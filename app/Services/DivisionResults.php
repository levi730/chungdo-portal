<?php

namespace App\Services;

use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recording who placed in a division.
 *
 * Scoring works from the published version of a discipline: that is what the
 * cards were printed from. A division is the entry at a position in the
 * version's data. Publishing a different version removes the scoring and it
 * starts over (EventDivisionService::publish).
 *
 * A division is scored once and then closed. A correction reopens it, which
 * takes it off the results page until it is saved again.
 */
class DivisionResults
{
    /** @return array<string, EventDivisionVersion> discipline => published version */
    public function publishedVersions(Event $event): array
    {
        $versions = [];

        foreach ([EventDivision::DISCIPLINE_SPARRING, EventDivision::DISCIPLINE_FORMS] as $discipline) {
            $version = EventDivisionVersion::find($event->publishedVersionIdFor($discipline));

            if ($version) {
                $versions[$discipline] = $version;
            }
        }

        return $versions;
    }

    /**
     * A division's registrations. Anyone withdrawn since publishing has no
     * registration and drops out here.
     *
     * @return Collection<int, EventRegistration> keyed by registration id
     */
    public function members(EventDivisionVersion $version, int $position): Collection
    {
        if (! isset($version->data[$position])) {
            throw new InvalidArgumentException('That division is not in the published version.');
        }

        return EventRegistration::with(['user.school', 'user.rank'])
            ->where('event_id', $version->event_id)
            ->whereIn('id', $version->data[$position]['members'] ?? [])
            ->get()
            ->keyBy('id');
    }

    /**
     * Save a division's places and close it.
     *
     * @param  array<int|string, int|string|null>  $places  registration id => place;
     *                                                      blank means unplaced
     */
    public function record(EventDivisionVersion $version, int $position, array $places, User $by): DivisionResult
    {
        $places = array_map('intval', array_filter($places, fn ($place) => $place !== null && $place !== ''));
        $members = $this->members($version, $position);

        foreach ($places as $regId => $place) {
            if (! $members->has($regId)) {
                throw new InvalidArgumentException('That competitor is not in this division.');
            }
            if ($place < 1 || $place > $members->count()) {
                throw new InvalidArgumentException("Places run from 1 to {$members->count()} in this division.");
            }
        }

        if ($places === []) {
            throw new InvalidArgumentException('Number at least one competitor before saving.');
        }

        return DB::transaction(function () use ($version, $position, $places, $members, $by) {
            // Shares a lock with publishing, and keeps two scorers apart.
            $event = Event::whereKey($version->event_id)->lockForUpdate()->firstOrFail();

            if ($event->publishedVersionIdFor($version->discipline) !== $version->id) {
                throw new InvalidArgumentException('A different version has been published. Go back to the divisions and start again.');
            }

            $result = DivisionResult::where('event_division_version_id', $version->id)
                ->where('position', $position)
                ->first();

            if ($result?->isCompleted()) {
                throw new InvalidArgumentException('This division is already closed. Reopen it to make a correction.');
            }

            $result ??= new DivisionResult(['event_division_version_id' => $version->id, 'position' => $position]);
            $result->fill([
                'event_id' => $version->event_id,
                'discipline' => $version->discipline,
                'division_name' => (string) ($version->data[$position]['label'] ?? ''),
                'completed_at' => now(),
                'completed_by_user_id' => $by->id,
            ])->save();

            $result->placements()->delete();

            foreach ($places as $regId => $place) {
                $user = $members[$regId]->user;

                $result->placements()->create([
                    'user_id' => $user?->id,
                    'place' => $place,
                    'name' => $user?->full_name ?? 'Unknown',
                    'school' => $user?->school?->shortname,
                    'rank' => $user?->rank?->rank,
                ]);
            }

            return $result;
        });
    }

    /** Take a closed division off the results page so it can be corrected. */
    public function reopen(DivisionResult $result): void
    {
        $result->update(['completed_at' => null, 'completed_by_user_id' => null]);
    }
}
