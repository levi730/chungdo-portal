<?php

use App\Models\AccountLinkRequest;
use App\Models\User;
use App\Notifications\AccountLinkRequested;
use Illuminate\Support\Facades\Notification;

/**
 * Two separate households, each an account holder with one minor child.
 */
function twoHouseholds(): array
{
    $wife = User::factory()->single_student()->can_login()->create();
    $wifeKid = User::factory()->is_kid()->belongs_to($wife)->create();

    $husband = User::factory()->single_student()->can_login()->create();
    $husbandKid = User::factory()->is_kid()->belongs_to($husband)->create();

    return [$wife, $wifeKid, $husband, $husbandKid];
}

it('sends a link request and notifies the recipient', function () {
    Notification::fake();
    [$wife, , $husband] = twoHouseholds();

    $this->actingAs($wife)
        ->post(route('account-link.request'), ['email' => $husband->email])
        ->assertRedirect();

    $this->assertDatabaseHas('account_link_requests', [
        'requester_user_id' => $wife->id,
        'recipient_user_id' => $husband->id,
        'status' => 'pending',
    ]);
    Notification::assertSentTo($husband, AccountLinkRequested::class);

    // Nothing is linked until the recipient accepts.
    expect($wife->fresh()->isLinkedWith($husband->fresh()))->toBeFalse();
});

it('renders the request notification mail without error', function () {
    [$wife, , $husband] = twoHouseholds();

    $mail = (new AccountLinkRequested($wife))->toMail($husband);
    $rendered = (string) $mail->render();

    expect($rendered)->toContain($wife->full_name);
    expect($rendered)->toContain(route('profile.edit'));
});

it('links both households mutually once the recipient accepts', function () {
    [$wife, $wifeKid, $husband, $husbandKid] = twoHouseholds();

    $link = AccountLinkRequest::create([
        'requester_user_id' => $wife->id,
        'recipient_user_id' => $husband->id,
    ]);

    $this->actingAs($husband)
        ->post(route('account-link.accept', ['id' => $link->id]))
        ->assertRedirect();

    // Adults are mutual guardians.
    expect($wife->canManage($husband))->toBeTrue();
    expect($husband->canManage($wife))->toBeTrue();

    // Each adult can now act for the other's child.
    expect($wife->canManage($husbandKid))->toBeTrue();
    expect($husband->canManage($wifeKid))->toBeTrue();

    // Their own children are unaffected.
    expect($wife->canManage($wifeKid))->toBeTrue();

    expect($link->fresh()->status)->toBe('accepted');
});

it('does not link when the recipient declines', function () {
    [$wife, , $husband] = twoHouseholds();
    $link = AccountLinkRequest::create([
        'requester_user_id' => $wife->id,
        'recipient_user_id' => $husband->id,
    ]);

    $this->actingAs($husband)
        ->post(route('account-link.decline', ['id' => $link->id]))
        ->assertRedirect();

    expect($link->fresh()->status)->toBe('declined');
    expect($wife->canManage($husband))->toBeFalse();
});

it('only lets the recipient accept the request', function () {
    [$wife, , $husband] = twoHouseholds();
    $stranger = User::factory()->single_student()->can_login()->create();
    $link = AccountLinkRequest::create([
        'requester_user_id' => $wife->id,
        'recipient_user_id' => $husband->id,
    ]);

    // The requester cannot accept their own request.
    $this->actingAs($wife)
        ->post(route('account-link.accept', ['id' => $link->id]))
        ->assertForbidden();

    // An unrelated user cannot accept it either.
    $this->actingAs($stranger)
        ->post(route('account-link.accept', ['id' => $link->id]))
        ->assertForbidden();

    expect($link->fresh()->status)->toBe('pending');
});

it('lets the requester cancel a pending request but not the recipient', function () {
    [$wife, , $husband] = twoHouseholds();
    $link = AccountLinkRequest::create([
        'requester_user_id' => $wife->id,
        'recipient_user_id' => $husband->id,
    ]);

    $this->actingAs($husband)
        ->post(route('account-link.cancel', ['id' => $link->id]))
        ->assertForbidden();

    $this->actingAs($wife)
        ->post(route('account-link.cancel', ['id' => $link->id]))
        ->assertRedirect();

    expect($link->fresh()->status)->toBe('cancelled');
});

it('rejects a request to an unknown or already-linked account', function () {
    [$wife, , $husband] = twoHouseholds();

    // Unknown email.
    $this->actingAs($wife)
        ->post(route('account-link.request'), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors(['email'], null, 'updateFamilyMember');

    // Already linked.
    $wife->linkAccountWith($husband);
    $this->actingAs($wife)
        ->post(route('account-link.request'), ['email' => $husband->email])
        ->assertSessionHasErrors(['email'], null, 'updateFamilyMember');
});
