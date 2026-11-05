<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DivisionPlacement extends Model
{
    protected $fillable = ['division_result_id', 'user_id', 'place', 'name', 'school', 'rank'];

    public function result(): BelongsTo
    {
        return $this->belongsTo(DivisionResult::class, 'division_result_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The name to publish. Everything that shows a result goes through here,
     * so how competitors are named on a results page, and later in a Facebook
     * post, is decided in this one place.
     *
     * Full names for now: the first run is a demonstration for the association's
     * leaders, who have yet to settle how minors should be named. The results
     * page is behind sign-in, and anyone with a verified email can sign up.
     */
    public function displayName(): string
    {
        return $this->name;
    }
}
