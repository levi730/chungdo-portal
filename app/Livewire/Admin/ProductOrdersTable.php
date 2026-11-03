<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/**
 * The store orders list — filter by product, school, payment status and
 * hand-over status; a row opens the order. Same bones as ProductsTable.
 */
class ProductOrdersTable extends DataTableComponent
{
    protected $model = ProductOrder::class;

    /** Enforced on Livewire's own endpoint too, not just the route. */
    public function boot(): void
    {
        abort_unless(Gate::allows('store.manage'), 403);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('created_at', 'desc')
            ->setTableRowUrl(fn ($row) => route('orders.show', ['order' => $row->getKey()]))
            ->setFilterLayoutSlideDown()
            ->setPerPageAccepted([25, 50, 100])
            ->setPerPage(50);
    }

    public function builder(): Builder
    {
        return ProductOrder::query()->with(['items', 'pickupSchool']);
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Product', 'product')
                ->options(['' => 'All'] + Product::withTrashed()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->whereHas('items', fn ($q) => $q->where('product_id', (int) $value));
                    }
                }),
            SelectFilter::make('Pickup school', 'school')
                ->options(['' => 'All'] + School::orderBy('name')->pluck('name', 'id')->all())
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('pickup_school_id', (int) $value);
                    }
                }),
            SelectFilter::make('Payment', 'status')
                ->options([
                    '' => 'All',
                    ProductOrder::STATUS_PAID => 'Paid',
                    ProductOrder::STATUS_PENDING => 'Pending',
                    ProductOrder::STATUS_FAILED => 'Failed',
                    ProductOrder::STATUS_CANCELLED => 'Cancelled',
                ])
                ->setFilterDefaultValue(ProductOrder::STATUS_PAID)
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('status', $value);
                    }
                }),
            SelectFilter::make('Hand-over', 'fulfillment')
                ->options(['' => 'All'] + ProductOrder::FULFILLMENT_STATUSES)
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('fulfillment_status', $value);
                    }
                }),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hideIf(true),
            Column::make('Placed', 'created_at')
                ->sortable()
                ->format(fn ($value) => $value?->format('M j, Y g:ia')),
            Column::make('Buyer', 'name')
                ->sortable()
                ->searchable()
                ->format(fn ($value, $row) => view('product.admin.orders.cells.buyer', ['order' => $row]))
                ->html(),
            // Searchable but not shown: the buyer cell already prints it.
            Column::make('Email', 'email')
                ->searchable()
                ->hideIf(true),
            Column::make('Order #', 'reference')
                ->searchable()
                ->format(fn ($value, $row) => $row->shortReference()),
            Column::make('Items')
                ->label(fn ($row) => view('product.admin.orders.cells.items', ['order' => $row]))
                ->html(),
            Column::make('Pickup at')
                ->label(fn ($row) => $row->pickupSchool?->name ?? '—'),
            Column::make('Total', 'total')
                ->sortable()
                ->format(fn ($value, $row) => view('product.admin.orders.cells.total', ['order' => $row]))
                ->html(),
            Column::make('Payment', 'status')
                ->sortable()
                ->format(fn ($value, $row) => view('product.admin.orders.cells.status', ['order' => $row]))
                ->html(),
            Column::make('Hand-over', 'fulfillment_status')
                ->sortable()
                ->format(fn ($value, $row) => view('product.admin.orders.cells.fulfillment', ['order' => $row]))
                ->html(),
        ];
    }
}
