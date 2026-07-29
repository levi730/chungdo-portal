<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Build a household: an account holder, a spouse dependent, and two kids.
 */
function household(): array
{
    $jacob = User::factory()->single_student()->can_login()->create();
    $lauren = User::factory()->is_kid()->belongs_to($jacob)->create(['firstname' => 'Lauren']);
    $kidA = User::factory()->is_kid()->belongs_to($jacob)->create();
    $kidB = User::factory()->is_kid()->belongs_to($jacob)->create();

    return [$jacob, $lauren, $kidA, $kidB];
}

it('mirrors responsible_user_id into a primary guardianship on save', function () {
    [$jacob, $lauren] = household();

    expect($lauren->guardians()->pluck('users.id'))->toContain($jacob->id);
    expect($lauren->primaryGuardian()->id)->toBe($jacob->id);
    expect($jacob->dependents()->pluck('users.id'))->toContain($lauren->id);
});

it('lets the account holder manage their dependents but not the reverse', function () {
    [$jacob, $lauren, $kidA] = household();

    expect($jacob->canManage($lauren))->toBeTrue();
    expect($jacob->canManage($kidA))->toBeTrue();
    expect($jacob->canManage($jacob))->toBeTrue();
    expect($lauren->canManage($jacob))->toBeFalse();
    expect($lauren->canManage($kidA))->toBeFalse();
});

it('promotes a spouse to a mutual, household-wide guardian', function () {
    [$jacob, $lauren, $kidA, $kidB] = household();

    $lauren->promoteToLoginableAdult('lauren@example.com', $jacob);
    $lauren->refresh();

    // She can now log in with her own email, verified (no second gate).
    expect($lauren->can_login)->toBe(1);
    expect($lauren->getRawOriginal('email'))->toBe('lauren@example.com');
    expect($lauren->email_verified_at)->not->toBeNull();

    // Mutual: each adult can act for the other.
    expect($jacob->canManage($lauren))->toBeTrue();
    expect($lauren->canManage($jacob))->toBeTrue();

    // Household-wide: she gains co-guardianship of both children.
    expect($lauren->canManage($kidA))->toBeTrue();
    expect($lauren->canManage($kidB))->toBeTrue();

    // Jacob's original primary guardianship over Lauren is not disturbed.
    expect($lauren->primaryGuardian()->id)->toBe($jacob->id);
    // The children's primary guardian stays Jacob, not Lauren.
    expect($kidA->fresh()->primaryGuardian()->id)->toBe($jacob->id);
});

it('emails a set-password link when inviting a family member', function () {
    Notification::fake();
    [$jacob, $lauren] = household();

    $this->actingAs($jacob)
        ->post(route('profile-family.invite', ['id' => $lauren->id]), ['email' => 'lauren@example.com'])
        ->assertRedirect();

    expect($lauren->fresh()->can_login)->toBe(1);
    Notification::assertSentTo($lauren->fresh(), ResetPassword::class);
});

it('rejects inviting a user you do not manage', function () {
    [$jacob] = household();
    $stranger = User::factory()->single_student()->can_login()->create();

    $this->actingAs($jacob)
        ->post(route('profile-family.invite', ['id' => $stranger->id]), ['email' => 'x@example.com'])
        ->assertForbidden();
});

it('forbids editing or deleting a user you do not manage', function () {
    [$jacob] = household();
    $stranger = User::factory()->single_student()->create();

    $this->actingAs($jacob)
        ->delete(route('profile-family.delete', ['id' => $stranger->id]))
        ->assertForbidden();
});

it('will not delete a login-enabled account through the family route', function () {
    [$jacob, $lauren] = household();
    $lauren->promoteToLoginableAdult('lauren@example.com', $jacob);

    $this->actingAs($jacob)
        ->delete(route('profile-family.delete', ['id' => $lauren->id]))
        ->assertForbidden();

    expect(User::find($lauren->id))->not->toBeNull();
});
