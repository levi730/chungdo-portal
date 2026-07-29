<?php

namespace App\Exports\Event;

use App\EventAddons\AddonRegistry;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Models\Event;

/**
 * A per-event financial ledger: one row per registration payment (a charge,
 * with dollars broken out by add-on category) and one row per issued refund
 * (negative, attributed to the person). Net Collected = charges - refunds
 * reconciles against Stripe.
 */
class FinancialsExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    /** Fixed columns before the dynamic per-category breakdown columns. */
    private const LEAD_COLUMNS = 5; // Date, Type, Payor, Email, Registrants

    private Event $event;

    /** @var array{columns: array<string,string>, rows: array, net: float}|null */
    private ?array $data = null;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $data = $this->build();

        return view('event.exports.financials', [
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'net' => $data['net'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $event->getSheet()->freezePane('A2');
    }

    public function columnFormats(): array
    {
        $data = $this->build();
        $typeCount = count($data['columns']);

        $formats = ['A' => 'mm/dd/YYYY hh:mm'];

        // Every breakdown column plus the Total column is currency.
        $first = self::LEAD_COLUMNS + 1;
        $last = self::LEAD_COLUMNS + $typeCount + 1; // + Total column
        for ($i = $first; $i <= $last; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_CURRENCY_USD;
        }

        return $formats;
    }

    /**
     * Build the ledger once: the ordered breakdown columns, the charge/refund
     * rows sorted by date, and the net collected.
     *
     * @return array{columns: array<string,string>, rows: array, net: float}
     */
    private function build(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $this->event->loadMissing('addons');

        $registrations = EventRegistration::where('event_id', $this->event->id)
            ->with('addonAnswers')
            ->get();

        $refunds = Refund::where('event_id', $this->event->id)->get();

        $byPayment = $registrations->whereNotNull('payment_id')->groupBy('payment_id');
        $payments = Payment::whereIn('id', $byPayment->keys())->get()->keyBy('id');

        // Preload every user we'll name. The payor is on the Payment (user_id);
        // older registrations predate registering_user_id (it can be null), so the
        // Payment is the authoritative payor link.
        $userIds = collect()
            ->merge($registrations->pluck('user_id'))          // the registrants
            ->merge($registrations->pluck('registering_user_id'))
            ->merge($payments->pluck('user_id'))                // the payors
            ->merge($refunds->pluck('person_id'))
            ->merge($refunds->pluck('refunded_to_user_id'))
            ->filter()->unique();
        $users = User::findMany($userIds)->keyBy('id');

        $rows = [];
        $typesPresent = [];

        // --- Charge rows: one per Payment. ---
        foreach ($byPayment as $paymentId => $group) {
            $payment = $payments->get($paymentId);
            if (! $payment) {
                continue;
            }

            $amounts = [];

            // Itemized add-ons (donation, meal, apparel, ...) carry real amounts.
            // The base registration fee is NOT stored reliably per person (the
            // pivot's amount_due ignores household discounts / per-division
            // pricing), so we derive it as the residual: total charged minus the
            // itemized add-ons. That absorbs any discount into the base fee and
            // guarantees the breakdown reconciles to the Total.
            foreach ($group as $reg) {
                foreach ($reg->addonAnswers as $answer) {
                    if ($answer->type === 'registration_fee') {
                        continue; // rolled into the residual base fee below
                    }
                    $amt = (float) ($answer->amount ?? 0);
                    if ($amt == 0.0) {
                        continue;
                    }
                    $amounts[$answer->type] = round(($amounts[$answer->type] ?? 0) + $amt, 2);
                    $typesPresent[$answer->type] = true;
                }
            }

            $total = (float) $payment->amount_paid;
            $fee = round($total - array_sum($amounts), 2);
            if ($fee != 0.0) {
                $amounts['registration_fee'] = $fee;
                $typesPresent['registration_fee'] = true;
            }

            // Payor: the Payment's user; fall back to the group's registering user.
            $payorId = $payment->user_id ?? $group->pluck('registering_user_id')->filter()->first();
            $payor = $payorId ? $users->get($payorId) : null;
            $names = $group->map(fn ($reg) => $this->name($users->get($reg->user_id)))
                ->filter()->values()->implode(', ');

            $rows[] = [
                'date' => $payment->created_at,
                'type' => 'Registration',
                'payor' => $this->name($payor),
                'email' => $payor?->email,
                'registrants' => $names,
                'amounts' => $amounts,
                'total' => $total,
                'stripe_ref' => $payment->stripe_payment_intent_id,
            ];
        }

        // --- Refund rows: one per issued refund (negative). ---
        foreach ($refunds as $refund) {
            $amounts = [];
            foreach ((array) $refund->breakdown as $type => $amt) {
                $amt = (float) $amt;
                if ($amt == 0.0) {
                    continue;
                }
                $amounts[$type] = round(-$amt, 2);
                $typesPresent[$type] = true;
            }

            $rows[] = [
                'date' => $refund->created_at,
                'type' => 'Refund',
                'payor' => $this->name($users->get($refund->refunded_to_user_id)),
                'email' => $users->get($refund->refunded_to_user_id)?->email,
                'registrants' => $this->name($users->get($refund->person_id)),
                'amounts' => $amounts,
                'total' => round(-(float) $refund->amount, 2),
                'stripe_ref' => $refund->stripe_refund_id,
            ];
        }

        // Order the breakdown columns by the add-on registry order.
        $order = array_flip(array_keys(AddonRegistry::all()));
        $types = array_keys($typesPresent);
        usort($types, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $columns = [];
        foreach ($types as $type) {
            $columns[$type] = $this->event->addon($type)?->label()
                ?? AddonRegistry::for($type)?->label()
                ?? ucwords(str_replace('_', ' ', $type));
        }

        // Chronological ledger.
        usort($rows, fn ($a, $b) => ($a['date']?->timestamp ?? 0) <=> ($b['date']?->timestamp ?? 0));

        $net = round(array_sum(array_column($rows, 'total')), 2);

        return $this->data = compact('columns', 'rows', 'net');
    }

    private function name(?User $user): ?string
    {
        return $user ? trim($user->firstname.' '.$user->lastname) : null;
    }
}
