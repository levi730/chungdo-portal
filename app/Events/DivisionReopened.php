<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A saved division was opened for a correction and has left the results page. */
class DivisionReopened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $resultId) {}
}
