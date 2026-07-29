<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncZulipJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Services\ZulipGroupResolver;
use Spatie\Permission\Models\Role;

/**
 * Super-admin user administration: search users and edit their profile data,
 * roles, and password. Access is gated by the `manage-users` ability
 * (super.admin only) on the route group.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'zulipConfigured' => filled(config('services.zulip.site')),
            'lastSync' => Cache::get('zulip.last_sync'),
        ]);
    }

    public function syncZulip()
    {
        SyncZulipJob::dispatch();

        return redirect()
            ->route('admin.users.index')
            ->with('admin-user-success', 'Zulip sync queued. Refresh in a moment for the result.');
    }

    public function edit(User $user, ZulipGroupResolver $zulipGroups)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'allRoles' => Role::orderBy('name')->pluck('name'),
            // Read-only preview of what OIDC would sync to Zulip on next login.
            'zulipBeltRank' => $user->rank?->rank,
            'zulipGroups' => $zulipGroups->for($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip' => ['nullable', 'string', 'max:20'],
            'is_student' => ['nullable', 'boolean'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'rank_id' => ['nullable', 'exists:ranks,id'],
            'dob' => ['nullable', 'date'],
            'height' => ['nullable', 'integer', 'min:0', 'max:120'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:1500'],
            'sex' => ['nullable', 'string', 'max:1'],
            'roles' => ['array'],
            'roles.*' => [Rule::in(Role::pluck('name')->all())],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
            'sync_to_zulip' => ['nullable', 'boolean'],
        ]);

        $user->forceFill([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'zip' => $validated['zip'] ?? null,
            'is_student' => $request->boolean('is_student') ? 1 : 0,
            'school_id' => $validated['school_id'] ?? null,
            'rank_id' => $validated['rank_id'] ?? null,
            'dob' => ! empty($validated['dob']) ? new Carbon($validated['dob']) : null,
            'height' => $validated['height'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'sync_to_zulip' => $request->boolean('sync_to_zulip'),
        ])->save();

        if (! empty($validated['password'])) {
            $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        }

        $this->syncRoles($request, $user, $validated['roles'] ?? []);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('admin-user-success', $user->full_name.' updated successfully.');
    }

    public function sendPasswordReset(Request $request, User $user)
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.edit', $user)->with(
            'admin-user-success',
            $status === Password::RESET_LINK_SENT
                ? 'Password reset link sent to '.$user->email.'.'
                : 'Could not send a reset link: '.__($status)
        );
    }

    /**
     * Sync the user's roles, guarding against a super.admin stripping their own
     * super.admin role (which would immediately lock them out of this page).
     */
    private function syncRoles(Request $request, User $user, array $roles): void
    {
        if (
            $user->is($request->user())
            && $user->hasRole('super.admin')
            && ! in_array('super.admin', $roles, true)
        ) {
            $roles[] = 'super.admin';
            session()->flash('admin-user-warning', 'You cannot remove super.admin from your own account.');
        }

        $user->syncRoles($roles);
    }
}
