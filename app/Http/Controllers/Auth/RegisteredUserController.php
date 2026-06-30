<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Complete any pending invitation
        if ($token = session('pending_invitation_token')) {
            $invitation = \App\Models\Invitation::where('token', $token)->whereNull('accepted_at')->first();
            if ($invitation && $invitation->email === $user->email) {
                \App\Models\Role::create([
                    'user_id' => $user->id,
                    'property_id' => $invitation->property_id,
                    'type' => $invitation->role,
                ]);
                $invitation->update(['accepted_at' => now()]);
                session()->forget('pending_invitation_token');
            }
        }

return redirect(route('dashboard', absolute: false));

        return redirect(route('dashboard', absolute: false));
    }
}
