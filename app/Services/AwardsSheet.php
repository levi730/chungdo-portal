<?php

namespace App\Services;

use App\Models\DivisionPlacement;
use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use Illuminate\Support\Collection;

/**
 * The sheet results are announced and awards presented from: 1st place in each
 * forms division, the winning places of each sparring division, and the
 * participation trophies.
 *
 * Divisions come from the published versions rather than the results, so one
 * that hasn't been saved shows up as missing instead of quietly being left out.
 */
class AwardsSheet
{
    public function __construct(
        private DivisionResults $results,
        private ParticipationTrophies $trophies,
    ) {}

    /**
     * @return array{
     *   forms: array<int, array{division: string, winners: Collection<int, DivisionPlacement>}>,
     *   sparring: array<int, array{division: string, awaiting_finals: bool, placements: Collection<int, DivisionPlacement>}>,
     *   unsaved: array<int, string>,
     *   trophies: Collection<int, array>,
     *   trophies_waiting: int,
     *   has_forms: bool,
     *   has_sparring: bool
     * } unsaved names divisions with nothing to read out yet
     */
    public function build(Event $event): array
    {
        $versions = $this->results->publishedVersions($event);
        $published = DivisionResult::published()->with('placements')->where('event_id', $event->id)->get()
            ->keyBy(fn (DivisionResult $r) => "{$r->event_division_version_id}:{$r->position}");

        $sheet = ['forms' => [], 'sparring' => [], 'unsaved' => []];

        foreach ($versions as $discipline => $version) {
            foreach ($version->data as $position => $division) {
                if (empty($division['members'])) {
                    continue;
                }

                $result = $published["{$version->id}:{$position}"] ?? null;
                $label = (string) ($division['label'] ?? '');

                if ($discipline === EventDivision::DISCIPLINE_FORMS) {
                    $winners = $result?->placements->where('place', 1)->values() ?? collect();

                    if ($winners->isEmpty()) {
                        $sheet['unsaved'][] = ucfirst($discipline).': '.$label;
                    } else {
                        $sheet['forms'][] = ['division' => $result->division_name, 'winners' => $winners];
                    }
                } elseif ($result) {
                    $sheet['sparring'][] = [
                        'division' => $result->division_name,
                        'awaiting_finals' => $result->isAwaitingFinals(),
                        'placements' => $result->placements
                            ->filter(fn (DivisionPlacement $p) => $p->isFinalist() || $p->place <= $event->winning_places)
                            ->values(),
                    ];
                } else {
                    $sheet['unsaved'][] = ucfirst($discipline).': '.$label;
                }
            }
        }

        $trophies = $this->trophies->build($event);

        return $sheet + [
            'trophies' => $trophies['ready'],
            'trophies_waiting' => $trophies['waiting']->count(),
            'has_forms' => isset($versions[EventDivision::DISCIPLINE_FORMS]),
            'has_sparring' => isset($versions[EventDivision::DISCIPLINE_SPARRING]),
        ];
    }
}
