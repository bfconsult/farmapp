<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = ['property_id', 'invited_by', 'user_id', 'email', 'role', 'message', 'token', 'accepted_at'];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($invitation) {
            $invitation->token = $invitation->token ?? Str::random(40);
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Set only for an invitation to a team member who was already added
     * directly (the "add first, invite later" flow) - null for the classic
     * invite-a-stranger-by-email flow, where no User exists yet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Finishes whatever invitation is stashed in the session for a user who
     * just authenticated (registered, logged in, or claimed a shell
     * account) - centralised because all three entry points need identical
     * behaviour. Uses firstOrCreate rather than create so an in-flight role
     * promotion (invited as worker, promoted to manager before accepting)
     * isn't clobbered back down by the invitation's original role.
     */
    public static function completePendingFor(User $user): ?self
    {
        $token = session('pending_invitation_token');
        if (!$token) {
            return null;
        }

        $invitation = static::where('token', $token)->whereNull('accepted_at')->first();
        if (!$invitation) {
            session()->forget('pending_invitation_token');

            return null;
        }

        $matches = $invitation->user_id === $user->id
            || ($invitation->email && strtolower($invitation->email) === strtolower((string) $user->email));
        if (!$matches) {
            // Wrong person is logged in right now - leave the token stashed
            // in case the right one logs in later on this device.
            return null;
        }

        Role::firstOrCreate(
            ['user_id' => $user->id, 'property_id' => $invitation->property_id],
            ['type' => $invitation->role]
        );
        $invitation->update(['accepted_at' => now()]);
        session()->forget('pending_invitation_token');
        session(['current_property_id' => $invitation->property_id]);
        $user->update(['current_property_id' => $invitation->property_id]);

        return $invitation;
    }
}
