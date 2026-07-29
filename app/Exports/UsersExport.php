<?php

namespace App\Exports;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports users (typically the filtered/searched result set from the admin
 * users table) to a spreadsheet. Built from a query so large result sets are
 * chunked rather than loaded all at once.
 */
class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Last Name', 'First Name', 'Email', 'School', 'Rank', 'Student'];
    }

    /**
     * @param  \App\Models\User  $user
     */
    public function map($user): array
    {
        return [
            $user->lastname,
            $user->firstname,
            $user->email,
            $user->school?->shortname,
            $user->rank?->rank,
            $user->is_student ? 'Yes' : 'No',
        ];
    }
}
