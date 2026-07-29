<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $created_at
 * @property string $updated_at
 * @property int $school_id
 * @property int $person_id
 * @property bool $principal
 */
class SchoolInstructor extends Pivot
{
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['created_at', 'updated_at', 'school_id', 'user_id', 'principal'];

    protected $table = 'school_instructors';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
