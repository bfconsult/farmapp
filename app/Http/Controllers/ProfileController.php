<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Avatars are small (a header icon + profile page), so this is a much
     * tighter cap than PhotoController's 1920px job/note photos.
     */
    private const AVATAR_MAX_DIMENSION = 512;

    private const JPEG_QUALITY = 80;
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Upload or replace the user's avatar.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:10240',
        ]);

        $user = $request->user();
        $oldAvatar = $user->avatar;

        $image = (new ImageManager(new Driver()))
            ->decode($request->file('avatar')->getRealPath())
            ->scaleDown(width: self::AVATAR_MAX_DIMENSION, height: self::AVATAR_MAX_DIMENSION);

        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = 'avatars/'.Str::uuid().'.jpg';

        Storage::disk(config('filesystems.default'))->put($path, (string) $encoded);

        $user->update(['avatar' => $path]);

        if ($oldAvatar) {
            Storage::disk(config('filesystems.default'))->delete($oldAvatar);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Remove the user's avatar, reverting them to the initials fallback.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Deactivate the user's account, preserving their data.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->update([
            'email' => $user->email ? $user->email . '.deleted' : null,
            'deleted' => true,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
