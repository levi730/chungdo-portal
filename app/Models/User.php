<?php

namespace App\Models;

use App\Observers\UserObserver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Sendportal\Base\Facades\Sendportal;
use Sendportal\Base\Repositories\Subscribers\MySqlSubscriberTenantRepository;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;


#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements MustVerifyEmail
{
    use Billable, HasApiTokens, HasFactory, HasRoles, Impersonate, Notifiable, TwoFactorAuthenticatable;

    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['full_name', 'age', 'height_text', 'natural_division', 'natural_division_text'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dob' => 'datetime',
        ];
    }

    public function getFullNameAttribute()
    {
        return $this->firstname.' '.$this->lastname;
    }

    public function getAgeAttribute()
    {
        if ($this->dob) {
            return $this->dob->age;
        }

        return null;
    }

    public function getAgeOnDate($date = null)
    {
        if ($this->dob) {
            if (! $date) {
                $date = new \Carbon\Carbon();
            }

            return abs(intval($date->diffInYears($this->dob)));
        }

        return null;
    }

    public function getHeightTextAttribute()
    {
        return floor($this->height / 12)."' ".($this->height % 12).'"';
    }

    public function getNaturalDivisionAttribute()
    {
        /*
         * M|40|3
         * Men's Exceutive 3rd Degree Black Belt
         * F|05|-5
         * Girl's Mini Pee-Wee Green Belt
         */

        if ($this->age >= 40) {
            $div = '40';
        } elseif ($this->age >= 16) {
            $div = '16';
        } elseif ($this->age >= 12) {
            $div = '12';
        } elseif ($this->age >= 9) {
            $div = '09';
        } elseif ($this->age >= 5) {
            $div = '05';
        } else {
            return null;
        }

        if ($this->sex != 'F' && $this->sex != 'M') {
            return null;
        }

        if ($this->rank_id < -7 || $this->rank_id == 0 || $this->rank_id == -1 || $this->rank_id > 6) {
            return null;
        }

        $arr = [$this->sex, $div, $this->rank_id];

        return implode('|', $arr);

    }

    public function getNaturalDivisionTextAttribute()
    {
        $parts = explode('|', $this->natural_division ?? '');
        if (count($parts) != 3) {
            return null;
        }
        $str = '';
        // Boys/Girls/Women/Men
        if ($parts[0] == 'F') {
            if (intval($parts[1]) > 15) {
                $str .= "Women's ";
            } else {
                $str .= 'Girls ';
            }
        } else {
            if (intval($parts[1]) > 15) {
                $str .= "Men's ";
            } else {
                $str .= 'Boys ';
            }
        }

        if ($parts[1] == '05') {
            $str .= 'Mini Pee Wee ';
        } elseif ($parts[1] == '09') {
            $str .= 'Pee Wee ';
        } elseif ($parts[1] == '12') {
            $str .= 'Junior ';
        } elseif ($parts[1] == '40') {
            $str .= 'Executive ';
        }

        $str .= $this->rank->rank;

        return $str;
    }

    public function getCanRegisterForTournamentsAttribute()
    {
        if (! $this->school || ! $this->rank || $this->height < 1 || $this->weight < 1 && $this->dob) {
            return false;
        }

        return true;
    }

    public function getAddress1Attribute()
    {
        return $this->getAttrFromParent('address1');
    }

    public function getAddress2Attribute()
    {
        return $this->getAttrFromParent('address2');
    }

    public function getCityAttribute()
    {
        return $this->getAttrFromParent('city');
    }

    public function getStateAttribute()
    {
        return $this->getAttrFromParent('state');
    }

    public function getZipAttribute()
    {
        return $this->getAttrFromParent('zip');
    }

    public function getEmailAttribute()
    {
        return $this->getAttrFromParent('email');
    }

    public function getPhoneAttribute()
    {
        return $this->getAttrFromParent('phone');
    }

    public function responsible_user(): BelongsTo
    {
        return $this->belongsTo(static::class);
    }

    public function family_members(): HasMany
    {
        return $this->hasMany(static::class, 'responsible_user_id');
    }

    /**
     * People this user may act/pay for (their dependents and, mutually, spouses).
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(static::class, 'guardianships', 'guardian_user_id', 'dependent_user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * People who may act/pay for this user.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(static::class, 'guardianships', 'dependent_user_id', 'guardian_user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * The primary guardian — source for inherited address/contact details.
     */
    public function primaryGuardian(): ?User
    {
        return $this->guardians()->wherePivot('is_primary', true)->first();
    }

    /**
     * May this user act/register/pay on behalf of $target?
     */
    public function canManage(User $target): bool
    {
        if ($this->is($target)) {
            return true;
        }

        return $this->dependents()->whereKey($target->getKey())->exists();
    }

    /**
     * Give a dependent their own login and wire up household guardianship:
     * the two adults become mutual guardians, and the new adult gains
     * co-guardianship of the inviter's other dependents (the children).
     */
    public function promoteToLoginableAdult(string $email, User $inviter): void
    {
        // Mark the email verified: only someone with access to this inbox can
        // complete the invite (they must open the emailed link to set a
        // password), so a separate verification step would be redundant.
        $this->forceFill([
            'email' => $email,
            'can_login' => 1,
            'email_verified_at' => now(),
        ])->save();

        // Mutual guardianship between the two adults. syncWithoutDetaching with
        // no pivot attributes leaves any existing is_primary edge untouched.
        $this->guardians()->syncWithoutDetaching([$inviter->getKey()]);
        $inviter->guardians()->syncWithoutDetaching([$this->getKey()]);

        // Co-guardian of the rest of the household.
        $inviter->dependents()
            ->where('users.id', '!=', $this->getKey())
            ->get()
            ->each(fn (User $child) => $child->guardians()->syncWithoutDetaching([$this->getKey()]));
    }

    /**
     * Merge two existing households after both adults have consented: the two
     * account holders become mutual guardians and each gains co-guardianship
     * of the other's minor children. Nothing is deleted or re-keyed.
     */
    public function linkAccountWith(User $other): void
    {
        // Mutual guardianship between the two adults.
        $this->guardians()->syncWithoutDetaching([$other->getKey()]);
        $other->guardians()->syncWithoutDetaching([$this->getKey()]);

        // Share each household's minor children with the other adult.
        $share = function (User $from, User $to) {
            $from->dependents()
                ->where('users.can_login', 0)
                ->where('users.id', '!=', $to->getKey())
                ->get()
                ->each(fn (User $child) => $child->guardians()->syncWithoutDetaching([$to->getKey()]));
        };

        $share($this, $other);
        $share($other, $this);
    }

    /**
     * Is this user already able to act for $other, and vice versa?
     */
    public function isLinkedWith(User $other): bool
    {
        return $this->canManage($other) && $other->canManage($this);
    }

    public function linkRequestsSent(): HasMany
    {
        return $this->hasMany(AccountLinkRequest::class, 'requester_user_id');
    }

    public function linkRequestsReceived(): HasMany
    {
        return $this->hasMany(AccountLinkRequest::class, 'recipient_user_id');
    }

    public function pendingLinkRequestsReceived()
    {
        return $this->linkRequestsReceived()
            ->where('status', AccountLinkRequest::STATUS_PENDING)
            ->with('requester');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class)->withTimestamps();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function instructor_of(): HasManyThrough
    {
        return $this->hasManyThrough(School::class, SchoolInstructor::class, 'user_id', 'id', 'id', 'school_id');
    }

    /**
     * Whether this user has any business on the school management screens —
     * either they may add schools outright, or they instruct at one and so may
     * edit its details (see SchoolPolicy). Used to decide whether the Schools
     * menu shows them a management link.
     */
    public function managesAnySchool(): bool
    {
        return $this->can('school.manage') || $this->instructor_of()->exists();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')->withPivot(['id', 'amount_due', 'amount_paid', 'payment_id', 'checkin']);
    }

    public function event_notes(): HasMany
    {
        return $this->hasMany(UserEventNote::class, 'user_id');
    }

    public function addFamilyMember($attrs)
    {
        $fam = new User($attrs);
        $fam->can_login = 0;
        $fam->is_student = 1;
        $fam->responsible_user_id = $this->id;
        $fam->save();

        return $fam;
    }

    private function getAttrFromParent($attrname)
    {

        if (in_array($attrname, array_keys($this->attributes))) {
            if ($this->attributes[$attrname]) {
                return $this->attributes[$attrname];
            } else {
                $guardian = $this->primaryGuardian() ?? $this->responsible_user;
                if ($guardian) {
                    return $guardian->{$attrname};
                }
            }
        }

        return null;
    }

    public function syncToSendportal($deleted=false) {
        $r = new MySqlSubscriberTenantRepository();

        $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $this->getOriginal('email'));


        if($sub) {
            if($deleted || !$this->mailings) {
                ///delete
                $sub->delete();
            } else {
                //update
                $sub->email = $this->email;
                $sub->first_name = $this->firstname;
                $sub->last_name = $this->lastname;
                $sub->save();
            }
        } else {
            //create
            if($this->mailings) {
                $r->store(Sendportal::currentWorkspaceId(), [
                    'email' => $this->email,
                    'first_name' => $this->firstname,
                    'last_name' => $this->lastname
                ]);
            }
        }


    }
}
