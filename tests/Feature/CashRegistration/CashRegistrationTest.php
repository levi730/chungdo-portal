<?php

use App\Livewire\Event\CashRegistration;
use App\Mail\CashRegistrationPending;
use App\Mail\CashRemittanceSlip;
use App\Models\CashRemittance;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Guardianship;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use App\Services\CashRegistration\CashRegistrar;
use App\Services\CashRegistration\RemittanceNotifier;
use App\Services\CashRegistration\RemittanceReceiver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Mail::fake();
});

function cashEvent(array $attrs = []): Event
{
    $event = Event::create(array_merge([
        'name' => 'Cash Open',
        'slug' => 'cash-open-'.uniqid(),
        'startdatetime' => now()->addMonth(),
        'stripe_account' => 'association',
    ], $attrs));

    // $50, $10 off the second person in a family.
    EventAddon::create(['event_id' => $event->id, 'type' => 'registration_fee', 'enabled' => true, 'sort_order' => 0,
        'settings' => ['cost' => 50, 'cost_type' => 'per person', 'discounts' => ['2' => 10, '3' => 0, '4' => 0, '5' => 0]]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'tshirt', 'enabled' => true, 'sort_order' => 1, 'settings' => []]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 2, 'settings' => ['price' => 12]]);

    return $event->fresh();
}

function cashInstructor(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $school = School::create(['name' => 'Cash Dojang', 'shortname' => 'CD']);
    SchoolInstructor::create(['school_id' => $school->id, 'user_id' => $user->id, 'principal' => false]);

    return $user;
}

function cashReceiver(): User
{
    Permission::findOrCreate('payments.receive', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('payments.receive');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function siblings(): array
{
    $parent = User::factory()->create();
    $kids = User::factory()->count(2)->create();
    foreach ($kids as $kid) {
        Guardianship::create(['guardian_user_id' => $parent->id, 'dependent_user_id' => $kid->id, 'is_primary' => true]);
    }

    return [$parent, $kids[0], $kids[1]];
}

it('registers the batch unpaid and records what the instructor owes', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $paymentsBefore = Payment::count();

    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [
        $a->id => ['tshirt' => 'M', 'meal' => ['attending' => true, 'additional' => 1]],
        $b->id => [],
    ]);

    // Unrelated students each pay the full fee; $a adds two meals.
    expect((float) $r->amount_due)->toBe(50.0 + 24.0 + 50.0)
        ->and($r->status)->toBe(CashRemittance::STATUS_OUTSTANDING)
        ->and($r->reference)->toBe(CashRemittance::referenceFor($r->id))
        ->and($r->payee)->toBe('Chung Do Association')
        ->and($r->responsible_user_id)->toBe($instructor->id);

    $regs = EventRegistration::where('event_id', $event->id)->get();
    expect($regs)->toHaveCount(2)
        ->and($regs->every(fn ($reg) => (float) $reg->amount_paid === 0.0 && $reg->payment_id === null && $reg->cash_remittance_id === $r->id))->toBeTrue()
        ->and($regs->firstWhere('user_id', $a->id)->load('addonAnswers')->tshirtSize())->toBe('M')
        ->and(Payment::count())->toBe($paymentsBefore); // nothing recorded until received
});

it('applies the family discount within a household, not across the batch', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    [, $kid1, $kid2] = siblings();
    $stranger = User::factory()->create();

    $quote = app(CashRegistrar::class)->quote($event, [$stranger->id => [], $kid1->id => [], $kid2->id => []]);
    $amounts = collect($quote['students'])->pluck('amount', 'user_id');

    expect($amounts[$stranger->id])->toBe(50.0)
        ->and($amounts[$kid1->id])->toBe(50.0)
        ->and($amounts[$kid2->id])->toBe(40.0)
        ->and($quote['total'])->toBe(140.0);
});

it('refuses a batch containing someone already registered, and writes nothing', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $new = User::factory()->create();
    $existing = User::factory()->create();
    $event->registrations()->attach($existing->id, ['amount_due' => 50, 'amount_paid' => 50]);

    expect(fn () => app(CashRegistrar::class)->submit($event, $instructor, $instructor, [$new->id => [], $existing->id => []]))
        ->toThrow(ValidationException::class);

    expect(CashRemittance::where('event_id', $event->id)->count())->toBe(0)
        ->and(EventRegistration::where('event_id', $event->id)->where('user_id', $new->id)->exists())->toBeFalse();
});

it('makes checks payable to whoever owns the event\'s stripe account', function () {
    // An account only counts as configured with a secret; never a real one here.
    config([
        'services.stripe.accounts.main_school.secret' => 'sk_test_placeholder',
        'services.stripe.accounts.main_school.payee' => 'Blue Wave Main School',
    ]);
    $instructor = cashInstructor();

    $school = app(CashRegistrar::class)->submit(cashEvent(['stripe_account' => 'main_school']), $instructor, $instructor, [User::factory()->create()->id => []]);
    $association = app(CashRegistrar::class)->submit(cashEvent(), $instructor, $instructor, [User::factory()->create()->id => []]);

    expect($school->payee)->toBe('Blue Wave Main School')
        ->and($school->stripe_account)->toBe('main_school')
        ->and($association->payee)->toBe('Chung Do Association');
});

it('emails each household its own students, and the instructor the slip', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    [$parent, $kid1, $kid2] = siblings();
    $solo = User::factory()->create();

    app(CashRegistrar::class)->submit($event, $instructor, $instructor, [$kid1->id => [], $kid2->id => [], $solo->id => []]);

    Mail::assertSent(CashRegistrationPending::class, fn ($m) => $m->hasTo($parent->email) && count($m->students) === 2 && $m->amount() === 90.0);
    Mail::assertSent(CashRegistrationPending::class, fn ($m) => $m->hasTo($solo->email) && count($m->students) === 1);
    Mail::assertSent(CashRegistrationPending::class, 2);
    Mail::assertSent(CashRemittanceSlip::class, fn ($m) => $m->hasTo($instructor->email));
});

it('records the deposit as a payment and marks each registration paid', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [
        $a->id => ['meal' => ['attending' => true]],
        $b->id => [],
    ]);

    $r = app(RemittanceReceiver::class)->receive($r, cashReceiver(), 112.0, 'check', '1041');

    $payment = Payment::find($r->payment_id);
    expect($r->status)->toBe(CashRemittance::STATUS_RECEIVED)
        ->and($r->balance())->toBe(0.0)
        ->and($payment->method)->toBe('check')
        ->and((float) $payment->amount_paid)->toBe(112.0)
        ->and($payment->user_id)->toBe($instructor->id)
        ->and($payment->stripe_payment_intent_id)->toBeNull();

    $regs = EventRegistration::where('cash_remittance_id', $r->id)->get()->keyBy('user_id');
    expect((float) $regs[$a->id]->amount_paid)->toBe(62.0)
        ->and((float) $regs[$b->id]->amount_paid)->toBe(50.0)
        ->and($regs->every(fn ($reg) => $reg->payment_id === $payment->id))->toBeTrue()
        ->and(EventRegistrationAddon::where('type', 'meal_ticket')->where('event_registration_id', $regs[$a->id]->id)->value('payment_id'))->toBe($payment->id);
});

it('leaves the last students unpaid when the envelope is short', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [$a->id => [], $b->id => []]);

    $r = app(RemittanceReceiver::class)->receive($r, cashReceiver(), 70.0, 'cash');

    $regs = EventRegistration::where('cash_remittance_id', $r->id)->get()->keyBy('user_id');
    expect($r->balance())->toBe(30.0)
        ->and((float) $regs[$a->id]->amount_paid)->toBe(50.0)
        ->and((float) $regs[$b->id]->amount_paid)->toBe(20.0);
});

it('receives a remittance only once', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [User::factory()->create()->id => []]);
    $receiver = cashReceiver();

    app(RemittanceReceiver::class)->receive($r, $receiver, 50, 'cash');

    expect(fn () => app(RemittanceReceiver::class)->receive($r, $receiver, 50, 'cash'))->toThrow(ValidationException::class);
    expect(Payment::where('method', 'cash')->where('user_id', $instructor->id)->count())->toBe(1);
});

it('voids an outstanding remittance by archiving and removing its registrations', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $a = User::factory()->create();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [$a->id => ['tshirt' => 'L']]);

    $r = app(RemittanceReceiver::class)->void($r, $instructor, 'Wrong event');

    expect($r->status)->toBe(CashRemittance::STATUS_VOID)
        ->and(EventRegistration::where('cash_remittance_id', $r->id)->exists())->toBeFalse()
        ->and(DB::table('event_registrations_deleted')->where('cash_remittance_id', $r->id)->where('user_id', $a->id)->exists())->toBeTrue();
});

it('will not void a remittance once the money is in', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [User::factory()->create()->id => []]);
    app(RemittanceReceiver::class)->receive($r, cashReceiver(), 50, 'cash');

    expect(fn () => app(RemittanceReceiver::class)->void($r, $instructor, 'oops'))->toThrow(ValidationException::class);
});

it('lets instructors open the form and turns away everyone else', function () {
    $event = cashEvent();

    $this->actingAs(cashInstructor())->get(route('cash.register', $event->slug))
        ->assertOk()
        // Livewire's own styles hide wire:loading spinners until a request runs.
        ->assertSee('[wire\\:loading', false);

    $member = User::factory()->create();
    $member->markEmailAsVerified();
    $this->actingAs($member)->get(route('cash.register', $event->slug))->assertForbidden();
});

it('only lets payment receivers mark a remittance received', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [User::factory()->create()->id => []]);

    $this->actingAs($instructor)
        ->post(route('cash.receive', $r), ['amount' => 50, 'method' => 'cash'])
        ->assertForbidden();

    $this->actingAs(cashReceiver())
        ->post(route('cash.receive', $r), ['amount' => 50, 'method' => 'cash'])
        ->assertRedirect();

    expect($r->fresh()->status)->toBe(CashRemittance::STATUS_RECEIVED);
});

it('shows an instructor only their own remittances', function () {
    $event = cashEvent();
    $mine = cashInstructor();
    $theirs = cashInstructor();
    $r1 = app(CashRegistrar::class)->submit($event, $mine, $mine, [User::factory()->create()->id => []]);
    $r2 = app(CashRegistrar::class)->submit($event, $theirs, $theirs, [User::factory()->create()->id => []]);

    $this->actingAs($mine)->get(route('cash.remittances'))
        ->assertSee($r1->reference)
        ->assertDontSee($r2->reference);

    $this->actingAs($mine)->get(route('cash.slip', $r2))->assertForbidden();
});

it('registers through the form, with the instructor on the hook', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $student = User::factory()->create(['firstname' => 'Zelda', 'lastname' => 'Quintero']);

    Livewire::actingAs($instructor)
        ->test(CashRegistration::class, ['eventId' => $event->id])
        ->set('search', 'Quintero')
        ->assertSee('Zelda')
        ->call('add', $student->id)
        ->set("students.{$student->id}.tshirt", 'S')
        ->assertSee('$50.00')
        ->call('submit')
        ->assertHasErrors('confirmed')
        ->set('confirmed', true)
        ->set('responsibleId', User::factory()->create()->id) // ignored: instructors cannot name someone else
        ->call('submit')
        ->assertRedirect();

    $r = CashRemittance::where('event_id', $event->id)->sole();
    expect($r->responsible_user_id)->toBe($instructor->id)
        ->and((float) $r->amount_due)->toBe(50.0);
});

it('blocks add-on changes while the cash is still with the instructor', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $student = User::factory()->create();
    $student->markEmailAsVerified();
    app(CashRegistrar::class)->submit($event, $instructor, $instructor, [$student->id => []]);

    $this->actingAs($student)
        ->postJson(route('event.register-adjust', $event->slug), ['user_id' => $student->id])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'cash/check'));
});

it('puts cash in the financials ledger, separated from stripe', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $r = app(CashRegistrar::class)->submit($event, $instructor, $instructor, [User::factory()->create()->id => []]);
    app(RemittanceReceiver::class)->receive($r, cashReceiver(), 50, 'cash');

    $html = (new \App\Exports\Event\FinancialsExport($event))->view()->render();

    expect($html)->toContain('Registration (cash)')
        ->toContain($r->reference)
        ->toContain('Via cash/check');
});

it('reports remittances still outstanding after their event, and nothing else', function () {
    $instructor = cashInstructor();
    $past = cashEvent(['startdatetime' => now()->subWeek()]);
    $future = cashEvent();
    $overdue = app(CashRegistrar::class)->submit($past, $instructor, $instructor, [User::factory()->create()->id => []]);
    app(CashRegistrar::class)->submit($future, $instructor, $instructor, [User::factory()->create()->id => []]);
    $settled = app(CashRegistrar::class)->submit($past, $instructor, $instructor, [User::factory()->create()->id => []]);
    app(RemittanceReceiver::class)->receive($settled, cashReceiver(), 50, 'cash');

    $ids = app(RemittanceNotifier::class)->overdue()->pluck('id');

    expect($ids)->toContain($overdue->id)
        ->not->toContain($settled->id)
        ->and($ids->count())->toBe(CashRemittance::outstanding()->whereHas('event', fn ($q) => $q->where('startdatetime', '<', now()->subDays(3)))->count());
});

it('warns about an incomplete profile but still lets the instructor add the student', function () {
    $event = cashEvent();
    $instructor = cashInstructor();
    $student = User::factory()->create(['firstname' => 'Wren', 'lastname' => 'Okonkwo', 'sex' => null]);

    Livewire::actingAs($instructor)
        ->test(CashRegistration::class, ['eventId' => $event->id])
        ->set('search', 'Okonkwo')
        ->assertSee('Incomplete profile')
        ->call('add', $student->id)
        ->assertSet("students.{$student->id}.participation", \App\EventAddons\EventParticipationAddon::BOTH)
        ->assertSee('collect it at check-in')
        ->assertSee('sex');
});
