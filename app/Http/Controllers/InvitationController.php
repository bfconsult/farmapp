<?php

namespace App\Http\Controllers;

use App\Mail\PropertyInvitation;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $propertyId = session('current_property_id');
        $property = Property::find($propertyId);

        $pendingInvitations = $property->invitations()->whereNull('accepted_at')->get();
        $pendingEmails = $pendingInvitations->pluck('email')->map(fn ($email) => strtolower($email));

        // A team member with a fresh pending invitation (e.g. a role change awaiting
        // acceptance) isn't fully settled yet, so they show under Pending, not here.
        $roles = $property->roles()->with('user')->get()
            ->reject(fn ($role) => $pendingEmails->contains(strtolower($role->user->email)))
            ->values();

        return Inertia::render('Invitations/Index', [
            'property' => $property,
            'roles' => $roles,
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

    public function accept(string $token)
    {
        $invitation = Invitation::where('token', $token)->whereNull('accepted_at')->firstOrFail();

        return Inertia::render('Invitations/Accept', [
            'invitation' => $invitation->load('property'),
        ]);
    }

    public function process(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->whereNull('accepted_at')->firstOrFail();
    
        $user = Auth::user();

        if (!$user) {
            session(['pending_invitation_token' => $token]);
            return redirect()->route('register', ['email' => $invitation->email]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            abort(403, 'This invitation was sent to a different email address.');
        }

        $alreadyOnProperty = Role::where('user_id', $user->id)
            ->where('property_id', $invitation->property_id)
            ->exists();

        if (!$alreadyOnProperty) {
            Role::create([
                'user_id' => $user->id,
                'property_id' => $invitation->property_id,
                'type' => $invitation->role,
            ]);
        }

        $invitation->update(['accepted_at' => now()]);
    
        return redirect()->route('properties.index')->with('success', 'You have joined the property!');
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

        $role->delete();

        return back()->with('success', 'User removed.');
    }

    public function destroyInvitation(Invitation $invitation)
    {
        $invitation->delete();
        return back();
    }
}