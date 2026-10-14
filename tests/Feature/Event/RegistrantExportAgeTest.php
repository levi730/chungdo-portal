<?php

use App\Exports\Event\RegistrantExport;
use App\Models\Event;
use App\Models\User;

function registrantExportAgeCell(Event $event, string $dob): string
{
    $user = User::factory()->create();
    $user->forceFill(['dob' => $dob])->save();
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);

    $html = (new RegistrantExport($event))->view()->render();
    preg_match_all('/<td>(.*?)<\/td>/s', $html, $cells);

    return trim($cells[1][5]); // First, Last, Email, Rank, DOB, Age
}

it('gives the age as of the event date, not today', function () {
    $event = Event::create([
        'name' => 'Age Export', 'slug' => 'age-export', 'cost' => 0,
        'startdatetime' => '2030-06-15 09:00:00',
    ]);

    expect(registrantExportAgeCell($event, '2010-06-15'))->toBe('20')
        ->and((new RegistrantExport($event))->view()->render())->toContain('Age (at event)');
});

it('does not count a birthday that falls after the event', function () {
    $event = Event::create([
        'name' => 'Age Export', 'slug' => 'age-export', 'cost' => 0,
        'startdatetime' => '2030-06-15 09:00:00',
    ]);

    expect(registrantExportAgeCell($event, '2010-06-16'))->toBe('19');
});

it('falls back to today, and says so, when the event has no date', function () {
    $event = Event::create(['name' => 'Age Export', 'slug' => 'age-export', 'cost' => 0]);
    $dob = now()->subYears(12)->subDay()->toDateString();

    expect(registrantExportAgeCell($event, $dob))->toBe('12')
        ->and((new RegistrantExport($event))->view()->render())->toContain('Age (today)');
});
