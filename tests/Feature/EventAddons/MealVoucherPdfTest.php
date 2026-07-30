<?php

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use App\Services\MealVoucherPdf;

/** An event with the meal_ticket add-on enabled ($menu is the printed menu). */
function voucherEvent(string $menu = ''): array
{
    $event = Event::create(['name' => 'Voucher Event', 'cost' => 0, 'slug' => 'voucher-event']);

    $addon = EventAddon::create([
        'event_id' => $event->id,
        'type' => 'meal_ticket',
        'enabled' => true,
        'sort_order' => 0,
        'settings' => ['price' => 10, 'label' => 'Meal', 'description' => $menu],
    ]);

    $event->load('addons');

    return [$event, $addon];
}

/** Register $user for $event and record a meal answer; returns the loaded registration. */
function registerWithMeals(Event $event, EventAddon $addon, User $user, bool $attending, int $additional): EventRegistration
{
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0, 'docs_printed' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id,
        'event_addon_id' => $addon->id,
        'type' => 'meal_ticket',
        'selected' => $attending,
        'quantity' => $additional,
        'amount' => (($attending ? 1 : 0) + $additional) * 10,
    ]);

    return $reg->load('addonAnswers', 'user');
}

it('summarizes meal totals and per-registrant lines', function () {
    [$event, $addon] = voucherEvent();
    $alice = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'A']);
    $bob = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'B']);
    $r1 = registerWithMeals($event, $addon, $alice, true, 2); // 3 meals
    $r2 = registerWithMeals($event, $addon, $bob, true, 0);   // 1 meal

    $summary = MealVoucherPdf::summarize([$r1, $r2]);

    expect($summary['total'])->toBe(4);
    expect($summary['lines'])->toHaveCount(2);
    expect($summary['lines'][0])->toBe(['name' => 'Alice A', 'meals' => 3]);
});

it('omits registrants with no meals from the summary', function () {
    [$event, $addon] = voucherEvent();
    $u = User::factory()->create();
    $r = registerWithMeals($event, $addon, $u, false, 0); // 0 meals

    $summary = MealVoucherPdf::summarize([$r]);

    expect($summary['total'])->toBe(0);
    expect($summary['lines'])->toBeEmpty();
});

it('aggregates a household (self + dependents) for the voucher', function () {
    [$event, $addon] = voucherEvent();
    $parent = User::factory()->single_student()->can_login()->create();
    $kid = User::factory()->is_kid()->belongs_to($parent)->create();

    registerWithMeals($event, $addon, $parent, true, 1); // 2 meals
    registerWithMeals($event, $addon, $kid, true, 0);    // 1 meal

    $regs = EventRegistration::where('event_id', $event->id)
        ->whereIn('user_id', collect([$parent->id])->merge($parent->dependents->pluck('id')))
        ->with(['user', 'addonAnswers'])->get();

    expect(MealVoucherPdf::summarize($regs)['total'])->toBe(3);
});

it('returns null when the household bought no meals', function () {
    [$event, $addon] = voucherEvent();
    $parent = User::factory()->single_student()->can_login()->create();
    registerWithMeals($event, $addon, $parent, false, 0);

    expect((new MealVoucherPdf())->forHousehold($event, $parent))->toBeNull();
});

it('compiles a meal-voucher PDF via the typst binary', function () {
    if (! trim(shell_exec('command -v '.escapeshellarg(config('events.typst_bin', 'typst')).' 2>/dev/null') ?? '')) {
        $this->markTestSkipped('typst binary not available');
    }

    [$event, $addon] = voucherEvent("# Entrees\n- Chicken\n- Pork\n\nIncludes a drink.");
    $parent = User::factory()->single_student()->can_login()->create();
    $kid = User::factory()->is_kid()->belongs_to($parent)->create();
    registerWithMeals($event, $addon, $parent, true, 1);
    registerWithMeals($event, $addon, $kid, true, 0);

    $pdf = (new MealVoucherPdf())->forHousehold($event, $parent);

    expect($pdf)->toBeFile();
    expect(filesize($pdf))->toBeGreaterThan(1000);
    expect(file_get_contents($pdf, false, null, 0, 5))->toBe('%PDF-');

    @unlink($pdf);
});

it('serves the voucher over the download route for a household with meals', function () {
    if (! trim(shell_exec('command -v '.escapeshellarg(config('events.typst_bin', 'typst')).' 2>/dev/null') ?? '')) {
        $this->markTestSkipped('typst binary not available');
    }

    [$event, $addon] = voucherEvent('# Entrees\n- Chicken');
    $parent = User::factory()->single_student()->can_login()->create();
    registerWithMeals($event, $addon, $parent, true, 1);

    $this->actingAs($parent)
        ->get(route('event.meal-voucher', $event->slug))
        ->assertOk()
        ->assertDownload();
});

it('404s the download route when the household has no meals', function () {
    [$event, $addon] = voucherEvent();
    $parent = User::factory()->single_student()->can_login()->create();
    registerWithMeals($event, $addon, $parent, false, 0);

    $this->actingAs($parent)
        ->get(route('event.meal-voucher', $event->slug))
        ->assertNotFound();
});
