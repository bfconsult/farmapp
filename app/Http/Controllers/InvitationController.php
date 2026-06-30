<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $propertyId = session('current_property_id');
        $property = Property::find($propertyId);

        $roles = $property->roles()->with('user')->get();
        $pendingInvitations = $property->invitations()->whereNull('accepted_at')->get();

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
        ]);

        // Managers can only invite workers
        $currentUserRole = Auth::user()->roleOn($property);
        if ($currentUserRole === Role::MANAGER && $validated['role'] !== Role::WORKER) {
            abort(403, 'Managers can only invite workers.');
        }

        $invitation = Invitation::create([
            'property_id' => $property->id,
            'invited_by' => Auth::id(),
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        // TODO: Send invitation email

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
    
        Role::create([
            'user_id' => $user->id,
            'property_id' => $invitation->property_id,
            'type' => $invitation->role,
        ]);
    
        $invitation->update(['accepted_at' => now()]);
    
        return redirect()->route('properties.index')->with('success', 'You have joined the property!');
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