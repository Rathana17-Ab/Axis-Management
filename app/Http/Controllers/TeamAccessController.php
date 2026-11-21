<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\SwitchSessionRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamAccessController extends Controller
{
    /**
     * Add an engineering team member with login access.
     */
    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $member = User::create([
            ...$request->validated(),
            'can_login' => true,
            'password' => Str::password(32),
        ]);

        return TeamMemberResource::make($member)->response()->setStatusCode(201);
    }

    /**
     * Update an employee's department, position, description or active/inactive status.
     */
    public function update(UpdateTeamMemberRequest $request, User $user): TeamMemberResource
    {
        $user->update($request->validated());

        return TeamMemberResource::make($user);
    }

    /**
     * Enable or lock a team member's login access.
     */
    public function toggleLogin(User $user): TeamMemberResource
    {
        $user->update(['can_login' => ! $user->can_login]);

        return TeamMemberResource::make($user);
    }

    /**
     * Switch the active workspace session to another team member.
     */
    public function switchSession(SwitchSessionRequest $request): JsonResponse|TeamMemberResource
    {
        $member = User::query()->where('email', Str::lower($request->validated('email')))->first();

        if ($member === null) {
            return response()->json(['message' => "We couldn't find anyone with that email."], 422);
        }

        if (! $member->can_login) {
            return response()->json(['message' => 'This account is locked. Ask an admin to turn sign-in back on.'], 423);
        }

        $password = $request->validated('password');

        if (filled($password) && ! Hash::check($password, $member->password)) {
            return response()->json(['message' => "That password doesn't look right."], 422);
        }

        Auth::login($member);
        $request->session()->regenerate();

        return TeamMemberResource::make($member)->additional(['csrf_token' => csrf_token()]);
    }
}
