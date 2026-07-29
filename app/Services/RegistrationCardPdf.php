<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Renders tournament registration cards to a single PDF with Typst — one compile
 * for the whole document (2-up per US-letter page), replacing the per-page
 * headless-Chromium render + pdftk merge.
 */
class RegistrationCardPdf
{
    private string $template = 'resources/typst/registration-card.typ';

    private string $tournamentTemplate = 'resources/typst/tournament-card.typ';

    /**
     * Build the cards PDF and return its absolute path. Pass $blanksOnly to get a
     * single page of two blank cards.
     */
    public function generate(Event $event, bool $blanksOnly = false): string
    {
        return $this->render($this->payload($event, $blanksOnly));
    }

    /**
     * Build the new combined-tournament cards (Forms and/or Sparring "applications").
     * $variant is 'forms', 'sparring', or 'both'.
     */
    public function generateTournament(Event $event, string $variant, bool $blanksOnly = false): string
    {
        return $this->render($this->tournamentPayload($event, $variant, $blanksOnly), $this->tournamentTemplate);
    }

    /**
     * The new tournament cards grouped by the published division arrangement(s):
     * Forms cards by forms divisions and/or Sparring cards by sparring divisions.
     * $variant is 'forms', 'sparring', or 'both'.
     */
    public function generateTournamentByDivision(Event $event, string $variant, bool $covers = false): string
    {
        return $this->render($this->tournamentDivisionPayload($event, $variant, $covers), $this->tournamentTemplate);
    }

    /**
     * Render every registrant's card grouped by the event's published division
     * arrangement, with an optional cover/separator per division.
     */
    public function generateByDivision(Event $event, bool $covers = false): string
    {
        return $this->render($this->divisionPayload($event, $covers));
    }

    /** Write the payload to JSON and compile it with Typst; returns the PDF path. */
    private function render(array $payload, ?string $template = null): string
    {
        $template ??= $this->template;

        $dir = storage_path('app/typst');
        File::ensureDirectoryExists($dir);

        $uid = Str::uuid()->toString();
        $jsonAbs = "$dir/$uid.json";
        $pdfAbs = "$dir/$uid.pdf";

        File::put($jsonAbs, json_encode($payload, JSON_UNESCAPED_SLASHES));

        // Path handed to Typst is relative to the project root (its --root).
        $jsonRel = '/'.ltrim(str_replace(base_path(), '', $jsonAbs), '/');

        $result = Process::path(base_path())->run([
            config('events.typst_bin', 'typst'),
            'compile',
            '--root', base_path(),
            '--input', "data=$jsonRel",
            $template,
            $pdfAbs,
        ]);

        File::delete($jsonAbs);

        if (! $result->successful()) {
            throw new \RuntimeException('Typst render failed: '.$result->errorOutput());
        }

        return $pdfAbs;
    }

    /**
     * The Typst payload for the published-division print: one entry per division
     * (in the arrangement's order) holding its registrants' cards.
     *
     * @return array<string, mixed>
     */
    public function divisionPayload(Event $event, bool $covers = false): array
    {
        $version = $event->publishedDivisionVersion;
        if (! $version) {
            throw new \RuntimeException('This event has no published division arrangement.');
        }

        $regIds = collect($version->data)->flatMap(fn ($d) => $d['members'] ?? [])->all();
        $regs = \App\Models\EventRegistration::whereIn('id', $regIds)->where('event_id', $event->id)
            ->with(['user.school', 'user.rank'])->get()->keyBy('id');

        $divisions = array_values(array_filter(array_map(function ($d) use ($regs, $event) {
            $cards = collect($d['members'] ?? [])
                ->filter(fn ($id) => $regs->has($id) && $regs[$id]->user)
                ->map(fn ($id) => $this->cardData($regs[$id]->user, $event))
                ->values()->all();

            return $cards ? ['label' => $d['label'] ?: 'Division', 'cards' => $cards] : null;
        }, $version->data)));

        return [
            'event' => $event->name,
            'logo' => '/public/img/CDKTKD_logo.svg',
            'covers' => $covers,
            'divisions' => $divisions,
        ];
    }

    /**
     * The full Typst document payload (event, logo, cards).
     *
     * @return array<string, mixed>
     */
    public function payload(Event $event, bool $blanksOnly = false): array
    {
        return [
            'event' => $event->name,
            'logo' => '/public/img/CDKTKD_logo.svg',
            'cards' => $blanksOnly ? [null, null] : $this->cardsFor($event),
        ];
    }

    /**
     * Registrants for the event as card payloads, ordered by rank/sex/age, with a
     * trailing blank card when the count is odd (so pages stay 2-up).
     *
     * @return array<int, array|null>
     */
    private function cardsFor(Event $event): array
    {
        $cards = $this->orderedUsers($event)->map(fn (User $u) => $this->cardData($u, $event))->all();

        if (count($cards) % 2 === 1) {
            $cards[] = null; // pad to an even count
        }

        return $cards;
    }

    /**
     * The event's registrants ordered for card printing: highest rank first, then
     * sex / age / name.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function orderedUsers(Event $event)
    {
        return $event->users()
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            ->orderBy('rank_id', 'desc')
            ->orderBy('users.sex')
            ->orderBy('users.dob')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(User $user, Event $event): array
    {
        $school = $user->school;

        return [
            'name' => $user->fullname,
            'age' => (string) ($user->getAgeOnDate($event->startdatetime) ?? ''),
            'dob' => $user->dob?->format('m/d/Y') ?? '',
            'sex' => (string) $user->sex,
            'weight' => (string) $user->weight,
            'height' => $user->height ? $user->height_text : '',
            'address' => trim(sprintf('%s %s, %s %s', $user->address1, $user->city, $user->state, $user->zip), ' ,'),
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'school' => (string) ($school?->name ?? ''),
            'city' => (string) ($school?->city ?? ''),
            'state' => (string) ($school?->state ?? ''),
            'instructors' => (string) ($school?->principal_instructors_text ?? ''),
            'instructor_ranks' => (string) ($school?->principal_instructors_rank_text ?? ''),
            'note' => (string) ($user->event_notes()->where('event_id', $event->id)->value('note') ?? ''),
            'mark' => $this->divisionMark($user),
        ];
    }

    /**
     * The division grid cell to fill for this registrant, from their natural
     * division (sex|age-group|rank). Null when they have no valid division.
     *
     * @return array{row: int, col: int, degree: int|null}|null
     */
    private function divisionMark(User $user): ?array
    {
        $nd = $user->natural_division; // e.g. "M|40|3"
        if (! $nd) {
            return null;
        }
        [$sex, $ageGroup, $rank] = array_pad(explode('|', $nd), 3, null);
        $rank = (int) $rank;

        $row = match ($ageGroup) {
            '05' => 0, '09' => 1, '12' => 2, '40' => 3,
            '16' => $sex === 'F' ? 4 : 5,
            default => null,
        };
        if ($row === null) {
            return null;
        }

        // Belt columns: Black(2), Black(1), Brown, Purple, Green, Yellow, White.
        [$col, $degree] = match (true) {
            $rank >= 2 => [0, $rank > 2 ? $rank : null],
            $rank === 1 => [1, null],
            $rank === -2 => [2, null],
            $rank === -3 => [3, null],
            $rank === -4 => [4, null],
            $rank === -5 => [5, null],
            $rank <= -6 => [6, null],
            default => [null, null],
        };
        if ($col === null) {
            return null;
        }

        return ['row' => $row, 'col' => $col, 'degree' => $degree];
    }

    // ---- Combined-tournament cards (tournament-card.typ) --------------------

    /**
     * The Typst payload for the new tournament cards: the event/venue header plus
     * the registrants' Forms and/or Sparring cards.
     *
     * @return array<string, mixed>
     */
    public function tournamentPayload(Event $event, string $variant, bool $blanksOnly = false): array
    {
        return array_merge($this->tournamentHeader($event), [
            'cards' => $blanksOnly
                ? $this->tournamentBlankCards($variant)
                : $this->tournamentCards($event, $variant),
        ]);
    }

    /**
     * Payload for the by-division print: one Typst "division" per published
     * division (Forms and/or Sparring), each holding its members' cards.
     *
     * @return array<string, mixed>
     */
    public function tournamentDivisionPayload(Event $event, string $variant, bool $covers = false): array
    {
        $disciplines = match ($variant) {
            'forms' => ['forms'],
            'sparring' => ['sparring'],
            default => ['forms', 'sparring'],
        };

        $divisions = [];
        foreach ($disciplines as $discipline) {
            $versionId = $event->publishedVersionIdFor($discipline);
            $version = $versionId ? \App\Models\EventDivisionVersion::find($versionId) : null;
            if (! $version) {
                throw new \RuntimeException("This event has no published {$discipline} division arrangement.");
            }

            $regIds = collect($version->data)->flatMap(fn ($d) => $d['members'] ?? [])->all();
            $regs = \App\Models\EventRegistration::whereIn('id', $regIds)->where('event_id', $event->id)
                ->with(['user.school', 'user.rank'])->get()->keyBy('id');

            foreach ($version->data as $d) {
                $cards = collect($d['members'] ?? [])
                    ->filter(fn ($id) => $regs->has($id) && $regs[$id]->user)
                    ->map(fn ($id) => $this->tournamentCardData($regs[$id]->user, $event, $discipline))
                    ->values()->all();

                if ($cards) {
                    $divisions[] = ['label' => $d['label'] ?: 'Division', 'discipline' => $discipline, 'cards' => $cards];
                }
            }
        }

        return array_merge($this->tournamentHeader($event), [
            'covers' => $covers,
            'divisions' => $divisions,
        ]);
    }

    /**
     * The venue/event info shown across the top of every card.
     *
     * @return array<string, string>
     */
    private function tournamentHeader(Event $event): array
    {
        return [
            'event' => (string) $event->name,
            'subtitle' => 'Chung Do Association — All Members',
            'host' => $event->hostName(),
            'where' => trim((string) preg_replace('/\s*\R\s*/', ', ', (string) $event->location)),
            'when' => $event->startdatetime?->format('l, F j, Y') ?? '',
            'start_time' => $event->startdatetime
                ? str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $event->startdatetime->format('g:i a'))
                : '',
            'fee' => $this->tournamentFee($event),
        ];
    }

    /** "$90 (Forms + Sparring)" — the registration-fee add-on cost plus a type tag. */
    private function tournamentFee(Event $event): string
    {
        $descriptor = match (true) {
            $event->hasForms() && $event->hasSparring() => ' (Forms + Sparring)',
            $event->hasForms() => ' (Forms)',
            $event->hasSparring() => ' (Sparring)',
            default => '',
        };

        $cost = (float) ($event->addon('registration_fee')?->setting('cost', 0) ?? 0);
        if ($cost <= 0) {
            return trim($descriptor);
        }

        $amount = $cost == floor($cost) ? number_format($cost) : number_format($cost, 2);

        return '$'.$amount.$descriptor;
    }

    /**
     * The card payloads for the chosen variant. 'both' prints a Forms section and a
     * Sparring section, each padded to an even count so the two never share a sheet.
     *
     * @return array<int, array|null>
     */
    private function tournamentCards(Event $event, string $variant): array
    {
        $users = $this->orderedUsers($event);

        $variants = match ($variant) {
            'forms' => ['forms'],
            'sparring' => ['sparring'],
            default => ['forms', 'sparring'],
        };

        // For a Combined tournament, only print the cards each competitor signed up
        // for; otherwise every registrant gets the requested variant.
        $participation = ($event->hasForms() && $event->hasSparring()) ? $this->participationMap($event) : null;

        $cards = [];
        foreach ($variants as $v) {
            $section = $users
                ->filter(fn (User $u) => $participation === null
                    || $this->doesVariant($participation[$u->id] ?? \App\EventAddons\EventParticipationAddon::BOTH, $v))
                ->map(fn (User $u) => $this->tournamentCardData($u, $event, $v))
                ->values()->all();
            if (count($section) % 2 === 1) {
                $section[] = null; // keep each variant on its own sheets
            }
            $cards = array_merge($cards, $section);
        }

        return $cards;
    }

    /** Whether a participation choice includes the given card variant. */
    private function doesVariant(string $choice, string $variant): bool
    {
        return $variant === 'forms'
            ? in_array($choice, ['forms', 'both'], true)
            : in_array($choice, ['sparring', 'both'], true);
    }

    /**
     * Each registrant's participation choice for the event, keyed by user id.
     *
     * @return array<int, string>
     */
    private function participationMap(Event $event): array
    {
        return \App\Models\EventRegistrationAddon::where('type', 'participation')
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->with('registration:id,user_id')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->registration->user_id => $a->value])
            ->all();
    }

    /** A single page of two blank cards for the chosen variant. */
    private function tournamentBlankCards(string $variant): array
    {
        return match ($variant) {
            'forms' => [['variant' => 'forms'], ['variant' => 'forms']],
            'sparring' => [['variant' => 'sparring'], ['variant' => 'sparring']],
            default => [['variant' => 'forms'], ['variant' => 'sparring']],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentCardData(User $user, Event $event, string $variant): array
    {
        $school = $user->school;

        // "Paid" is X-ed on the card when the registrant's fee is fully covered.
        $due = (float) ($user->pivot->amount_due ?? 0);
        $paid = $due > 0 && (float) ($user->pivot->amount_paid ?? 0) >= $due;

        return [
            'variant' => $variant,
            'paid' => $paid,
            'name' => $user->fullname,
            'age' => (string) ($user->getAgeOnDate($event->startdatetime) ?? ''),
            'dob' => $user->dob?->format('m/d/Y') ?? '',
            'sex' => (string) $user->sex,
            'weight' => (string) $user->weight,
            'height' => $user->height ? $user->height_text : '',
            'address' => trim(sprintf('%s %s, %s %s', $user->address1, $user->city, $user->state, $user->zip), ' ,'),
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'school' => (string) ($school?->name ?? ''),
            'instructors' => (string) ($school?->principal_instructors_text ?? ''),
            'instructor_ranks' => (string) ($school?->principal_instructors_rank_text ?? ''),
            'note' => (string) ($user->event_notes()->where('event_id', $event->id)->value('note') ?? ''),
            'mark' => $this->tournamentDivisionMark($user),
        ];
    }

    /**
     * The cell to shade on the new 5-row x 8-column grid (age group x belt), from
     * the registrant's natural division. Black belts split into 3rd/2nd/1st-degree
     * columns; 4th degree and up fall in the 3rd-degree column. Null when invalid.
     *
     * @return array{row: int, col: int}|null
     */
    private function tournamentDivisionMark(User $user): ?array
    {
        $nd = $user->natural_division; // e.g. "M|40|3"
        if (! $nd) {
            return null;
        }
        [$sex, $ageGroup, $rank] = array_pad(explode('|', $nd), 3, null);
        $rank = (int) $rank;

        $row = match ($ageGroup) {
            '05' => 0, '09' => 1, '12' => 2, '16' => 3, '40' => 4,
            default => null,
        };
        if ($row === null) {
            return null;
        }

        // Columns: Black(3), Black(2), Black(1), Brown, Purple, Green, Yellow, White.
        $col = match (true) {
            $rank >= 3 => 0,   // 3rd degree and above
            $rank === 2 => 1,
            $rank === 1 => 2,
            $rank === -2 => 3,
            $rank === -3 => 4,
            $rank === -4 => 5,
            $rank === -5 => 6,
            $rank <= -6 => 7,
            default => null,
        };
        if ($col === null) {
            return null;
        }

        return ['row' => $row, 'col' => $col];
    }
}
