<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class ProductsTable extends DataTableComponent
{
    protected $model = Product::class;

    /**
     * Enforce store.manage on every request, including Livewire's AJAX update
     * calls (which don't pass through the route middleware).
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('store.manage'), 403);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('sort_order', 'asc')
            ->setTableRowUrl(fn ($row) => route('products.edit', ['product' => $row->getKey()]))
            ->setFilterLayoutSlideDown();
    }

    /**
     * Archived products stay in the list (muted, with a Restore button) rather
     * than living behind a filter — same as the events index.
     */
    public function builder(): Builder
    {
        return Product::query()->withTrashed()->withCount(['runs', 'variants']);
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Status', 'status')
                ->options(['' => 'All'] + Product::STATUSES)
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('status', $value);
                    }
                }),
            SelectFilter::make('Featured', 'highlighted')
                ->options(['' => 'All', '1' => 'Featured', '0' => 'Not featured'])
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('highlighted', (int) $value);
                    }
                }),
            SelectFilter::make('Archived', 'trashed')
                ->options(['' => 'Include', '0' => 'Hide', '1' => 'Only'])
                ->filter(function (Builder $builder, string $value) {
                    if ($value === '0') {
                        $builder->whereNull('deleted_at');
                    } elseif ($value === '1') {
                        $builder->whereNotNull('deleted_at');
                    }
                }),
        ];
    }

    public function columns(): array
    {
        return [
            // Ensures the primary key is selected so row URLs / actions have it.
            Column::make('Id', 'id')
                ->hideIf(true),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable()
                ->format(fn ($value, $row) => view('product.admin.cells.name', ['product' => $row]))
                ->html(),
            Column::make('Status', 'status')
                ->sortable()
                ->format(fn ($value, $row) => view('product.admin.cells.status', ['product' => $row]))
                ->html(),
            // Label columns (not DB fields) so rappasoft doesn't try to select a
            // `products.runs_count` column; the withCount() in builder() still
            // populates them.
            Column::make('Runs')
                ->label(fn ($row) => $row->runs_count),
            Column::make('Variants')
                ->label(fn ($row) => $row->variants_count),
            Column::make('Ordering')
                ->label(fn ($row) => view('product.admin.cells.window', ['product' => $row]))
                ->html(),
            Column::make('Payments to')
                ->label(fn ($row) => view('product.admin.cells.account', ['product' => $row]))
                ->html(),
            Column::make('Sort', 'sort_order')
                ->sortable(),
            Column::make('Actions')
                ->label(fn ($row) => view('product.admin.cells.actions', ['product' => $row]))
                ->html(),
        ];
    }
}
