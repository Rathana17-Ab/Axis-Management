<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    /**
     * Replace the signed-in user's profile photo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        /** @var User $user */
        $user = $request->user();
        $previousPath = $user->avatar_path;
        $path = $validated['photo']->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        if ($previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }

        return redirect()->route('admin.profile')->with('status', 'Profile photo updated.');
    }

    /**
     * Remove the signed-in user's profile photo.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $path = $user->avatar_path;

        $user->update(['avatar_path' => null]);

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }

        return redirect()->route('admin.profile')->with('status', 'Profile photo removed.');
    }
}
