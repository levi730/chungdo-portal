<?php

namespace App\Exports\ProjectUnited;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;

class FinalExport implements WithMultipleSheets
{
    use Exportable;

    private mixed $to_schools_data;

    public function __construct($to_schools_data)
    {
        $this->to_schools_data = $to_schools_data;
    }

    public function sheets(): array
    {
        return [
            new FinalExportBySchool($this->to_schools_data),
        ];
    }


}
