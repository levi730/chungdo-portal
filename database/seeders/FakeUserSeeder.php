<?php

namespace Database\Seeders;

use App\Models\EventRegistration;
use Illuminate\Database\Seeder;

class FakeUserSeeder extends Seeder
{
    public $num = 100;

    private $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();

    }

    public function run(): void
    {
        $situations = [
            'single_student',
            'whole_family_trains',
            'parent_not_training',
        ];

        for ($i = 0; $i < $this->num; $i++) {
            $sit = $this->faker->randomElement($situations);

            switch ($sit) {
                case 'single_student':
                    $user = \App\Models\User::factory()->single_student()->can_login()->create();
                    if ($this->faker->unique(true)->numberBetween(1, 10) != 10) {
                        $this->registerForTournament($user);
                    }
                    break;

                case 'whole_family_trains':
                    $parent = \App\Models\User::factory()->single_student()->can_login()->create();
                    if ($this->faker->unique(true)->numberBetween(1, 10) != 10) {
                        $this->registerForTournament($parent);
                    }

                    $incl_spouse = $this->faker->unique(true)->numberBetween(0, 1);
                    if ($incl_spouse) {
                        $spouse = \App\Models\User::factory()->single_student()->spouse_to($parent)->create();
                        if ($this->faker->unique(true)->numberBetween(1, 10) != 10) {
                            $this->registerForTournament($spouse);
                        }
                    }

                    $num_kids = $this->faker->biasedNumberBetween(1, 5, ['\Faker\Provider\Biased', 'linearLow']);
                    for ($x = 0; $x < $num_kids; $x++) {
                        $kid = \App\Models\User::factory()->single_student()->belongs_to($parent)->is_kid()->create();
                        if ($this->faker->unique(true)->numberBetween(1, 10) != 10) {
                            $this->registerForTournament($kid);
                        }
                    }

                    break;

                case 'parent_not_training':
                    $parent = \App\Models\User::factory()->single_student()->can_login()->doesnt_train()->create();
                    $num_kids = $this->faker->unique(true)->numberBetween(1, 5);

                    for ($x = 0; $x < $num_kids; $x++) {
                        $kid = \App\Models\User::factory()->single_student()->belongs_to($parent)->is_kid()->create();
                        if ($this->faker->unique(true)->numberBetween(1, 10) != 10) {
                            $this->registerForTournament($kid);
                        }
                    }

                    break;
            }
        }
    }

    private function registerForTournament($user)
    {
        $er = new EventRegistration([
            'user_id' => $user->id,
            'event_id' => 1,
            'amount_due' => 65,
            'amount_paid' => 65,
            'payment_id' => 1,
        ]);
        $er->save();

        // T-shirt size is now an add-on answer.
        \App\Models\EventRegistrationAddon::create([
            'event_registration_id' => $er->id,
            'event_addon_id' => \App\Models\EventAddon::where('event_id', 1)->where('type', 'tshirt')->value('id') ?? 0,
            'type' => 'tshirt',
            'selected' => true,
            'value' => $this->faker->randomElement(['S', 'M', 'L', 'XL', '2XL']),
        ]);
    }
}
