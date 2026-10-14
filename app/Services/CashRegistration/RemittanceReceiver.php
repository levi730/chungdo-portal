<?php

namespace App\Services\CashRegistration;

use App\Models\CashRemittance;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The two ways an outstanding remittance ends: the money is received, or it is
 * voided because it was entered by mistake.
 *
 * Both lock the row and check it is still outstanding, so a double-click or two
 * treasurers at once converge on one outcome — the same guard as
 * RegistrationFulfiller, for the same reason.
 */
class RemittanceReceiver
{
    /**
     * Record the deposit. Creates the Payment the financials export reads, and
     * stamps each registration with what was paid toward it.
     *
     * The amount received is split across the students in slip order, each up
     * to their own line total. A short envelope therefore leaves the last
     * students showing unpaid on their registration cards rather than everyone
     * looking slightly underpaid; any overpayment lands on the last student so
     * the registrations always sum to what was deposited.
     */
    public function receive(
        CashRemittance $remittance,
        User $by,
        float $amount,
        string $method,
        ?string $checkNumbers = null,
        ?string $depositNote = null,
    ): CashRemittance {
        if (! array_key_exists($method, CashRemittance::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose cash, check, or both.']);
        }
        if ($amount < 0) {
            throw ValidationException::withMessages(['amount' => 'The amount received cannot be negative.']);
        }

        return DB::transaction(function () use ($remittance, $by, $amount, $method, $checkNumbers, $depositNote) {
            /** @var CashRemittance $r */
            $r = CashRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();

            if (! $r->isOutstanding()) {
                throw ValidationException::withMessages(['remittance' => "{$r->reference} is already {$r->status}."]);
            }

            $payment = Payment::create([
                'user_id' => $r->responsible_user_id,
                'amount_paid' => $amount,
                'method' => $method,
            ]);

            $owed = collect($r->lines())->mapWithKeys(fn ($s) => [$s['user_id'] => (float) $s['amount']]);
            $registrations = EventRegistration::where('cash_remittance_id', $r->id)->orderBy('id')->get();
            $remaining = round($amount, 2);

            foreach ($registrations as $i => $reg) {
                $share = min($remaining, $owed[$reg->user_id] ?? 0.0);
                if ($i === $registrations->count() - 1) {
                    $share = $remaining; // the overpayment, if any, goes on the last
                }
                $remaining = round($remaining - $share, 2);

                DB::table('event_registrations')->where('id', $reg->id)->update([
                    'amount_paid' => $share,
                    'payment_id' => $payment->id,
                    'updated_at' => now(),
                ]);

                // Paid add-ons carry the payment, as on the card path, so a
                // later refund request can find what it is refunding.
                EventRegistrationAddon::where('event_registration_id', $reg->id)
                    ->where('amount', '>', 0)
                    ->update(['payment_id' => $payment->id]);
            }

            $r->fill([
                'status' => CashRemittance::STATUS_RECEIVED,
                'amount_received' => $amount,
                'received_method' => $method,
                'check_numbers' => $checkNumbers ?: null,
                'deposit_note' => $depositNote ?: null,
                'received_at' => now(),
                'received_by_user_id' => $by->id,
                'payment_id' => $payment->id,
            ])->save();

            return $r;
        });
    }

    /**
     * Undo a remittance entered by mistake, before any money is recorded. Its
     * registrations are archived to event_registrations_deleted and removed;
     * the remittance itself is kept, void, with its payload, as the record of
     * what was entered. Once received it is money, and goes through refunds.
     */
    public function void(CashRemittance $remittance, User $by, string $reason): CashRemittance
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Say why this is being voided.']);
        }

        return DB::transaction(function () use ($remittance, $by, $reason) {
            /** @var CashRemittance $r */
            $r = CashRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();

            if (! $r->isOutstanding()) {
                throw ValidationException::withMessages(['remittance' => "{$r->reference} is already {$r->status}."]);
            }

            $rows = DB::table('event_registrations')->where('cash_remittance_id', $r->id)->get();

            foreach ($rows as $row) {
                DB::table('event_registrations_deleted')->insert([
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                    'deleted_at' => now(),
                    'user_id' => $row->user_id,
                    'event_id' => $row->event_id,
                    'amount_due' => $row->amount_due,
                    'amount_paid' => $row->amount_paid,
                    'payment_id' => $row->payment_id,
                    'cash_remittance_id' => $row->cash_remittance_id,
                    'checkin' => $row->checkin,
                    'event_division_id' => $row->event_division_id,
                    'registering_user_id' => $row->registering_user_id,
                    'docs_printed' => $row->docs_printed,
                ]);
            }

            $ids = $rows->pluck('id');
            EventRegistrationAddon::whereIn('event_registration_id', $ids)->delete();
            DB::table('event_registrations')->whereIn('id', $ids)->delete();

            $r->fill([
                'status' => CashRemittance::STATUS_VOID,
                'voided_at' => now(),
                'voided_by_user_id' => $by->id,
                'void_reason' => $reason,
            ])->save();

            return $r;
        });
    }
}
