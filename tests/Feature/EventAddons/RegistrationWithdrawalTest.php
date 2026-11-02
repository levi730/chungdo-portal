<?php

use App\Models\AddonChangeRequest;
use App\Models\CashRemittance;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\PotluckOptions;
use App\Models\Rank;
use App\Models\Refund;
use App\Models\School;
use App\Models\User;
use App\Services\RegistrationWithdrawal;
use App\Services\Stripe\StripeAccounts;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * An admin withdraws a registrant and refunds them, with no request from the
 * member — the Refund button on the registrants list. The member-initiated
 * flow (RefundApproverTest) can only trim add-ons; this one takes the whole
 * registration, fee included, and must leave the financial records whole.
 */
function wdEvent(): Event
{
    $event = Event::create(['name' => 'Withdraw Test', 'slug' => 'withdraw-'.uniqid(), 'cost' => 65]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'registration_fee', 'enabled' => true, 'sort_order' => 0, 'settings' => ['cost' => 65]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 1, 'settings' => ['price' => 12]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'donation', 'enabled' => true, 'sort_order' => 2, 'settings' => []]);

    return $event->load('addons');
}

/** A member with the school and rank the registrants page expects to print. */
function wdMember(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $school = School::firstOrCreate(['shortname' => 'WDRW'], ['name' => 'Withdraw School', 'city' => 'St. Charles', 'state' => 'MO']);
    if (! Rank::exists()) {
        Rank::forceCreate(['id' => 9902, 'rank' => 'Withdraw Belt', 'color' => 'white', 'content_color' => 'black']);
    }
    DB::table('users')->where('id', $user->id)->update([
        'school_id' => $school->id,
        'rank_id' => Rank::first()->id,
        'sex' => 'M',
        'dob' => '2011-02-03',
    ]);

    return $user->fresh();
}

/**
 * Register $user with the given money lines, each [type, amount, payment].
 * A null payment on a line means it inherits the registration's.
 */
function wdRegister(Event $event, User $user, ?Payment $payment, array $lines, array $pivot = []): EventRegistration
{
    $event->registrations()->attach($user->id, array_merge([
        'amount_due' => 65,
        'amount_paid' => $payment?->amount_paid ?? 0,
        'payment_id' => $payment?->id,
        'registering_user_id' => $user->id,
        'docs_printed' => 0,
    ], $pivot));
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    foreach ($lines as [$type, $amount, $linePayment]) {
        EventRegistrationAddon::create([
            'event_registration_id' => $reg->id,
            'event_addon_id' => $event->addon($type)->id,
            'type' => $type,
            'selected' => true,
            'amount' => $amount,
            'payment_id' => ($linePayment ?? $payment)?->id,
        ]);
    }

    return $reg;
}

/** $65 fee + $12 meal + $20 household donation, all on one $97 Stripe payment. */
function wdPaidRegistration(Event $event, ?User $user = null): array
{
    $user ??= wdMember();
    $payment = Payment::create(['user_id' => $user->id, 'amount_paid' => 97, 'stripe_payment_intent_id' => 'pi_wd_'.uniqid()]);
    $reg = wdRegister($event, $user, $payment, [
        ['registration_fee', 65, null],
        ['meal_ticket', 12, null],
        ['donation', 20, null],
    ]);

    return [$reg, $payment, $user];
}

/** A withdrawal service whose Stripe calls are recorded in $calls instead of made. */
function wdService(array &$calls, ?\Throwable $fail = null): RegistrationWithdrawal
{
    return new RegistrationWithdrawal(function ($pi, $cents, $account) use (&$calls, $fail) {
        if ($fail) {
            throw $fail;
        }
        $calls[] = [$pi, $cents, $account];

        return 're_wd_'.count($calls);
    });
}

function wdRefunder(): User
{
    $user = wdMember();
    $user->givePermissionTo(
        Permission::findOrCreate('event.issueRefunds', 'web'),
        Permission::findOrCreate('event.viewAllSchoolRegistrants', 'web'),
    );
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('suggests the person\'s own lines and allows the household\'s, up to the payment', function () {
    $event = wdEvent();
    [$reg] = wdPaidRegistration($event);

    $summary = app(RegistrationWithdrawal::class)->summary($reg);

    expect(collect($summary['lines'])->pluck('amount', 'type')->all())
        ->toBe(['registration_fee' => 65.0, 'meal_ticket' => 12.0, 'donation' => 20.0])
        ->and(collect($summary['lines'])->firstWhere('type', 'donation')['group'])->toBeTrue()
        ->and($summary['suggested'])->toBe(77.0)   // fee + meal: the donation is the household's
        ->and($summary['max'])->toBe(97.0)
        ->and($summary['methods'])->toBe(['stripe'])
        ->and($summary['awaiting_cash'])->toBeFalse();
});

it('estimates the fee for a registration that never stored one', function () {
    $event = wdEvent();
    $a = wdMember();
    $b = wdMember();
    // Two siblings on one $120 payment, each with a $5 meal and no fee line:
    // the fee is the $110 remainder split evenly.
    $payment = Payment::create(['user_id' => $a->id, 'amount_paid' => 120, 'stripe_payment_intent_id' => 'pi_wd_legacy']);
    $regA = wdRegister($event, $a, $payment, [['meal_ticket', 5, null]]);
    wdRegister($event, $b, $payment, [['meal_ticket', 5, null]]);

    $summary = app(RegistrationWithdrawal::class)->summary($regA);
    $fee = collect($summary['lines'])->firstWhere('type', 'registration_fee');

    expect($fee['amount'])->toBe(55.0)
        ->and($fee['estimated'])->toBeTrue()
        ->and($summary['suggested'])->toBe(60.0);
});

it('refunds on the event\'s Stripe account, records the refund, and archives the registration with its lines', function () {
    $event = wdEvent();
    [$reg, $payment, $user] = wdPaidRegistration($event);
    $admin = wdMember();
    $calls = [];

    $refunds = wdService($calls)->withdraw($reg, 77.0, $admin, 'Injured');

    expect($calls)->toBe([[$payment->stripe_payment_intent_id, 7700, app(StripeAccounts::class)->forEvent($event)]]);

    expect($refunds)->toHaveCount(1);
    $refund = Refund::find($refunds[0]->id);
    expect((float) $refund->amount)->toBe(77.0)
        ->and($refund->breakdown)->toEqual(['registration_fee' => 65, 'meal_ticket' => 12])
        ->and($refund->person_id)->toBe($user->id)
        ->and($refund->refunded_to_user_id)->toBe($user->id)
        ->and($refund->payment_id)->toBe($payment->id)
        ->and($refund->stripe_payment_intent_id)->toBe($payment->stripe_payment_intent_id)
        ->and($refund->stripe_refund_id)->toBe('re_wd_1')
        ->and($refund->decided_by_user_id)->toBe($admin->id)
        ->and($refund->admin_note)->toBe('Injured')
        ->and($refund->addon_change_request_id)->toBeNull();

    // Gone from the event, kept in the archive — lines and all.
    expect(EventRegistration::find($reg->id))->toBeNull()
        ->and(EventRegistrationAddon::where('event_registration_id', $reg->id)->count())->toBe(0);

    $archived = DB::table('event_registrations_deleted')->where('registration_id', $reg->id)->first();
    expect($archived)->not->toBeNull()
        ->and($archived->user_id)->toBe($user->id)
        ->and($archived->payment_id)->toBe($payment->id)
        ->and($archived->deleted_by_user_id)->toBe($admin->id)
        ->and($archived->reason)->toBe('withdrawn')
        ->and(collect(json_decode($archived->addon_answers, true))->pluck('amount', 'type')->map(fn ($a) => (float) $a)->all())
        ->toBe(['registration_fee' => 65.0, 'meal_ticket' => 12.0, 'donation' => 20.0]);
});

it('takes a shortfall off the registration fee first', function () {
    $event = wdEvent();
    [$reg, $payment] = wdPaidRegistration($event);
    $calls = [];

    wdService($calls)->withdraw($reg, 70.0, wdMember());

    expect($calls[0][1])->toBe(7000)
        ->and(Refund::where('event_registration_id', $reg->id)->first()->breakdown)
        ->toEqual(['registration_fee' => 58, 'meal_ticket' => 12]);
});

it('refunds a top-up on the payment that charged it', function () {
    $event = wdEvent();
    $user = wdMember();
    $original = Payment::create(['user_id' => $user->id, 'amount_paid' => 65, 'stripe_payment_intent_id' => 'pi_wd_orig']);
    $topUp = Payment::create(['user_id' => $user->id, 'amount_paid' => 12, 'stripe_payment_intent_id' => 'pi_wd_topup']);
    $reg = wdRegister($event, $user, $original, [
        ['registration_fee', 65, null],
        ['meal_ticket', 12, $topUp],
    ]);
    $calls = [];

    $refunds = wdService($calls)->withdraw($reg, 77.0, wdMember());

    expect(array_map(fn ($c) => [$c[0], $c[1]], $calls))->toBe([['pi_wd_orig', 6500], ['pi_wd_topup', 1200]])
        ->and($refunds)->toHaveCount(2)
        ->and(collect($refunds)->pluck('breakdown')->all())
        ->toEqual([['registration_fee' => 65], ['meal_ticket' => 12]]);
});

it('caps the refund at what the payment can still return', function () {
    $event = wdEvent();
    [$reg, $payment, $user] = wdPaidRegistration($event);
    // $60 already went back on this payment (an approved add-on request, say).
    Refund::create(['event_id' => $event->id, 'event_registration_id' => $reg->id, 'person_id' => $user->id, 'payment_id' => $payment->id, 'amount' => 60]);

    $summary = app(RegistrationWithdrawal::class)->summary($reg);
    expect($summary['max'])->toBe(37.0)
        ->and($summary['suggested'])->toBe(37.0);

    $calls = [];
    expect(fn () => wdService($calls)->withdraw($reg, 50.0, wdMember()))->toThrow(ValidationException::class);
    expect($calls)->toBe([])
        ->and(EventRegistration::find($reg->id))->not->toBeNull();
});

it('records a cash refund without touching Stripe', function () {
    $event = wdEvent();
    $user = wdMember();
    $payment = Payment::create(['user_id' => $user->id, 'amount_paid' => 65, 'method' => 'cash']);
    $reg = wdRegister($event, $user, $payment, [['registration_fee', 65, null]]);
    $calls = [];

    $summary = app(RegistrationWithdrawal::class)->summary($reg);
    expect($summary['methods'])->toBe(['cash']);

    $refunds = wdService($calls)->withdraw($reg, 65.0, wdMember());

    expect($calls)->toBe([])
        ->and($refunds)->toHaveCount(1)
        ->and($refunds[0]->stripe_refund_id)->toBeNull()
        ->and((float) $refunds[0]->amount)->toBe(65.0)
        ->and(EventRegistration::find($reg->id))->toBeNull();
});

it('refuses while the cash envelope is still with the instructor', function () {
    $event = wdEvent();
    $user = wdMember();
    $remittance = CashRemittance::create([
        'event_id' => $event->id,
        'responsible_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'stripe_account' => 'association',
        'payee' => 'Chung Do Association',
        'amount_due' => 65,
        'status' => CashRemittance::STATUS_OUTSTANDING,
        'payload' => [],
    ]);
    $remittance->update(['reference' => 'CR-'.$remittance->id]);
    $reg = wdRegister($event, $user, null, [['registration_fee', 65, null]], ['cash_remittance_id' => $remittance->id]);
    $calls = [];

    expect(app(RegistrationWithdrawal::class)->summary($reg)['awaiting_cash'])->toBeTrue();
    expect(fn () => wdService($calls)->withdraw($reg, 0.0, wdMember()))->toThrow(ValidationException::class);
    expect(EventRegistration::find($reg->id))->not->toBeNull();
});

it('withdraws a free registration with nothing to refund', function () {
    $event = wdEvent();
    $user = wdMember();
    $reg = wdRegister($event, $user, null, []);
    $calls = [];

    $refunds = wdService($calls)->withdraw($reg, 0.0, wdMember());

    expect($refunds)->toBe([])
        ->and($calls)->toBe([])
        ->and(Refund::where('event_registration_id', $reg->id)->exists())->toBeFalse()
        ->and(EventRegistration::find($reg->id))->toBeNull()
        ->and(DB::table('event_registrations_deleted')->where('registration_id', $reg->id)->exists())->toBeTrue();
});

it('leaves everything in place when Stripe refuses', function () {
    $event = wdEvent();
    [$reg] = wdPaidRegistration($event);
    $calls = [];

    expect(fn () => wdService($calls, new RuntimeException('card account closed'))->withdraw($reg, 77.0, wdMember()))
        ->toThrow(RuntimeException::class);

    expect(EventRegistration::find($reg->id))->not->toBeNull()
        ->and(EventRegistrationAddon::where('event_registration_id', $reg->id)->count())->toBe(3)
        ->and(Refund::where('event_registration_id', $reg->id)->exists())->toBeFalse()
        ->and(DB::table('event_registrations_deleted')->where('registration_id', $reg->id)->exists())->toBeFalse();
});

it('supersedes a pending refund request and gives the potluck slot back', function () {
    $event = wdEvent();
    EventAddon::create(['event_id' => $event->id, 'type' => 'potluck', 'enabled' => true, 'sort_order' => 3, 'settings' => []]);
    $event->load('addons');
    [$reg, $payment, $user] = wdPaidRegistration($event);

    $dish = PotluckOptions::forceCreate(['event_id' => $event->id, 'category' => 'Sides', 'item' => 'Kimchi', 'limit' => 2, 'current_count' => 1]);
    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id, 'event_addon_id' => $event->addon('potluck')->id,
        'type' => 'potluck', 'selected' => true, 'data' => ['potluck_item_id' => $dish->id],
    ]);
    $request = AddonChangeRequest::create([
        'event_id' => $event->id, 'event_registration_id' => $reg->id, 'requested_by_user_id' => $user->id,
        'new_state' => [], 'refund_amount' => 12, 'stripe_payment_intent_id' => $payment->stripe_payment_intent_id, 'status' => 'pending',
    ]);
    $calls = [];

    wdService($calls)->withdraw($reg, 77.0, wdMember());

    expect($request->fresh()->status)->toBe(AddonChangeRequest::STATUS_SUPERSEDED)
        ->and($dish->fresh()->current_count)->toBe(0);
});

it('keeps the charge in the financials ledger after a withdrawal', function () {
    $event = wdEvent();
    [$reg, $payment, $user] = wdPaidRegistration($event);
    $calls = [];
    wdService($calls)->withdraw($reg, 77.0, wdMember());

    $html = (new \App\Exports\Event\FinancialsExport($event))->view()->render();

    // The $97 charge row still names the registrant and itemizes the meal and
    // donation; the refund row is its negative. Net: 97 - 77 = 20.
    expect(substr_count($html, trim($user->firstname.' '.$user->lastname)))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('<td>97</td>')
        ->toContain('<td>-77</td>')
        ->toContain('<b>20</b>')
        ->toContain('re_wd_1');
});

it('hides the button and refuses the route from an event admin', function () {
    $this->seed(PermissionSeeder::class);
    $event = wdEvent();
    [$reg] = wdPaidRegistration($event);
    $admin = wdMember();
    $admin->assignRole('event.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->get(route('event.registrants', $event->slug))
        ->assertOk()
        ->assertDontSee('withdraw'.$reg->id);

    $this->actingAs($admin)
        ->post(route('event.registrant-withdraw', [$event->slug, $reg->id]), ['amount' => 77])
        ->assertForbidden();

    expect(EventRegistration::find($reg->id))->not->toBeNull();
});

it('lets a holder refund from the registrants page', function () {
    $event = wdEvent();
    [$reg, $payment, $user] = wdPaidRegistration($event);
    $refunder = wdRefunder();
    $calls = [];
    app()->instance(RegistrationWithdrawal::class, wdService($calls));

    $this->actingAs($refunder)
        ->get(route('event.registrants', $event->slug))
        ->assertOk()
        ->assertSee('withdraw'.$reg->id)
        ->assertSee('value="77.00"', false);

    $this->actingAs($refunder)
        ->post(route('event.registrant-withdraw', [$event->slug, $reg->id]), ['amount' => 77, 'note' => 'Moved away'])
        ->assertRedirect(route('event.registrants', $event->slug))
        ->assertSessionHas('success');

    expect($calls)->toHaveCount(1)
        ->and(EventRegistration::find($reg->id))->toBeNull()
        ->and(Refund::where('event_registration_id', $reg->id)->value('admin_note'))->toBe('Moved away');
});

it('reports a refused withdrawal instead of failing the page', function () {
    $event = wdEvent();
    [$reg] = wdPaidRegistration($event);
    $refunder = wdRefunder();
    $calls = [];
    app()->instance(RegistrationWithdrawal::class, wdService($calls, new RuntimeException('charge already refunded')));

    $this->actingAs($refunder)
        ->from(route('event.registrants', $event->slug))
        ->post(route('event.registrant-withdraw', [$event->slug, $reg->id]), ['amount' => 77])
        ->assertRedirect(route('event.registrants', $event->slug))
        ->assertSessionHas('error');

    expect(EventRegistration::find($reg->id))->not->toBeNull();
});

it('is on the coordinator\'s allowlist and in the seeder, but held by no role', function () {
    $this->seed(PermissionSeeder::class);

    expect(config('portal.coordinator_permissions'))->toContain('event.issueRefunds')
        ->and(Permission::where('name', 'event.issueRefunds')->exists())->toBeTrue()
        ->and(\Spatie\Permission\Models\Role::with('permissions')->get()
            ->filter(fn ($r) => $r->permissions->contains('name', 'event.issueRefunds'))->pluck('name')->all())
        ->toBe([]);
});
