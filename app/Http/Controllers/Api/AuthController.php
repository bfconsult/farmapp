<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = $request->authenticate();

        $token = $user->createToken('flutter-app');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Identifies the user behind a stored token, so the mobile app can show
     * who's logged in after a cold start without asking them to log in
     * again - a stored token is otherwise trusted optimistically with no
     * way to attach a name/email to it (see AuthController's doc comment
     * on the Flutter side).
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request)
    {
        // Revokes only the token used for this request, not all of the
        // user's tokens/devices - a full "log out everywhere" action would
        // instead do $request->user()->tokens()->delete().
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
