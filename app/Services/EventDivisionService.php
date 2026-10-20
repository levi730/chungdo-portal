<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;

/**
 * Loads, auto-arranges, and persists an event's divisions for the drag-and-drop
 * board. A Combined tournament keeps two independent arrangements — sparring and
 * forms/kata — selected by $discipline throughout (default sparring).
 */
class EventDivisionService
{
    public function __construct(private DivisionArranger $arranger = new DivisionArranger()) {}

    /** Label of the virtual group holding competitors who aren't in any division yet. */
    public const UNPLACED_LABEL = 'New — not placed yet';

    /**
     * The saved arrangement for a discipline as board data, or an empty list.
     * Competitors who registered (or switched into this discipline) after the
     * save are surfaced in a leading UNPLACED group, and members who no longer
     * compete in it are flagged `stale` — so a late registration is visible
     * without rebuilding the board and losing hand-made merges.
     *
     * @return array<int, array>
     */
    public function board(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
    {
        $relation = $this->membersRelation($discipline);

        $divisions = EventDivision::where('event_id', $event->id)
            ->where('discipline', $discipline)
            ->with(["$relation.user.rank", "$relation.user.school"])
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($divisions->isEmpty()) {
            return [];
        }

        $competitors = $this->competitors($event, $discipline);

        $board = $divisions->map(fn (EventDivision $d) => [
            'id' => $d->id,
            'label' => $d->name,
            'members' => $d->{$relation}
                ->map(fn ($r) => $this->memberDto($r, ! $competitors->has($r->id)))
                ->values()->all(),
        ])->values()->all();

        return $this->withUnplaced($board, $competitors);
    }

    /**
     * Run the auto-arranger over the discipline's competitors (filtered by their
     * participation choice) and return board data — not persisted. This is the
     * "combine small divisions for me" pass.
     *
     * @return array<int, array>
     */
    public function auto(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
    {
        $regs = $this->competitors($event, $discipline);

        return $this->toBoard($this->arranger->arrange($regs, $discipline === EventDivision::DISCIPLINE_FORMS), $regs);
    }

    /**
     * Every natural division (belt × sex × age group) on its own, uncombined, as
     * board data — not persisted. The starting point for merging by hand.
     *
     * @return array<int, array>
     */
    public function natural(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
    {
        $regs = $this->competitors($event, $discipline);

        return $this->toBoard($this->arranger->natural($regs), $regs);
    }

    /** The discipline's competitors, keyed by registration id. */
    private function competitors(Event $event, string $discipline): \Illuminate\Support\Collection
    {
        return EventRegistration::where('event_id', $event->id)
            ->with(['user.rank', 'user.school', 'addonAnswers'])->get()
            ->filter(fn ($r) => $this->competesIn($event, $r, $discipline))
            ->keyBy('id');
    }

    /** Arranger output → unsaved board data. */
    private function toBoard(array $divisions, \Illuminate\Support\Collection $regs): array
    {
        return array_map(fn ($d) => [
            'id' => null,
            'label' => $d['label'],
            'members' => collect($d['members'])
                ->map(fn ($regId) => $this->memberDto($regs[$regId]))
                ->all(),
        ], $divisions);
    }

    /**
     * Persist the board for a discipline: upsert its divisions, assign each
     * registration's division column, drop removed divisions, snapshot a version.
     *
     * @param  array  $divisions  [['id'=>?int, 'label'=>string, 'members'=>[regId,...]], ...]
     * @return int  the id of the version snapshot created by this save
     */
    public function save(Event $event, array $divisions, string $discipline = EventDivision::DISCIPLINE_SPARRING): int
    {
        $column = $this->assignmentColumn($discipline);
        $versionId = 0;

        DB::transaction(function () use ($event, $divisions, $discipline, $column, &$versionId) {
            $keptIds = [];
            $assignments = []; // regId => divisionId

            foreach (array_values($divisions) as $order => $div) {
                $model = EventDivision::firstOrNew([
                    'id' => $div['id'] ?? null,
                    'event_id' => $event->id,
                ]);
                $model->event_id = $event->id;
                $model->discipline = $discipline;
                $model->name = $div['label'] ?? null;
                $model->sort_order = $order;
                $model->save();
                $keptIds[] = $model->id;

                foreach (($div['members'] ?? []) as $regId) {
                    $assignments[(int) $regId] = $model->id;
                }
            }

            // Assign / clear this discipline's division column on every registration.
            $regs = EventRegistration::where('event_id', $event->id)->get();
            foreach ($regs as $reg) {
                $target = $assignments[$reg->id] ?? null;
                if ($reg->{$column} !== $target) {
                    $reg->{$column} = $target;
                    $reg->save();
                }
            }

            // Remove divisions of THIS discipline that are no longer present.
            EventDivision::where('event_id', $event->id)
                ->where('discipline', $discipline)
                ->when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds))
                ->delete();

            $version = EventDivisionVersion::create([
                'event_id' => $event->id,
                'discipline' => $discipline,
                'created_by_user_id' => auth()->id(),
                'data' => array_values(array_map(fn ($d) => [
                    'label' => $d['label'] ?? '',
                    'members' => array_map('intval', $d['members'] ?? []),
                ], $divisions)),
                'created_at' => now(),
            ]);
            $versionId = $version->id;
        });

        return $versionId;
    }

    /**
     * Saved versions for a discipline (newest first) for the history panel.
     *
     * @return array<int, array>
     */
    public function versions(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
    {
        $publishedId = $event->publishedVersionIdFor($discipline);

        return EventDivisionVersion::where('event_id', $event->id)
            ->where('discipline', $discipline)
            ->with('createdBy')->orderByDesc('id')->get()
            ->map(fn (EventDivisionVersion $v) => [
                'id' => $v->id,
                'created_at' => $v->created_at?->toIso8601String(),
                'by' => $v->createdBy?->full_name ?? 'Unknown',
                'divisions' => count($v->data),
                'members' => collect($v->data)->sum(fn ($d) => count($d['members'] ?? [])),
                'starred' => (bool) $v->starred,
                'published' => $v->id === $publishedId,
                'note' => (string) $v->note,
            ])->all();
    }

    /**
     * Board data for a saved version (for Restore).
     *
     * @return array<int, array>
     */
    public function versionBoard(EventDivisionVersion $version): array
    {
        $regIds = collect($version->data)->flatMap(fn ($d) => $d['members'] ?? [])->all();
        $regs = EventRegistration::whereIn('id', $regIds)->where('event_id', $version->event_id)
            ->with(['user.rank', 'user.school'])->get()->keyBy('id');
        $competitors = $this->competitors($version->event, $version->discipline ?? EventDivision::DISCIPLINE_SPARRING);

        $board = array_map(fn ($d) => [
            'id' => null,
            'label' => $d['label'] ?? '',
            'members' => collect($d['members'] ?? [])
                ->filter(fn ($id) => $regs->has($id))
                ->map(fn ($id) => $this->memberDto($regs[$id], ! $competitors->has($id)))
                ->values()->all(),
        ], $version->data);

        return $this->withUnplaced($board, $competitors);
    }

    /**
     * Prepend a `pending` group of competitors not in any of the board's
     * divisions. The client never saves this group, so anyone left in it stays
     * unplaced and reappears here on the next load.
     */
    private function withUnplaced(array $board, \Illuminate\Support\Collection $competitors): array
    {
        $placed = collect($board)->flatMap(fn ($d) => array_column($d['members'], 'id'))->flip();
        $unplaced = $competitors->reject(fn ($r) => $placed->has($r->id));
        if ($unplaced->isEmpty()) {
            return $board;
        }

        array_unshift($board, [
            'id' => null,
            'label' => self::UNPLACED_LABEL,
            'pending' => true,
            'members' => $unplaced->map(fn ($r) => $this->memberDto($r))->values()->all(),
        ]);

        return $board;
    }

    /** Toggle a version's star; returns the new starred state. */
    public function toggleStar(EventDivisionVersion $version): bool
    {
        $version->starred = ! $version->starred;
        $version->save();

        return $version->starred;
    }

    /** Set a version's free-text note. */
    public function updateNote(EventDivisionVersion $version, ?string $note): void
    {
        $version->note = ($note === null || trim($note) === '') ? null : $note;
        $version->save();
    }

    /** Make a version the event's published arrangement for its discipline. */
    public function publish(Event $event, EventDivisionVersion $version): void
    {
        if ($version->discipline === EventDivision::DISCIPLINE_FORMS) {
            $event->published_forms_version_id = $version->id;
        } else {
            $event->published_version_id = $version->id;
        }
        $event->save();
    }

    public function unpublish(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): void
    {
        if ($discipline === EventDivision::DISCIPLINE_FORMS) {
            $event->published_forms_version_id = null;
        } else {
            $event->published_version_id = null;
        }
        $event->save();
    }

    /**
     * The published version summary for a discipline, or null when unpublished.
     *
     * @return array{id:int, at:?string, starred:bool}|null
     */
    public function publishedInfo(Event $event, string $discipline = EventDivision::DISCIPLINE_SPARRING): ?array
    {
        $v = $discipline === EventDivision::DISCIPLINE_FORMS
            ? $event->publishedFormsDivisionVersion
            : $event->publishedDivisionVersion;
        if (! $v) {
            return null;
        }

        return ['id' => $v->id, 'at' => $v->created_at?->toDayDateTimeString(), 'starred' => (bool) $v->starred];
    }

    /** The EventDivision relation holding a discipline's members. */
    private function membersRelation(string $discipline): string
    {
        return $discipline === EventDivision::DISCIPLINE_FORMS ? 'formsRegistrations' : 'registrations';
    }

    /** The registration column that stores a discipline's division assignment. */
    private function assignmentColumn(string $discipline): string
    {
        return $discipline === EventDivision::DISCIPLINE_FORMS ? 'forms_event_division_id' : 'event_division_id';
    }

    /**
     * Whether a registrant competes in the given discipline. When the
     * participation add-on is off, everyone competes in everything.
     */
    private function competesIn(Event $event, EventRegistration $reg, string $discipline): bool
    {
        // Only Combined tournaments split by participation; everyone else competes
        // in the single discipline the event runs.
        if (! ($event->hasForms() && $event->hasSparring())) {
            return true;
        }

        $choice = $reg->participation();

        return $discipline === EventDivision::DISCIPLINE_FORMS
            ? in_array($choice, ['forms', 'both'], true)
            : in_array($choice, ['sparring', 'both'], true);
    }

    /**
     * @param  bool  $stale  in a saved division but no longer competing in this
     *                       discipline (changed their participation)
     * @return array<string, mixed>
     */
    private function memberDto(EventRegistration $reg, bool $stale = false): array
    {
        $u = $reg->user;

        return [
            'id' => $reg->id,
            'name' => $u?->full_name ?? 'Unknown',
            'school' => $u?->school?->shortname ?? '',
            'rank' => $u?->rank?->rank ?? '',
            'rank_id' => $u?->rank_id,
            'age' => $u?->age,
            'weight' => $u?->weight,
            'sex' => $u?->sex,
            'stale' => $stale,
        ];
    }
}
