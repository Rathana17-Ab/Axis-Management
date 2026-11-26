<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\SwitchSessionRequest;
use App\Http\Requests\UpdateTeamMemberLoginRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Department;
use App\Models\Position;
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
        $validated = $request->validated();

        $member = User::create([
            ...$validated,
            ...$this->directoryAttributes($validated),
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
        $validated = $request->validated();

        $user->update([...$validated, ...$this->directoryAttributes($validated)]);

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
     * Remove a person from the team directory.
     */
    public function destroy(User $user): JsonResponse
    {
        if (Auth::id() === $user->id) {
            return response()->json([
                'message' => 'You cannot remove the account you are signed in as.',
            ], 409);
        }

        $name = $user->name;
        $departmentId = $user->department_id;
        $positionId = $user->position_id;

        $user->delete();

        return response()->json([
            'data' => [
                'message' => "Removed {$name} from the team.",
                'departmentId' => $departmentId,
                'positionId' => $positionId,
            ],
        ]);
    }

    /**
     * Create or replace a member's password without ever returning it.
     */
    public function resetPassword(UpdateTeamMemberLoginRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->update([
            'email' => $validated['email'] ?? $user->email,
            'password' => $validated['password'],
            'can_login' => true,
        ]);

        return response()->json([
            'data' => [
                'message' => "Sign-in details saved for {$user->name}.",
                'username' => $user->email,
                'active' => $user->can_login,
            ],
        ]);
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

    /**
     * Resolve directory selections into both the new links and the legacy columns
     * that still drive task assignment and task pickers.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function directoryAttributes(array $validated): array
    {
        $attributes = [];

        if (array_key_exists('department_id', $validated)) {
            $department = Department::query()->find($validated['department_id']);
            $attributes['department_id'] = $department?->id;

            if ($department !== null) {
                $attributes['department'] = $department->name;
            }
        } elseif (array_key_exists('department', $validated)) {
            $attributes['department'] = $validated['department'];
            $departmentId = Department::query()->where('name', $validated['department'])->value('id');

            if ($departmentId !== null) {
                $attributes['department_id'] = $departmentId;
            }
        }

        if (array_key_exists('position_id', $validated)) {
            $position = Position::query()->find($validated['position_id']);
            $attributes['position_id'] = $position?->id;

            if ($position !== null) {
                $attributes['position'] = $position->legacyPosition();
            }
        } elseif (array_key_exists('position', $validated)) {
            $attributes['position'] = $validated['position'];
            $positionId = Position::query()->where('code', $validated['position'])->value('id');

            if ($positionId !== null) {
                $attributes['position_id'] = $positionId;
            }
        }

        return $attributes;
    }
}
