<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\OneTimeCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** R-05 own profile, and R-06 first-sign-in password. */
class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('gyms'));
    }

    public function update(Request $request, OneTimeCodes $codes): UserResource
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::in(User::SUFFIXES)],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->fill($data);

        // A new email must be confirmed again before protected features work.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $codes->issue($user, OneTimeCodes::VERIFY);
        }

        return new UserResource($user->load('gyms'));
    }

    public function updatePhoto(Request $request): UserResource
    {
        $request->validate(['photo' => ['required', 'image', 'max:2048']]);
        $user = $request->user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->forceFill(['photo_path' => $request->file('photo')->store('profile-photos', 'public')])->save();

        return new UserResource($user->load('gyms'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed.']);
    }

    /** R-06: an owner-created account picks its own password before anything else works. */
    public function setFirstPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->password_change_required) {
            abort(409, 'Your password has already been set.');
        }

        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from the temporary one.']);
        }

        $user->forceFill(['password' => $data['password'], 'password_change_required' => false])->save();

        return response()->json(['message' => 'Password set.']);
    }
}
