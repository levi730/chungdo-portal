<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        Validator::make($input, [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($input) {
                if (self::looksLikeBotName((string) ($input['firstname'] ?? ''), (string) $value)) {
                    $fail('Please enter your real last name.');
                }
            }],
            // Honeypot: hidden from people by the form, filled in by bots that
            // complete every field. The reCAPTCHA alone has let roughly one
            // spam account a month through since 2024.
            'website' => ['prohibited'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'g-recaptcha-response' => 'required|captcha',
        ], [
            'website.prohibited' => 'Your registration could not be completed.',
        ], [
            'g-recaptcha-response' => 'Recaptcha',
        ])->validate();

        return User::create([
            'firstname' => $input['firstname'],
            'lastname' => $input['lastname'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * The XRumer-style signature on every spam account found so far: the last
     * name is the first name plus two or three capitals ("Georgegooxy" /
     * "GeorgegooxyVM"). No real surname is built that way.
     */
    public static function looksLikeBotName(string $firstname, string $lastname): bool
    {
        $firstname = trim($firstname);

        return $firstname !== ''
            && str_starts_with($lastname, $firstname)
            && preg_match('/^[A-Z]{2,3}$/', substr($lastname, strlen($firstname))) === 1;
    }
}
