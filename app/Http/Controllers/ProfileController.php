<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('profile.index', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), User::profileRules($user->id));

        if ($validator->fails()) {
            return redirect()
                ->route('profile.index')
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($user, $validated): void {
            $user->update($validated);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_PROFILE_UPDATED,
                "Updated profile details for {$user->email}."
            );
        });

        return redirect()
            ->route('profile.index')
            ->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), User::passwordRules());

        if ($validator->fails()) {
            return redirect()
                ->route('profile.index')
                ->withErrors($validator, 'password')
                ->withInput();
        }

        $password = $validator->validated()['password'];

        DB::transaction(function () use ($user, $password): void {
            $user->update(['password' => $password]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_PROFILE_UPDATED,
                "Changed account password for {$user->email}."
            );
        });

        return redirect()
            ->route('profile.index')
            ->with('success', 'Password changed successfully.');
    }
}
