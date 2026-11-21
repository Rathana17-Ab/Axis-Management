<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * The team member operating the workspace: the switched-in session user,
     * or the first member with login access (the prototype "opens immediately").
     */
    protected function actingMember(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user ?? User::query()->where('can_login', true)->orderBy('id')->first();
    }
}
