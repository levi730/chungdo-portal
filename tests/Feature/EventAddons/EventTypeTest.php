<?php

use App\Models\Event;

it('classifies competition disciplines by type', function () {
    expect((new Event(['type' => 'combined']))->hasSparring())->toBeTrue();
    expect((new Event(['type' => 'combined']))->hasForms())->toBeTrue();
    expect((new Event(['type' => 'sparring']))->hasForms())->toBeFalse();
    expect((new Event(['type' => 'forms']))->hasSparring())->toBeFalse();
    expect((new Event(['type' => 'combined']))->isCompetition())->toBeTrue();
    expect((new Event(['type' => 'social']))->isCompetition())->toBeFalse();
});

it('labels the type and falls back to the association as host', function () {
    expect((new Event(['type' => 'combined']))->typeLabel())->toBe('Combined Tournament');
    expect((new Event(['type' => null]))->typeLabel())->toBeNull();
    expect((new Event())->hostName())->toBe('Chung Do Association');
});
