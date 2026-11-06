<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A scorer saved a division. Corrections fire this again. */
class DivisionCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $resultId) {}
}
