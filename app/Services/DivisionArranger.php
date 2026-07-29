<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Produces a "best logical first pass" at sparring divisions from a set of
 * registrations, then the admin adjusts by drag-and-drop.
 *
 * Rules (all overridable by hand afterward):
 *  - Never mix sexes, except the very youngest (Mini Pee Wee) when a division
 *    would otherwise be tiny.
 *  - Aim for divisions of at least MIN_SIZE.
 *  - Combine adjacent AGE groups first (belts stay pure while big enough), then
 *    combine adjacent BELT ranks (up to MAX_BELT_RANKS in a run).
 *  - Weight is not used here — it's shown in the UI for manual splitting.
 *
 * Each returned division: ['label' => string, 'sex' => 'M'|'F'|null,
 * 'members' => int[] registration ids].
 */
class DivisionArranger
{
    public const MIN_SIZE = 4;

    public const MAX_BELT_RANKS = 3;

    /** Age groups youngest → oldest (the natural_division age code). */
    private const AGE_ORDER = ['05', '09', '12', '16', '40'];

    /**
     * Age groups only ever merge within a band — little kids (5-11) never join
     * the 12-and-up groups, so an 11-year-old can't land with a 35-year-old.
     */
    private const AGE_BANDS = [['05', '09'], ['12', '16', '40']];

    /** First pass caps a division at 2 adjacent age groups... */
    private const MAX_AGE_GROUPS = 2;

    /** ...a second pass extends still-too-small divisions to a 3rd adjacent group. */
    private const MAX_AGE_GROUPS_EXTENDED = 3;

    /** Belt levels highest → lowest by rank_id. */
    private const BELT_ORDER = [6, 5, 4, 3, 2, 1, -2, -3, -4, -5, -6, -7];

    private const AGE_BOUNDS = ['05' => [5, 8], '09' => [9, 11], '12' => [12, 15], '16' => [16, 39], '40' => [40, 120]];

    /**
     * @param  Collection  $registrations  EventRegistration models with `user` loaded
     * @param  bool  $combineSexes  forms/kata: mix male & female, group by age + belt
     *                              only (labels omit the sex word)
     * @return array<int, array{label: string, sex: ?string, members: int[]}>
     */
    public function arrange(Collection $registrations, bool $combineSexes = false): array
    {
        $valid = [];
        $unassigned = [];
        foreach ($registrations as $reg) {
            $traits = $this->traits($reg);
            if ($traits) {
                $valid[] = $traits + ['id' => $reg->id];
            } else {
                $unassigned[] = $reg->id;
            }
        }

        if ($combineSexes) {
            // One pool: no sex split, no sex word in labels (sex passed as null).
            $divisions = $this->arrangeSex($valid, null);
        } else {
            $divisions = [];
            foreach (['M', 'F'] as $sex) {
                $members = array_values(array_filter($valid, fn ($m) => $m['sex'] === $sex));
                $divisions = array_merge($divisions, $this->arrangeSex($members, $sex));
            }
            $divisions = $this->combineYoungestSexes($divisions);
        }

        $out = array_map(fn ($d) => [
            'label' => $this->label($d['sex'], $d['items'], $combineSexes),
            'sex' => $d['sex'],
            'members' => array_column($d['items'], 'id'),
        ], $divisions);

        if ($unassigned) {
            $out[] = ['label' => 'Unassigned', 'sex' => null, 'members' => $unassigned];
        }

        return $out;
    }

    /** Extract (sex, age code, rank) from a registrant's natural division, or null. */
    private function traits($reg): ?array
    {
        $nd = $reg->user?->natural_division;
        if (! $nd) {
            return null;
        }
        [$sex, $age, $rank] = array_pad(explode('|', $nd), 3, null);
        if (! in_array($age, self::AGE_ORDER, true) || $rank === null || $rank === '') {
            return null;
        }

        return ['sex' => $sex, 'age' => $age, 'rank' => (int) $rank];
    }

    /**
     * Arrange one sex: cluster deficient belt ranks, then age-merge within each
     * cluster.
     *
     * @return array<int, array{sex: string, items: array}>
     */
    private function arrangeSex(array $members, ?string $sex): array
    {
        if (empty($members)) {
            return [];
        }

        $byRank = [];
        foreach ($members as $m) {
            $byRank[$m['rank']][] = $m;
        }
        $ranks = array_values(array_filter(self::BELT_ORDER, fn ($r) => isset($byRank[$r])));

        // Walk ranks high→low; a rank that's already big enough stands alone,
        // otherwise accumulate consecutive deficient ranks (≤ MAX_BELT_RANKS).
        $clusters = [];
        $i = 0;
        while ($i < count($ranks)) {
            $items = $byRank[$ranks[$i]];
            $rankCount = 1;
            while (count($items) < self::MIN_SIZE
                   && $rankCount < self::MAX_BELT_RANKS
                   && $i + 1 < count($ranks)) {
                $i++;
                $rankCount++;
                $items = array_merge($items, $byRank[$ranks[$i]]);
            }
            $clusters[] = ['items' => $items, 'ranks' => $rankCount];
            $i++;
        }

        // Fold a still-tiny trailing cluster into the previous one — but only if
        // that keeps the run within the max belt span (else leave it small).
        $n = count($clusters);
        if ($n > 1
            && count($clusters[$n - 1]['items']) < self::MIN_SIZE
            && $clusters[$n - 2]['ranks'] + $clusters[$n - 1]['ranks'] <= self::MAX_BELT_RANKS) {
            $clusters[$n - 2]['items'] = array_merge($clusters[$n - 2]['items'], $clusters[$n - 1]['items']);
            array_pop($clusters);
        }

        $divisions = [];
        foreach ($clusters as $cluster) {
            foreach ($this->ageMerge($cluster['items']) as $items) {
                $divisions[] = ['sex' => $sex, 'items' => $items];
            }
        }

        return $divisions;
    }

    /**
     * Bin adjacent age groups (youngest→oldest) into divisions of ≥ MIN_SIZE,
     * folding a small remainder into the previous division.
     *
     * @return array<int, array> list of member-lists
     */
    private function ageMerge(array $items): array
    {
        $byAge = [];
        foreach ($items as $m) {
            $byAge[$m['age']][] = $m;
        }

        $divisions = [];
        // Merge only within an age band (kids never join the 12+ groups).
        foreach (self::AGE_BANDS as $band) {
            $ages = array_values(array_filter($band, fn ($a) => isset($byAge[$a])));
            if (! $ages) {
                continue;
            }

            // First pass: bin adjacent groups, at most MAX_AGE_GROUPS, closing at MIN_SIZE.
            $bandDivs = []; // each: ['groups' => int, 'items' => []]
            $cur = ['groups' => 0, 'items' => []];
            foreach ($ages as $a) {
                if ($cur['items'] && ($cur['groups'] >= self::MAX_AGE_GROUPS || count($cur['items']) >= self::MIN_SIZE)) {
                    $bandDivs[] = $cur;
                    $cur = ['groups' => 0, 'items' => []];
                }
                $cur['items'] = array_merge($cur['items'], $byAge[$a]);
                $cur['groups']++;
            }
            if ($cur['items']) {
                $bandDivs[] = $cur;
            }

            // Second pass: fold a still-too-small division into an adjacent one,
            // extending to a third age group when the run stays within the band.
            $bandDivs = $this->extendSmallDivisions($bandDivs);

            foreach ($bandDivs as $bd) {
                $divisions[] = $bd['items'];
            }
        }

        return $divisions;
    }

    /**
     * Second pass: merge each still-undersized division into an adjacent one,
     * up to MAX_AGE_GROUPS_EXTENDED groups.
     *
     * @param  array<int, array{groups: int, items: array}>  $divs
     * @return array<int, array{groups: int, items: array}>
     */
    private function extendSmallDivisions(array $divs): array
    {
        $i = 0;
        while ($i < count($divs)) {
            if (count($divs[$i]['items']) >= self::MIN_SIZE) {
                $i++;
                continue;
            }

            $into = null;
            if ($i + 1 < count($divs) && $divs[$i]['groups'] + $divs[$i + 1]['groups'] <= self::MAX_AGE_GROUPS_EXTENDED) {
                $into = $i + 1;
            } elseif ($i - 1 >= 0 && $divs[$i - 1]['groups'] + $divs[$i]['groups'] <= self::MAX_AGE_GROUPS_EXTENDED) {
                $into = $i - 1;
            }
            if ($into === null) {
                $i++;
                continue;
            }

            $divs[$into]['items'] = array_merge($divs[$into]['items'], $divs[$i]['items']);
            $divs[$into]['groups'] += $divs[$i]['groups'];
            array_splice($divs, $i, 1);
            $i = 0; // indices shifted — rescan
        }

        return $divs;
    }

    /**
     * Merge a still-small Mini Pee Wee (age 05) division from each sex into one
     * mixed division.
     */
    private function combineYoungestSexes(array $divisions): array
    {
        $isTinyMini = fn ($d) => $d['sex'] !== null
            && count($d['items']) < self::MIN_SIZE
            && collect($d['items'])->every(fn ($m) => $m['age'] === '05');

        $mini = array_values(array_filter($divisions, $isTinyMini));
        if (count($mini) < 2) {
            return $divisions;
        }

        // Combine all tiny mini-pee-wee divisions (both sexes) into one.
        $merged = ['sex' => null, 'items' => array_merge(...array_column($mini, 'items'))];
        $rest = array_values(array_filter($divisions, fn ($d) => ! $isTinyMini($d)));
        $rest[] = $merged;

        return $rest;
    }

    /** Build a starting label from the members' sex, age spread, and belt spread. */
    private function label(?string $sex, array $items, bool $combineSexes = false): string
    {
        $ages = array_values(array_intersect(self::AGE_ORDER, array_unique(array_column($items, 'age'))));
        $ranks = array_unique(array_column($items, 'rank'));

        // Forms are combined male/female — the label is age + belt only.
        $sexWord = $combineSexes ? '' : $this->sexWord($sex, $ages);

        return trim($sexWord.' '.$this->ageLabel($ages).' '.$this->beltLabel($ranks));
    }

    private function sexWord(?string $sex, array $ages): string
    {
        if ($sex === null) {
            return 'Mixed';
        }
        $youth = empty(array_intersect($ages, ['16', '40'])); // all under 16
        if ($sex === 'F') {
            return $youth ? 'Girls' : "Women's";
        }

        return $youth ? 'Boys' : "Men's";
    }

    private function ageLabel(array $ages): string
    {
        if (empty($ages)) {
            return '';
        }
        $lo = self::AGE_BOUNDS[$ages[0]][0];
        $hi = self::AGE_BOUNDS[$ages[count($ages) - 1]][1];

        return $hi >= 120 ? "{$lo}+" : "{$lo}-{$hi}";
    }

    private function beltLabel(array $ranks): string
    {
        usort($ranks, fn ($a, $b) => array_search($a, self::BELT_ORDER, true) <=> array_search($b, self::BELT_ORDER, true));
        $high = $ranks[0];
        $low = $ranks[count($ranks) - 1];

        // All black belts: describe by degree.
        if ($low >= 1) {
            if ($high === $low) {
                return $this->ordinal($high).' Degree Black';
            }

            return $this->ordinal($high).'–'.$this->ordinal($low).' Degree Black';
        }

        $hiFam = $this->beltFamily($high);
        $loFam = $this->beltFamily($low);

        return $hiFam === $loFam ? $hiFam.' Belt' : "{$hiFam}–{$loFam}";
    }

    private function beltFamily(int $rank): string
    {
        return match (true) {
            $rank >= 1 => 'Black',
            $rank === -2 => 'Brown',
            $rank === -3 => 'Purple',
            $rank === -4 => 'Green',
            $rank === -5 => 'Yellow',
            default => 'White',
        };
    }

    private function ordinal(int $n): string
    {
        $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return $n.'th';
        }

        return $n.$suffix[$n % 10];
    }
}
