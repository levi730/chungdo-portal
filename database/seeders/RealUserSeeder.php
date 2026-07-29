<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->addAdmin();
        $this->addInstructor();

    }

    private function addAdmin()
    {
        $admin = User::where('email', '=', 'admin@example.com')->first();
        if (! $admin) {
            $admin = User::create([
                'firstname' => 'Demo',
                'lastname' => 'Admin',
                'email' => 'admin@example.com',
                'email_verified_at' => new Carbon(),
                'can_login' => 1,
                'password' => Hash::make('password'),
                'school_id' => 1,
                'rank_id' => 3,
                'dob' => new Carbon('1980-01-01'),
                'height' => 69,
                'weight' => 180,
                'last_rank_date' => new Carbon('2014-09-01'),
                'address1' => '123 Main St.',
                'city' => 'Anytown',
                'state' => 'MO',
                'zip' => '63000',
            ]);
            $admin->save();
        }

        $child = $admin->family_members()->where('firstname', '=', 'Demo Jr.')->get();
        if (! $child) {
            $child = $admin->addFamilyMember([
                'firstname' => 'Demo Jr.',
                'lastname' => 'Admin',
                'school_id' => 1,
                'rank_id' => -6,
                'dob' => new Carbon('2016-01-01'),
                'height' => 46,
                'weight' => 51,
                'address_same_as_resp_user' => 1,
            ]);
        }
    }

    private function addInstructor()
    {
        $instructor = User::where('email', '=', 'instructor@example.com')->first();
        if (! $instructor) {
            $instructor = User::create([
                'firstname' => 'Demo',
                'lastname' => 'Instructor',
                'email' => 'instructor@example.com',
                'email_verified_at' => new Carbon(),
                'can_login' => 1,
                'password' => Hash::make('password'),
                'school_id' => 1,
                'rank_id' => 2,
                'dob' => new Carbon('1982-01-01'),
                'height' => 64,
                'weight' => 150,
                'address1' => '456 Oak Ave.',
                'city' => 'Anytown',
                'state' => 'IL',
                'zip' => '62000',
            ]);
            $instructor->save();
        }
    }
}
