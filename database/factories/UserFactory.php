<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public $faker;

    public function __construct(...$args)
    {
        // Forward all constructor arguments so factory chaining (states and
        // make/create overrides, which Laravel threads through new instances)
        // is preserved. Omitting them silently drops every applied state.
        parent::__construct(...$args);
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {


        $gender = $this->faker->randomElement(['male', 'female']);
        $firstname = $this->faker->firstname($gender);
        $lastname = $this->faker->lastName();

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => preg_replace('/[^A-Za-z0-9 ]/', '', $firstname[0].$lastname.$this->faker->numberBetween(1, 9873)).'@'.$this->faker->unique(true)->safeEmailDomain(),
            'email_verified_at' => null,
            'password' => null,
            'remember_token' => Str::random(10),
            'is_student' => false,
            'school_id' => null,
            'rank_id' => null,
            'last_rank_date' => null,
            'dob' => null,
            'height' => null,
            'weight' => null,
            'responsible_user_id' => null,
            'can_login' => false,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'address_same_as_resp_user' => false,
            'sex' => strtoupper($gender[0]),
            'mailings' => true
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function can_login()
    {

        return $this->state(function (array $attributes) {

            return [
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'can_login' => 1,
            ];
        });
    }

    public function single_student()
    {
        return $this->state(function (array $attributes) {

            $dob = $this->faker->dateTimeBetween('-70 years', '-19 years', 'America/Chicago');
            $last_rank = $this->faker->dateTimeBetween((new Carbon($dob))->addYears(5), 'yesterday', 'America/Chicago');

            return [
                'school_id' => $this->faker->unique(true)->numberBetween(1, 5),
                'rank_id' => $this->faker->randomElement([-7, -6, -5, -4, -3, -2, 1, 2, 3, 4]),
                'is_student' => 1,
                'last_rank_date' => $last_rank,
                'dob' => $dob,
                'height' => $this->faker->unique(true)->numberBetween(50, 84),
                'weight' => $this->faker->unique(true)->numberBetween(103, 300),
                'mailings' => true
            ];
        });
    }

    public function doesnt_train()
    {
        return $this->state(function (array $attributes) {
            return [
                'rank_id' => null,
                'is_student' => 0,
                'last_rank_date' => null,
                'mailings' => true
            ];
        });

    }

    public function belongs_to($user = null)
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'lastname' => $user->lastname,
                'responsible_user_id' => $user->id,
                'school_id' => $user->school_id,
                'address_same_as_resp_user' => true,
            ];
        });
    }

    public function spouse_to($user = null)
    {
        return $this->state(function (array $attributes) use ($user) {
            $gender = ($user->gender == 'F' ? 'male' : 'female');
            $firstname = $this->faker->firstname($gender);
            $lastname = $user->lastname;

            return [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => preg_replace('/[^A-Za-z0-9 ]/', '', $firstname[0].$lastname.$this->faker->numberBetween(1, 9873)).'@'.$this->faker->unique(true)->safeEmailDomain(),
                'responsible_user_id' => $user->id,
                'school_id' => $user->school_id,
                'address_same_as_resp_user' => true,
                'mailings' => true
            ];
        });
    }

    public function is_kid()
    {
        return $this->state(function (array $attributes) {
            $dob = $this->faker->dateTimeBetween('-18 years', '-6 years', 'America/Chicago');
            $last_rank = $this->faker->dateTimeBetween((new Carbon($dob))->addYears(5), 'yesterday', 'America/Chicago');

            return [
                'last_rank_date' => $last_rank,
                'dob' => $dob,
                'height' => $this->faker->unique(true)->numberBetween(47, 70),
                'weight' => $this->faker->unique(true)->numberBetween(42, 120),
                'email' => null,
                'mailings' => false
            ];
        });
    }
}
