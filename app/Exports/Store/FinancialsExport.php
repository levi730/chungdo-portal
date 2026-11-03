<?php

namespace App\Exports\Store;

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderRefund;
use App\Models\ProductRun;
use App\Services\Stripe\StripeAccounts;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * The store's financial ledger, the same shape as the event one
 * (App\Exports\Event\FinancialsExport) so the two reconcile the same way: one
 * row per paid order (a charge, with dollars broken out by product) and one
 * row per issued refund (negative). Net Collected = charges - refunds.
 *
 * Unlike an event, the store is not tied to one Stripe account — each product
 * names its own — so every row carries the account its money is in and the
 * net is totalled per account. Only the rows for one account reconcile
 * against that account's dashboard.
 *
 * Optionally scoped to one product or one print run; a run is the natural
 * unit ("the fall 2026 shirts"). An order spanning two runs appears in both
 * exports, with only the matching run's lines in the breakdown columns.
 */
class FinancialsExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    /** Fixed columns before the per-product breakdown: Date, Type, Buyer, Email, School, Account. */
    private const LEAD_COLUMNS = 6;

    private ?array $data = null;

    public function __construct(
        private ?Product $product = null,
        private ?ProductRun $run = null,
    ) {
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $data = $this->build();

        return view('product.admin.exports.financials', [
            'scope' => $this->scopeLabel(),
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'net' => $data['net'],
            'netByAccount' => $data['netByAccount'],
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
        $event->getSheet()->freezePane('A3');
    }

    public function columnFormats(): array
    {
        $data = $this->build();

        $formats = ['A' => 'mm/dd/YYYY hh:mm'];

        $first = self::LEAD_COLUMNS + 1;
        $last = self::LEAD_COLUMNS + count($data['columns']) + 1; // + Total
        for ($i = $first; $i <= $last; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_CURRENCY_USD;
        }

        return $formats;
    }

    /**
     * @return array{columns: array<int, string>, rows: array, net: float, netByAccount: array<string, float>}
     */
    private function build(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $accounts = app(StripeAccounts::class);

        $orders = ProductOrder::query()
            ->paid()
            ->when($this->run, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_run_id', $this->run->id)))
            ->when($this->product && ! $this->run, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $this->product->id)))
            ->with(['items', 'pickupSchool', 'refunds'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        // One breakdown column per product that appears, in catalogue order.
        $productIds = $orders->flatMap(fn ($o) => $this->linesOf($o)->pluck('product_id'))->unique();
        $columns = Product::withTrashed()->findMany($productIds)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->pluck('name', 'id')
            ->all();

        $rows = [];
        $netByAccount = [];

        foreach ($orders as $order) {
            $accountLabel = $accounts->label($order->stripe_account);
            $amounts = [];
            foreach ($this->linesOf($order) as $line) {
                $amounts[$line->product_id] = round(($amounts[$line->product_id] ?? 0) + (float) $line->amount, 2);
            }
            // What was actually captured, which can differ from the sum of the
            // lines (tax, or a scoped export showing part of an order).
            $total = $this->run || $this->product
                ? round(array_sum($amounts), 2)
                : round((float) ($order->amount_paid ?? $order->total), 2);

            $rows[] = [
                'date' => $order->paid_at ?? $order->created_at,
                'type' => 'Order',
                'buyer' => $order->name,
                'email' => $order->email,
                'school' => $order->pickupSchool?->name,
                'account' => $accountLabel,
                'amounts' => $amounts,
                'total' => $total,
                'reference' => $order->stripe_payment_intent_id ?? $order->stripe_checkout_session_id,
                'order' => $order->shortReference(),
            ];
            $netByAccount[$accountLabel] = round(($netByAccount[$accountLabel] ?? 0) + $total, 2);

            /** @var ProductOrderRefund $refund */
            foreach ($order->refunds as $refund) {
                $rows[] = [
                    'date' => $refund->created_at,
                    'type' => 'Refund',
                    'buyer' => $order->name,
                    'email' => $order->email,
                    'school' => $order->pickupSchool?->name,
                    'account' => $accountLabel,
                    'amounts' => [],
                    'total' => -round((float) $refund->amount, 2),
                    'reference' => $refund->stripe_refund_id,
                    'order' => $order->shortReference(),
                ];
                $netByAccount[$accountLabel] = round($netByAccount[$accountLabel] - (float) $refund->amount, 2);
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        return $this->data = [
            'columns' => $columns,
            'rows' => $rows,
            'net' => round(array_sum($netByAccount), 2),
            'netByAccount' => $netByAccount,
        ];
    }

    /** The order's lines inside this export's scope. */
    private function linesOf(ProductOrder $order): \Illuminate\Support\Collection
    {
        if ($this->run) {
            return $order->items->where('product_run_id', $this->run->id);
        }
        if ($this->product) {
            return $order->items->where('product_id', $this->product->id);
        }

        return $order->items;
    }

    private function scopeLabel(): string
    {
        if ($this->run) {
            return $this->run->product->name.' — '.$this->run->name;
        }
        if ($this->product) {
            return $this->product->name.' (all runs)';
        }

        return 'All store orders';
    }
}
