<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A division lost its scoring: its competitors changed in a new version, or the
 * version was unpublished. The row is gone, so this carries what listeners need.
 */
class DivisionCleared implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $eventId,
        public string $discipline,
        public string $divisionName,
        public ?int $zulipMessageId,
    ) {}
}
