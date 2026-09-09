<?php

use App\Models\Event;
use App\Models\User;
use App\Models\UserNote;
use App\Services\RegistrationCardPdf;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Notes about a member, split into permanent and temporary.
 *
 * The distinction is the whole point: "hard of hearing" must follow the person
 * to every event, and "cannot stay for finals" must not outlive the one it was
 * written for. The tests below are mostly about what does *not* show.
 */
function noteTaker(): User
{
    Permission::findOrCreate('event.viewAllSchoolRegistrants', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('event.viewAllSchoolRegistrants');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function noteEvent(string $slug = 'note-cup', string $start = '2026-02-01 09:00'): Event
{
    return Event::create([
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'cost' => 0,
        'startdatetime' => Carbon::parse($start),
    ]);
}

function noteSubject(Event $event): User
{
    // The registrants list prints the rank name, and the test database seeds no
    // ranks — DatabaseTransactions rolls this back with everything else.
    \DB::table('ranks')->insertOrIgnore([
        'id' => -4, 'rank' => 'Green Belt', 'color' => 'green', 'content_color' => 'white',
    ]);

    $user = User::factory()->create();
    // The list reads rank/sex/dob straight off the row.
    \DB::table('users')->where('id', $user->id)->update(['sex' => 'F', 'rank_id' => -4, 'dob' => '2014-01-01']);
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);

    return $user->fresh();
}

it('records a temporary note against the event it was written for', function () {
    $event = noteEvent();
    $subject = noteSubject($event);

    $this->actingAs(noteTaker())
        ->post("/user/{$subject->id}/add-note", [
            'note' => 'Needs to leave by 12:15pm at latest',
            'scope' => 'temporary',
            'event_id' => $event->id,
        ])
        ->assertOk()
        ->assertJsonPath('scope', 'temporary');

    $note = UserNote::where('user_id', $subject->id)->sole();

    expect($note->event_id)->toBe($event->id);
    expect($note->isPermanent())->toBeFalse();
});

it('refuses a temporary note with no event, because it could never be shown', function () {
    $subject = noteSubject(noteEvent());

    $this->actingAs(noteTaker())
        ->postJson("/user/{$subject->id}/add-note", ['note' => 'Leaving early', 'scope' => 'temporary'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('event_id');
});

it('lets a member without the registrants permission write nothing', function () {
    $event = noteEvent();
    $subject = noteSubject($event);
    $outsider = User::factory()->create();
    $outsider->markEmailAsVerified();

    $this->actingAs($outsider)
        ->post("/user/{$subject->id}/add-note", [
            'note' => 'Had two brain surgeries',
            'scope' => 'permanent',
            'event_id' => $event->id,
        ])
        ->assertForbidden();

    expect(UserNote::where('user_id', $subject->id)->count())->toBe(0);
});

it('shows a permanent note at every event but a temporary note only at its own', function () {
    $winter = noteEvent('winter-cup', '2026-02-01 09:00');
    $spring = noteEvent('spring-cup', '2026-05-01 09:00');
    $subject = noteSubject($winter);
    $spring->registrations()->attach($subject->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $author = noteTaker();

    $subject->notes()->create([
        'note' => 'Hard of hearing', 'scope' => 'permanent', 'added_by' => $author->id,
    ]);
    $subject->notes()->create([
        'note' => 'Cannot stay for finals', 'scope' => 'temporary',
        'event_id' => $winter->id, 'added_by' => $author->id,
    ]);

    expect($subject->notes()->visibleForEvent($winter)->pluck('note')->all())
        ->toEqualCanonicalizing(['Hard of hearing', 'Cannot stay for finals']);

    expect($subject->notes()->visibleForEvent($spring)->pluck('note')->all())
        ->toBe(['Hard of hearing']);
});

it('hides a temporary note that names no event', function () {
    $event = noteEvent();
    $subject = noteSubject($event);
    $author = noteTaker();

    $subject->notes()->create([
        'note' => 'Orphaned temporary note', 'scope' => 'temporary', 'added_by' => $author->id,
    ]);

    expect($subject->notes()->visibleForEvent($event)->count())->toBe(0);
});

it('shows the registrants list only this event note plus the permanent ones', function () {
    $winter = noteEvent('winter-list', '2026-02-01 09:00');
    $spring = noteEvent('spring-list', '2026-05-01 09:00');
    $subject = noteSubject($winter);
    $author = noteTaker();

    $subject->notes()->create(['note' => 'Uses an inhaler', 'scope' => 'permanent', 'added_by' => $author->id]);
    $subject->notes()->create([
        'note' => 'Left early last time', 'scope' => 'temporary',
        'event_id' => $spring->id, 'added_by' => $author->id,
    ]);

    $this->actingAs($author)
        ->get("/event/{$winter->slug}/registrants")
        ->assertOk()
        ->assertSee('Uses an inhaler')
        ->assertDontSee('Left early last time');
});

it('prints a permanent note and this event note on the registration card', function () {
    $winter = noteEvent('winter-card', '2026-02-01 09:00');
    $other = noteEvent('other-card', '2026-05-01 09:00');
    $subject = noteSubject($winter);
    $author = noteTaker();

    $subject->notes()->create(['note' => 'Uses an inhaler', 'scope' => 'permanent', 'added_by' => $author->id]);
    $subject->notes()->create([
        'note' => 'Not competing, broken arm', 'scope' => 'temporary',
        'event_id' => $winter->id, 'added_by' => $author->id,
    ]);
    $subject->notes()->create([
        'note' => 'From another event', 'scope' => 'temporary',
        'event_id' => $other->id, 'added_by' => $author->id,
    ]);

    $card = (new RegistrationCardPdf())->payload($winter)['cards'][0];

    expect($card['note'])->toBe('Uses an inhaler · Not competing, broken arm');
});
