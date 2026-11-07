<?php

namespace App\Services;

use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ParticipationTrophy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which Mini Pee Wees and Pee Wees are owed a participation trophy: those who
 * competed and did not win in forms or sparring.
 *
 * Nothing here is entered by hand. It is read from the published divisions and
 * the saved results, so a correction to a division corrects this list too.
 * Age is taken at the event's date, and comes from the birth date rather than a
 * division's name: boards are relabelled and can mix age groups.
 *
 * A win is a place from 1 to the event's winning_places. Ties share a place, so
 * with three winning places two 3rds both win.
 */
class ParticipationTrophies
{
    public const MINI_PEE_WEE_MAX_AGE = 8;

    public const PEE_WEE_MAX_AGE = 11;

    public function __construct(private DivisionResults $results) {}

    /**
     * @return array{
     *   ready: Collection<int, array>,
     *   waiting: Collection<int, array>,
     *   not_checked_in: Collection<int, array>,
     *   no_birth_date: Collection<int, array>
     * } ready: owed a trophy, every division saved. waiting: no win yet, a
     *   division still to be saved. The other two need a person to look.
     */
    public function build(Event $event): array
    {
        $entries = $this->entries($event);
        $completed = DivisionResult::completed()->with('placements')->where('event_id', $event->id)->get();

        $saved = $completed->mapWithKeys(fn (DivisionResult $r) => ["{$r->event_division_version_id}:{$r->position}" => true]);
        $winners = $completed->flatMap->placements
            ->where('place', '<=', $event->winning_places)
            ->pluck('user_id', 'user_id');
        $given = ParticipationTrophy::where('event_id', $event->id)->pluck('event_registration_id')->flip();

        $lists = ['ready' => [], 'waiting' => [], 'not_checked_in' => [], 'no_birth_date' => []];

        $registrations = EventRegistration::with('user.school')
            ->where('event_id', $event->id)
            ->whereIn('id', array_keys($entries))
            ->get();

        foreach ($registrations as $registration) {
            $user = $registration->user;

            if (! $user || $winners->has($user->id)) {
                continue;
            }

            $age = $user->dob ? (int) $user->dob->diffInYears(Carbon::parse($event->startdatetime)) : null;

            if ($age !== null && $age > self::PEE_WEE_MAX_AGE) {
                continue;
            }

            $divisions = $entries[$registration->id];
            $unsaved = array_filter($divisions, fn ($d) => ! $saved->has("{$d['version']}:{$d['position']}"));

            $row = [
                'registration_id' => $registration->id,
                'name' => $user->full_name,
                'school' => $user->school?->name,
                'group' => $age === null ? null : ($age <= self::MINI_PEE_WEE_MAX_AGE ? 'Mini Pee Wee' : 'Pee Wee'),
                'age' => $age,
                'divisions' => array_column($divisions, 'label'),
                'unsaved' => array_column($unsaved, 'label'),
                'given' => $given->has($registration->id),
            ];

            $list = match (true) {
                $age === null => 'no_birth_date',
                $registration->checkin === null => 'not_checked_in',
                $unsaved !== [] => 'waiting',
                default => 'ready',
            };

            $lists[$list][] = $row;
        }

        return array_map(
            fn (array $rows) => collect($rows)->sortBy([['school', 'asc'], ['name', 'asc']])->values(),
            $lists,
        );
    }

    /** @return array<int, array<int, array{version: int, position: int, label: string}>> registration id => divisions */
    private function entries(Event $event): array
    {
        $entries = [];

        foreach ($this->results->publishedVersions($event) as $version) {
            foreach ($version->data as $position => $division) {
                foreach ($division['members'] ?? [] as $registrationId) {
                    $entries[$registrationId][] = [
                        'version' => $version->id,
                        'position' => $position,
                        'label' => $division['label'],
                    ];
                }
            }
        }

        return $entries;
    }
}
