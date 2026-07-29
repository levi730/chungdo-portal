<?php

namespace App\Actions\Fortify;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  mixed  $user
     * @return void
     */
    public function update($user, array $input)
    {

        session()->flash('profile_hash', 'tabs-profile');

        Validator::make($input, [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'firstname' => $input['firstname'],
                'lastname' => $input['lastname'],
                'email' => $input['email'],
                'address1' => $input['address1'],
                'address2' => $input['address2'],
                'city' => $input['city'],
                'state' => $input['state'],
                'zip' => $input['zip'],
                'is_student' => isset($input['is_student']) ? 1 : 0,
                'school_id' => $input['school_id'],
                'rank_id' => $input['rank_id'],
                'dob' => $input['dob'] ? new Carbon($input['dob']) : null,
                'height' => $input['height'],
                'weight' => $input['weight'],
                'sex' => $input['sex'],
            ])->save();

            session()->flash('tab-profile-success', 'Profile successfully updated!');
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function updateVerifiedUser($user, array $input)
    {
        session()->flash('profile_hash', 'tabs-profile');

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
