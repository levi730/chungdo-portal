<?php

namespace App\Livewire\Event;

use App\Models\EventRegistration;
use App\Models\Rank;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Columns\BooleanColumn;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class CheckIn extends DataTableComponent
{
    protected $model = EventRegistration::class;

    public $slug;

    public int $eventId;

    public array $bulkActions = [
        'checkIn' => 'Check In',
    ];

    public function mount($slug)
    {
        $this->slug = $slug;
        $this->eventId = \App\Models\Event::where('slug', $slug)->firstOrFail()->id;
    }

    public function builder(): Builder
    {
        return EventRegistration::query()
            ->with(['user', 'user.rank', 'user.school', 'addonAnswers']) // Eager load anything
            ->where('event_registrations.event_id', '=', $this->eventId);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setTableRowUrl(fn ($row) => route('event.check-in-details', ['slug' => $this->slug, 'users' => $row->getKey(), 'scan' => 1]));
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Checked In?')
                ->options([
                    '' => 'All',
                    '1' => 'Yes',
                    '0' => 'No',
                ])
                ->filter(function (Builder $builder, string $value) {
                    if ($value == '1') {
                        $builder->whereNotNull('checkin');
                    } elseif ($value === '0') {
                        $builder->whereNull('checkin');
                    }
                }),
            MultiSelectFilter::make('Rank')
                ->options(
                    Rank::query()
                        ->orderBy('id')
                        ->get()
                        ->keyBy('id')
                        ->map(fn ($rank) => $rank->rank)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $value) {
                    $builder->whereHas('user', fn (Builder $q) => $q->whereIn('rank_id', $value));
                }),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hideIf(1),
            BooleanColumn::make('Checked In?', 'checkin')
                ->sortable(),
            Column::make('First Name', 'user.firstname')
                ->sortable()
                ->searchable(),
            Column::make('Last Name', 'user.lastname')
                ->sortable()
                ->searchable(),
            Column::make('School', 'user.school.shortname')
                ->sortable()
                ->searchable(),
            Column::make('DOB', 'user.dob')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        $value = new \Carbon\Carbon($value);

                        return $value->format('m/d/Y').' ('.$value->age.')';
                    }
                ),
            Column::make('Height', 'user.height')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        return floor($value / 12)."' ".($value % 12).'"';
                    }
                ),
            Column::make('Weight', 'user.weight')
                ->sortable(),
            Column::make('Rank', 'user.rank.rank')
                ->sortable(
                    fn (Builder $query, string $direction) => $query->orderBy(
                        \App\Models\User::select('rank_id')->whereColumn('users.id', 'event_registrations.user_id'),
                        $direction
                    )
                )
                ->searchable(),
            Column::make('T-Shirt Size')
                ->label(fn ($row) => $row->tshirtSize()),
        ];
    }

    public function checkIn($ids = null)
    {
        if ($ids) {
            $sel = $ids;
        } else {
            $sel = $this->getSelected();
        }
        $users = EventRegistration::whereIn('id', $sel)->pluck('user_id');

        return redirect()->route('event.check-in-details', [$this->slug, $users->implode(',')]);
    }
}
