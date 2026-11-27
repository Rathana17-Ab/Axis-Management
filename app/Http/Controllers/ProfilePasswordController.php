<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePasswordController extends Controller
{
    /**
     * Change the signed-in user's password after verifying the current one.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['password' => $validated['password']]);

        $request->session()->regenerate();

        return redirect()->route('admin.profile')->with('status', 'Password updated.');
    }
}
