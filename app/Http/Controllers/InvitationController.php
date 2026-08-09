<?php

namespace App\Http\Controllers;

use App\Mail\PropertyInvitation;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $propertyId = session('current_property_id');
        $property = Property::find($propertyId);

        $pendingInvitations = $property->invitations()->whereNull('accepted_at')->get();

        // Only an email-only invitation (no user_id) hides a matching team
        // member from the main list - that's the legacy "invited a stranger
        // who turns out to already be here" case. An invite tied to an
        // existing member (the add-first-invite-later flow) is shown
        // alongside them instead, so an unclaimed member stays visible.
        $emailOnlyPendingEmails = $pendingInvitations
            ->whereNull('user_id')
            ->pluck('email')
            ->filter()
            ->map(fn ($email) => strtolower($email));

        $roles = $property->roles()->with(['user', 'supplier'])->get()
            ->reject(fn ($role) => $role->user->email
                && $emailOnlyPendingEmails->contains(strtolower($role->user->email)))
            ->values();

        return Inertia::render('Invitations/Index', [
            'property' => $property,
            'roles' => $roles,
            'suppliers' => Supplier::where('property_id', $propertyId)->orderBy('name')->get(),
            'pendingInvitations' => $pendingInvitations,
            'currentUserRole' => Auth::user()->roleOn($property),
        ]);
    }

    public function store(Request $request)
    {
        $propertyId = session('current_property_id');
        $property = Property::find($propertyId);

        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:manager,worker,approver',
            'message' => 'nullable|string|max:2000',
        ]);

        // Managers can only invite workers
        $currentUserRole = Auth::user()->roleOn($property);
        if ($currentUserRole === Role::MANAGER && $validated['role'] !== Role::WORKER) {
            abort(403, 'Managers can only invite workers.');
        }

        // Superseded by this new invite - an old token for the same person
        // must not stay valid alongside the fresh one we're about to send.
        Invitation::where('property_id', $property->id)
            ->where('email', strtolower($validated['email']))
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::create([
            'property_id' => $property->id,
            'invited_by' => Auth::id(),
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'message' => $validated['message'] ?? null,
        ]);

        Mail::to($invitation->email)->send(new PropertyInvitation($invitation));

        return back()->with('success', 'Invitation sent.');
    }

    /**
     * Adds a team member directly - no email required, no invite sent. For
     * someone who may never use the app themselves (so their time still
     * needs to be logged), or just to get them working before they get
     * around to accepting an invite. "Send Invite" (inviteMember, below) is
     * an optional follow-up once they have an email on file.
     */
    public function storeMember(Request $request)
    {
        $propertyId = session('current_property_id');
        $property = Property::find($propertyId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => 'required|in:manager,worker,approver',
            'hourly_rate' => 'nullable|numeric|min:0',
        ], [
            'email.unique' => 'Someone with that email already has an account - use Invite by Email instead.',
        ]);

        // Same gate as inviting by email: managers can only add workers.
        $currentUserRole = Auth::user()->roleOn($property);
        if ($currentUserRole === Role::MANAGER && $validated['role'] !== Role::WORKER) {
            abort(403, 'Managers can only add workers.');
        }

        DB::transaction(function () use ($validated, $property) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => isset($validated['email']) ? strtolower($validated['email']) : null,
                // Never null/empty - Hash::check() throws on a non-hash
                // value, and the 'hashed' cast turns this random string into
                // a real bcrypt hash that nothing can guess, so the account
                // simply can't be logged into until it's claimed via invite.
                'password' => Str::random(48),
            ]);

            if ($validated['hourly_rate'] ?? null) {
                $user->update(['hourly_rate' => $validated['hourly_rate']]);
            }

            Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => $validated['role']]);
        });

        return back()->with('success', 'Team member added.');
    }

    /**
     * Sends an invite to a team member who was already added directly (see
     * storeMember) - persists whatever email is supplied onto the member
     * themselves (also how a member added with no email finally gets one),
     * then sends the same invite email as the regular invite flow.
     */
    public function inviteMember(Request $request, Role $role)
    {
        $property = $role->property;
        abort_unless($property->id === (int) session('current_property_id'), 404);

        $currentUserRole = Auth::user()->roleOn($property);
        if ($currentUserRole === Role::MANAGER && $role->type !== Role::WORKER) {
            abort(403, 'Managers can only invite workers.');
        }
        // The invitations.role column has no "admin" option, and an admin
        // already has full access - there's nothing to invite them into.
        abort_if($role->type === Role::ADMIN, 403, 'Admins already have full access - nothing to invite.');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($role->user_id)],
            'message' => 'nullable|string|max:2000',
        ]);
        $email = strtolower($validated['email']);

        $role->user->update(['email' => $email]);

        // Superseded by this new invite - an old token for the same person
        // must not stay valid alongside the fresh one we're about to send.
        Invitation::where('property_id', $property->id)
            ->where(fn ($q) => $q->where('email', $email)->orWhere('user_id', $role->user_id))
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::create([
            'property_id' => $property->id,
            'invited_by' => Auth::id(),
            'user_id' => $role->user_id,
            'email' => $email,
            'role' => $role->type,
            'message' => $validated['message'] ?? null,
        ]);

        Mail::to($email)->send(new PropertyInvitation($invitation));

        return back()->with('success', 'Invitation sent.');
    }

    public function accept(string $token)
    {
        $invitation = Invitation::where('token', $token)->whereNull('accepted_at')->with(['property', 'user'])->firstOrFail();

        // A member who was added directly (storeMember) already has a name
        // and a role - all that's missing is a password, so they get a
        // short "set your password" form instead of the full registration
        // flow that Accept.jsx sends brand-new people through.
        if ($invitation->user && !$invitation->user->isClaimed()) {
            return Inertia::render('Invitations/Claim', [
                'invitation' => $invitation,
            ]);
        }

        return Inertia::render('Invitations/Accept', [
            'invitation' => $invitation,
        ]);
    }

    public function process(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->whereNull('accepted_at')->firstOrFail();

        $user = Auth::user();

        if (!$user) {
            session(['pending_invitation_token' => $token]);

            if ($invitation->user && !$invitation->user->isClaimed()) {
                return redirect()->route('invitations.accept', $token);
            }

            // An account already exists for this address (either a claimed
            // member being re-invited, or someone who separately signed up
            // with the same email) - registering again would just trip the
            // unique-email rule and dead-end them, so send them to log in
            // instead; AuthenticatedSessionController finishes the
            // invitation once they're actually signed in.
            if (User::where('email', strtolower($invitation->email))->exists()) {
                return redirect()->route('login')->with('status', 'Log in to accept this invitation.');
            }

            return redirect()->route('register', ['email' => $invitation->email]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            abort(403, 'This invitation was sent to a different email address.');
        }

        session(['pending_invitation_token' => $token]);
        Invitation::completePendingFor($user);

        return redirect()->route('properties.show', $invitation->property_id)->with('success', 'You have joined the property!');
    }

    /**
     * Sets a password for a team member who was added directly (storeMember)
     * and is now claiming their existing account via an invite, rather than
     * registering a brand new one.
     */
    public function claim(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->whereNull('accepted_at')->firstOrFail();
        $user = $invitation->user;
        abort_if(!$user || $user->isClaimed(), 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // A different account signed in on this device would otherwise
        // fight the login below.
        if (Auth::check() && Auth::id() !== $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $user->update([
            'name' => $validated['name'],
            'email' => strtolower($invitation->email),
            'password' => $validated['password'],
            'claimed_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        session(['pending_invitation_token' => $invitation->token]);
        Invitation::completePendingFor($user);

        return redirect()->route('properties.show', $invitation->property_id)->with('success', 'Welcome aboard!');
    }

    public function updateRole(Request $request, Role $role)
    {
        $property = $role->property;
        $currentUserRole = Auth::user()->roleOn($property);

        // Only admins can change a team member's role.
        if ($currentUserRole !== Role::ADMIN) {
            abort(403, 'Only admins can change roles.');
        }

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', Role::TYPES),
        ]);

        // Can't demote the last admin.
        if ($role->type === Role::ADMIN && $validated['type'] !== Role::ADMIN) {
            $adminCount = $property->roles()->where('type', Role::ADMIN)->count();
            if ($adminCount <= 1) {
                abort(403, 'Cannot change the role of the last admin.');
            }
        }

        $role->update(['type' => $validated['type']]);

        return back()->with('success', 'Role updated.');
    }

    public function updateMemberRate(Request $request, Role $role)
    {
        $currentUserRole = Auth::user()->roleOn($role->property);

        // A manager can set their own rate and a worker's, but not an
        // admin's or another manager's - only an admin can do that.
        $allowed = $currentUserRole === Role::ADMIN
            || ($currentUserRole === Role::MANAGER && ($role->type === Role::WORKER || $role->user_id === Auth::id()));

        if (!$allowed) {
            abort(403);
        }

        $validated = $request->validate([
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        $role->user->update(['hourly_rate' => $validated['hourly_rate']]);

        return back()->with('success', 'Hourly rate updated.');
    }

    /**
     * Links (or unlinks) this role to a Supplier they bill through - see
     * Role::supplier(). Same permission shape as updateMemberRate: this is
     * "billing configuration" for the role, not a role-type change.
     */
    public function updateMemberSupplier(Request $request, Role $role)
    {
        $currentUserRole = Auth::user()->roleOn($role->property);

        $allowed = $currentUserRole === Role::ADMIN
            || ($currentUserRole === Role::MANAGER && ($role->type === Role::WORKER || $role->user_id === Auth::id()));

        if (!$allowed) {
            abort(403);
        }

        $validated = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('property_id', $role->property_id)],
        ]);

        $role->update(['supplier_id' => $validated['supplier_id'] ?? null]);

        return back()->with('success', 'Billing link updated.');
    }

    public function destroyRole(Role $role)
    {
        $property = $role->property;
        $currentUserRole = Auth::user()->roleOn($property);

        // Managers can only remove workers
        if ($currentUserRole === Role::MANAGER && $role->type !== Role::WORKER) {
            abort(403);
        }

        // Can't remove the last admin
        if ($role->type === Role::ADMIN) {
            $adminCount = $property->roles()->where('type', Role::ADMIN)->count();
            if ($adminCount <= 1) {
                abort(403, 'Cannot remove the last admin.');
            }
        }

        // Clear any pending invitation for them too, so a stale token can't
        // resurface later pointing at a role that no longer exists. Matches
        // by user_id (always available) as well as email, since a member
        // added directly may have no email at all.
        Invitation::where('property_id', $property->id)
            ->where(function ($q) use ($role) {
                $q->where('user_id', $role->user_id);
                if ($role->user->email) {
                    $q->orWhere('email', strtolower($role->user->email));
                }
            })
            ->whereNull('accepted_at')
            ->delete();

        // The User row (and everything logged against it - work sessions,
        // job assignments) is deliberately kept: removing someone from a
        // property should never silently delete their billed time. They
        // just stop appearing in this property's active team.
        $role->delete();

        return back()->with('success', 'User removed.');
    }

    public function destroyInvitation(Invitation $invitation)
    {
        $invitation->delete();
        return back();
    }
}