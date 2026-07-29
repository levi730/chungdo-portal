<?php

namespace App\Livewire\Admin;

use App\Exports\UsersExport;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Columns\BooleanColumn;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class UsersTable extends DataTableComponent
{
    protected $model = User::class;

    /**
     * Enforce the manage-users ability on every request, including Livewire's
     * AJAX update calls (which don't pass through the route middleware).
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-users'), 403);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('lastname', 'asc')
            ->setTableRowUrl(fn ($row) => route('admin.users.edit', ['user' => $row->getKey()]))
            // Renders the "All Black" + "Export" buttons inside the component.
            ->setConfigurableArea('before-toolbar', 'livewire.admin.users-table-actions')
            // Lay filters out in a wide panel (up to 4 across) instead of a
            // cramped, scrolling popover — the long rank/school checkbox lists
            // then show fully without an inner scroll.
            ->setFilterLayoutSlideDown();
    }

    /**
     * Quick action: select every black-belt rank (id >= 1) in the Rank filter.
     */
    public function selectAllBlack(): void
    {
        $blackRankIds = Rank::where('id', '>=', 1)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->setFilter('rank_id', $blackRankIds);
    }

    /**
     * Export the current filtered/searched result set to an .xlsx file.
     */
    public function exportXlsx()
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $query = $this->baseQuery()
            ->reorder()
            ->orderBy('lastname')
            ->orderBy('firstname');

        return Excel::download(new UsersExport($query), 'users-'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function builder(): Builder
    {
        return User::query()->with(['rank', 'school']);
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Student?')
                ->options(['' => 'All', '1' => 'Yes', '0' => 'No'])
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('is_student', (int) $value);
                    }
                }),
            SelectFilter::make('Zulip sync', 'sync_to_zulip')
                ->options(['' => 'All', '1' => 'Yes', '0' => 'No'])
                ->filter(function (Builder $builder, string $value) {
                    if ($value !== '') {
                        $builder->where('sync_to_zulip', (int) $value);
                    }
                }),
            MultiSelectFilter::make('School', 'school_id')
                ->options(School::orderBy('name')->pluck('name', 'id')->toArray())
                ->filter(function (Builder $builder, array $values) {
                    // OR across the selected schools.
                    $builder->whereIn('school_id', $values);
                }),
            MultiSelectFilter::make('Rank', 'rank_id')
                ->options(Rank::orderBy('id')->pluck('rank', 'id')->toArray())
                ->filter(function (Builder $builder, array $values) {
                    // OR across the selected ranks.
                    $builder->whereIn('rank_id', $values);
                }),
        ];
    }

    public function columns(): array
    {
        return [
            // Ensures the primary key is selected so row URLs / actions have it.
            Column::make('Id', 'id')
                ->hideIf(true),
            Column::make('Last Name', 'lastname')
                ->sortable()
                ->searchable(),
            Column::make('First Name', 'firstname')
                ->sortable()
                ->searchable(),
            Column::make('Email', 'email')
                ->sortable()
                ->searchable(),
            // Label columns (read from the eager-loaded relations) so rappasoft
            // doesn't join+alias `rank`/`school` onto the row, which would
            // shadow the relationships (and break the export's $user->rank).
            Column::make('School')
                ->label(fn ($row) => $row->school?->shortname),
            Column::make('Rank')
                ->label(fn ($row) => $row->rank?->rank),
            BooleanColumn::make('Student?', 'is_student')
                ->sortable(),
            BooleanColumn::make('Zulip', 'sync_to_zulip')
                ->sortable(),
            Column::make('Edit')
                ->label(fn ($row) => '<span class="text-primary">Edit</span>')
                ->html(),
        ];
    }
}
