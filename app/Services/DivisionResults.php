<?php

namespace App\Services;

use App\Models\DivisionResult;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recording who placed in a division.
 *
 * A division is scored once and then closed. A correction reopens it, which
 * takes it off the results page until it is saved again.
 */
class DivisionResults
{
    /**
     * Save a division's places and close it.
     *
     * @param  array<int|string, int|string|null>  $places  registration id => place;
     *                                                      blank means unplaced
     */
    public function record(EventDivision $division, array $places, User $by): DivisionResult
    {
        $places = array_map('intval', array_filter($places, fn ($place) => $place !== null && $place !== ''));
        $members = $division->members()->with(['user.school', 'user.rank'])->get()->keyBy('id');

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

        return DB::transaction(function () use ($division, $places, $members, $by) {
            // Two scorers saving the same division must not both write placements.
            EventDivision::whereKey($division->id)->lockForUpdate()->first();

            $result = DivisionResult::where('event_division_id', $division->id)->first();

            if ($result?->isCompleted()) {
                throw new InvalidArgumentException('This division is already closed. Reopen it to make a correction.');
            }

            $result ??= new DivisionResult(['event_division_id' => $division->id]);
            $result->fill([
                'event_id' => $division->event_id,
                'discipline' => $division->discipline,
                'division_name' => (string) $division->name,
                'sort_order' => (int) $division->sort_order,
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

    /**
     * Why a board save would damage recorded results, or null when it would not.
     *
     * Natural, Auto-combine and restoring a version all produce boards with no
     * division ids, so saving one replaces every division. A rename is fine; a
     * scored division disappearing, or a placed competitor leaving it, is not.
     *
     * @param  array<int, array>  $divisions  the board as EventDivisionService::save receives it
     */
    public function conflictWithBoard(int $eventId, string $discipline, array $divisions): ?string
    {
        $results = DivisionResult::with('placements')
            ->where('event_id', $eventId)
            ->where('discipline', $discipline)
            ->whereNotNull('event_division_id')
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        $board = collect($divisions)->filter(fn ($d) => ! empty($d['id']))->keyBy(fn ($d) => (int) $d['id']);

        foreach ($results as $result) {
            if (! $board->has($result->event_division_id)) {
                return "“{$result->division_name}” has recorded results, so it can't be removed or regenerated.";
            }

            // A competitor withdrawn since scoring has no registration and is
            // not expected on the board.
            $placedRegIds = EventRegistration::where('event_id', $eventId)
                ->whereIn('user_id', $result->placements->pluck('user_id')->filter())
                ->pluck('id')
                ->all();
            $onBoard = array_map('intval', $board[$result->event_division_id]['members'] ?? []);

            if (array_diff($placedRegIds, $onBoard) !== []) {
                return "A placed competitor can't be moved out of “{$result->division_name}”.";
            }
        }

        return null;
    }
}
