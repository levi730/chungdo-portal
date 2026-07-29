<?php

use App\Services\DivisionArranger;

/** A fake registration: the arranger only needs id + user->natural_division. */
function reg(int $id, ?string $nd): object
{
    return (object) ['id' => $id, 'user' => (object) ['natural_division' => $nd]];
}

/** Build N registrations sharing one natural division, ids offset by $start. */
function regs(int $start, int $count, string $nd): array
{
    return array_map(fn ($i) => reg($start + $i, $nd), range(0, $count - 1));
}

function arrange(array $regs): array
{
    return (new DivisionArranger())->arrange(collect($regs));
}

it('keeps a large natural division on its own', function () {
    $divisions = arrange(regs(1, 5, 'M|40|3')); // 5 men's executive 3rd-degree black

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['members'])->toHaveCount(5);
    expect($divisions[0]['label'])->toBe("Men's 40+ 3rd Degree Black");
});

it('merges adjacent age groups within a belt to reach the minimum', function () {
    // Brown belt boys: 2 mini pee wee + 2 pee wee = one division of 4.
    $divisions = arrange([...regs(1, 2, 'M|05|-2'), ...regs(10, 2, 'M|09|-2')]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['members'])->toHaveCount(4);
    expect($divisions[0]['label'])->toBe('Boys 5-11 Brown Belt');
});

it('splits a belt into separate age divisions when each is big enough', function () {
    $divisions = arrange([...regs(1, 4, 'M|05|-2'), ...regs(10, 5, 'M|16|-2')]);

    expect($divisions)->toHaveCount(2);
    expect(collect($divisions)->pluck('label')->all())
        ->toBe(['Boys 5-8 Brown Belt', "Men's 16-39 Brown Belt"]);
});

it('combines adjacent belt ranks when a belt is too small', function () {
    // Yellow (2) + White (2), all men 16-39 -> one Yellow–White division of 4.
    $divisions = arrange([...regs(1, 2, 'M|16|-5'), ...regs(10, 2, 'M|16|-6')]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['members'])->toHaveCount(4);
    expect($divisions[0]['label'])->toBe("Men's 16-39 Yellow–White");
});

it('does not combine more than three belt ranks', function () {
    // Four single-person color-belt ranks, all men 16-39. First three cluster
    // (3 people); the fourth stays its own (small) division.
    $divisions = arrange([
        ...regs(1, 1, 'M|16|-2'), ...regs(2, 1, 'M|16|-3'),
        ...regs(3, 1, 'M|16|-4'), ...regs(4, 1, 'M|16|-5'),
    ]);

    expect($divisions)->toHaveCount(2);
    expect(collect($divisions)->map(fn ($d) => count($d['members']))->sort()->values()->all())->toBe([1, 3]);
});

it('combines the youngest kids across sexes when a division is tiny', function () {
    // 2 boys + 2 girls white mini pee wee -> one mixed division of 4.
    $divisions = arrange([...regs(1, 2, 'M|05|-6'), ...regs(10, 2, 'F|05|-6')]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['sex'])->toBeNull();
    expect($divisions[0]['members'])->toHaveCount(4);
    expect($divisions[0]['label'])->toBe('Mixed 5-8 White Belt');
});

it('keeps sexes separate for older age groups', function () {
    $divisions = arrange([...regs(1, 4, 'M|16|-4'), ...regs(10, 4, 'F|16|-4')]);

    expect($divisions)->toHaveCount(2);
    expect(collect($divisions)->pluck('sex')->sort()->values()->all())->toBe(['F', 'M']);
});

it('buckets registrants without a valid division as Unassigned', function () {
    $divisions = arrange([...regs(1, 4, 'M|16|-4'), reg(99, null)]);

    $unassigned = collect($divisions)->firstWhere('label', 'Unassigned');
    expect($unassigned)->not->toBeNull();
    expect($unassigned['members'])->toBe([99]);
});

it('never merges little kids with the 12-and-up groups', function () {
    // A small purple belt spread across pee wee, junior, adult, executive — the
    // real bug that put 11-year-olds with a 35-year-old.
    $divisions = arrange([
        ...regs(1, 3, 'M|09|-3'),   // pee wee (9-11), ids 1-3
        ...regs(10, 3, 'M|12|-3'),  // junior, ids 10-12
        ...regs(20, 2, 'M|16|-3'),  // adult, ids 20-21
        ...regs(30, 1, 'M|40|-3'),  // executive, id 30
    ]);

    $kidIds = [1, 2, 3];
    foreach ($divisions as $d) {
        $hasKid = (bool) array_intersect($kidIds, $d['members']);
        $hasOlder = (bool) array_diff($d['members'], $kidIds);
        expect($hasKid && $hasOlder)->toBeFalse("division '{$d['label']}' mixes kids with 12+");
    }
});

it('extends to a third adjacent age group on a second pass when still too small', function () {
    // Junior(2) + Adult(1) + Executive(1) purple: first pass leaves a straggler,
    // so the second pass merges all three (12+) to reach 4 — still within the band.
    $divisions = arrange([
        ...regs(10, 2, 'M|12|-3'), ...regs(20, 1, 'M|16|-3'), ...regs(30, 1, 'M|40|-3'),
    ]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['members'])->toHaveCount(4);
    expect($divisions[0]['label'])->toBe("Men's 12+ Purple Belt");
});

it('never extends across the kid / 12+ band even on the second pass', function () {
    // Pee wee(2) + junior(2): second pass must NOT merge them (different bands).
    $divisions = arrange([...regs(1, 2, 'M|09|-3'), ...regs(10, 2, 'M|12|-3')]);

    $kidIds = [1, 2];
    foreach ($divisions as $d) {
        expect((bool) array_intersect($kidIds, $d['members']) && (bool) array_diff($d['members'], $kidIds))->toBeFalse();
    }
});

it('labels a combined all-black-degree division by degree range', function () {
    // 2nd + 3rd degree black men executive, two each -> one division.
    $divisions = arrange([...regs(1, 2, 'M|40|2'), ...regs(10, 2, 'M|40|3')]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['label'])->toBe("Men's 40+ 3rd–2nd Degree Black");
});

/** Forms/kata: one combined pool, labels without a sex word. */
function arrangeForms(array $regs): array
{
    return (new DivisionArranger())->arrange(collect($regs), combineSexes: true);
}

it('forms combines male and female into one division', function () {
    // 2 boys + 2 girls, green belt, 16-39 — sparring would keep them apart, but
    // forms merges them into a single division of 4.
    $divisions = arrangeForms([...regs(1, 2, 'M|16|-4'), ...regs(10, 2, 'F|16|-4')]);

    expect($divisions)->toHaveCount(1);
    expect($divisions[0]['members'])->toHaveCount(4);
});

it('forms labels omit the sex word', function () {
    $divisions = arrangeForms(regs(1, 5, 'M|40|3'));

    expect($divisions[0]['label'])->toBe('40+ 3rd Degree Black');
});

it('sparring still splits the same set by sex', function () {
    // The same combined-would-be roster stays two divisions under sparring rules.
    $regs = [...regs(1, 3, 'M|16|-4'), ...regs(10, 3, 'F|16|-4')];

    $sparring = (new DivisionArranger())->arrange(collect($regs)); // default: split
    $forms = arrangeForms($regs);

    expect($sparring)->toHaveCount(2);
    expect($forms)->toHaveCount(1);
    expect($forms[0]['label'])->toBe('16-39 Green Belt');
});
