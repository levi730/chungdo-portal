<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        $this->call(RealUserSeeder::class);

        /*$admin = User::create([
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
            'zip' => '63000'
        ]);

        $admin->save();

        $admin->addFamilyMember([
            'firstname' => 'Demo Jr.',
            'lastname' => 'Admin',
            'school_id' => 1,
            'rank_id' => -6,
            'dob' => new Carbon('2016-01-01'),
            'height' => 46,
            'weight' => 51,
            'address_same_as_resp_user' => 1
        ]);

        $this->call(FakeUserSeeder::class);*/

        $schools = [
            [
                'name' => 'Demo Martial Arts & Fitness',
                'address1' => '100 Demo Blvd',
                'city' => 'Anytown',
                'state' => 'MO',
                'zip' => '63000',
                'phone' => '5735550100',
                'email' => 'school1@example.com',
                'url' => 'https://example.com',
                'shortname' => 'Demo MA',
            ],
            [
                'name' => 'Demo Martial Arts & Fitness',
                'address1' => '200 Demo Rd.',
                'city' => 'Anytown',
                'state' => 'IL',
                'zip' => '62000',
                'phone' => '6185550200',
                'email' => 'school2@example.com',
                'url' => 'https://example.com',
                'shortname' => 'Demo MA East',
            ],
            [
                'name' => 'Demo Forever',
                'address1' => '300 Demo Rd.',
                'city' => 'Anytown',
                'state' => 'MO',
                'zip' => '63000',
                'phone' => '3145550300',
                'url' => 'https://example.com',
                'shortname' => 'Demo Forever',
            ],
            [
                'name' => 'Demo Institute',
                'address1' => '400 Demo Pkwy.',
                'city' => 'Anytown',
                'state' => 'MO',
                'zip' => '63000',
                'phone' => '6365550400',
                'email' => 'school4@example.com',
                'url' => 'https://example.com',
                'shortname' => 'Demo Institute',
            ],
            [
                'name' => 'Demo Life',
                'address1' => '500 Demo Blvd.',
                'city' => 'Anytown',
                'state' => 'MO',
                'zip' => '63000',
                'phone' => '3145550500',
                'email' => 'school5@example.com',
                'url' => 'https://example.com',
                'shortname' => 'Demo Life',
            ],
        ];

        foreach ($schools as $school) {
            $obj = new School($school);
            $obj->save();
        }

        //ranks
        $ranks = [
            [
                'id' => -7,
                'rank' => 'No Belt',
                'color' => 'e9ecef',
                'content_color' => '000000',
            ],
            [
                'id' => -6,
                'rank' => 'White Belt',
                'color' => 'ffffff',
                'content_color' => '000000',
            ],
            [
                'id' => -5,
                'rank' => 'Yellow Belt',
                'color' => 'ffc107',
                'content_color' => '000000',
            ],
            [
                'id' => -4,
                'rank' => 'Green Belt',
                'color' => '198754',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => -3,
                'rank' => 'Purple Belt',
                'color' => '6f42c1',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => -2,
                'rank' => 'Brown Belt',
                'color' => 'e9ecef',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => 1,
                'rank' => 'Black Belt',
                'color' => '000000',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => 2,
                'rank' => 'Black Belt (2nd)',
                'color' => '000000',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => 3,
                'rank' => 'Black Belt (3rd)',
                'color' => '000000',
                'content_color' => 'FFFFFF',
            ],
            [
                'id' => 4,
                'rank' => 'Black Belt (4th)',
                'color' => '000000',
                'content_color' => 'FFFFFF',
            ],
        ];

        foreach ($ranks as $rank) {
            $obj = new Rank($rank);
            $obj->save();
        }

        //Event
        $e = new Event([
            'name' => 'Winter 2022 Tournament',
            'startdatetime' => new Carbon('2022-02-05T09:00:00'),
            'location' => "First United Methodist Church\n801 1st Capitol Dr.\nSt. Charles, MO 63301",
            'details' => 'Sparring competition is back!  Free T-Shirt!  Post-tournament Potluck!',
            'slug' => 'Winter-2022-Tournament',
        ]);
        $e->save();

        // Registration fee is now the registration_fee add-on.
        \App\Models\EventAddon::create([
            'event_id' => $e->id,
            'type' => 'registration_fee',
            'enabled' => true,
            'sort_order' => 0,
            'settings' => ['cost' => 65, 'cost_type' => 'Per Person', 'discounts' => ['2' => 0, '3' => 0, '4' => 0, '5' => 0]],
        ]);

        $this->call(PermissionSeeder::class);

    }
}
