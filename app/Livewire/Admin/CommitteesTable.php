<?php

namespace App\Livewire\Admin;

use App\Models\Committee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class CommitteesTable extends DataTableComponent
{
    protected $model = Committee::class;

    public function boot(): void
    {
        abort_unless(Gate::allows('manage-users'), 403);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('name', 'asc')
            ->setTableRowUrl(fn ($row) => route('admin.committees.edit', ['committee' => $row->getKey()]));
    }

    public function builder(): Builder
    {
        return Committee::query()->withCount('members');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hideIf(true),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Slug', 'slug')
                ->sortable()
                ->searchable(),
            Column::make('Description', 'description')
                ->searchable()
                ->format(fn ($value) => Str::limit((string) $value, 80)),
            // Label column (not a DB field) so rappasoft doesn't try to select
            // a `committees.members_count` column; the withCount() subquery in
            // builder() still populates $row->members_count.
            Column::make('Members')
                ->label(fn ($row) => $row->members_count),
        ];
    }
}
