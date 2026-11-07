<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipationTrophy extends Model
{
    protected $fillable = ['event_id', 'event_registration_id', 'given_by_user_id'];
}
