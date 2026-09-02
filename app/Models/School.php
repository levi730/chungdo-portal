<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $created_at
 * @property string $updated_at
 * @property string $name
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string $phone
 * @property string $email
 * @property string $url
 */
class School extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['created_at', 'updated_at', 'name', 'shortname', 'address1', 'address2', 'city', 'state', 'zip', 'phone', 'email', 'url'];

    protected $appends = ['principal_instructors_text', 'principal_instructors_rank_text'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * One photo per school — a storefront, a class, a group shot. singleFile()
     * so uploading a new one replaces the old rather than accumulating; a
     * directory card shows exactly one.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    /** The school's photo, or null. */
    public function photo(): ?Media
    {
        return $this->getMedia('photo')->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function instructors(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, SchoolInstructor::class, 'school_id', 'id', 'id', 'user_id');
    }

    public function getPrincipalInstructorsTextAttribute()
    {

        $instructors = $this->instructors()->where('principal', '=', 1)->orderBy('users.rank_id', 'desc')->orderBy('users.lastname')->get();
        $arr = [];
        foreach ($instructors as $ins) {
            $arr[] = $ins->fullname;
        }

        return implode('/', $arr);
    }

    public function getPrincipalInstructorsRankTextAttribute()
    {

        $instructors = $this->instructors()->where('principal', '=', 1)->orderBy('users.rank_id', 'desc')->orderBy('users.lastname')->get();
        $arr = [];
        foreach ($instructors as $ins) {
            $arr[] = $ins->rank->rank;
        }

        return implode('/', $arr);
    }
}
