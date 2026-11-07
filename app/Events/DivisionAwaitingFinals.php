<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A scorer saved 3rd place and left 1st and 2nd for finals. DivisionCompleted follows after finals. */
class DivisionAwaitingFinals implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $resultId) {}
}
