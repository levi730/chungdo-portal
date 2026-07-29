<?php

namespace App\Exports\ProjectUnited;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class FinalExportBySchool implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithTitle
{
    use Exportable;

    private mixed $to_schools_data;
    private mixed $mailing_data;

    public function __construct($to_schools_data)
    {
        $this->to_schools_data = $to_schools_data;
    }

    public function title(): string
    {
        return 'By School';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];

    }

    public function view(): \Illuminate\Contracts\View\View
    {

        return view('general.project-united.exports.final-export-by-school', [
            'to_schools_data' => $this->to_schools_data,
        ]);
    }

    public static function afterSheet(AfterSheet $event)
    {
        $highRow = $event->getSheet()->getHighestRow();
        $event->getSheet()->freezePane('A2');
        $event->getSheet()->setCellValue('D'. ($highRow+1), '=SUM(C2:C'.$highRow.')');
        $event->getSheet()->setCellValue('E'. ($highRow+1), '=SUM(D2:D'.$highRow.')');
        $event->getSheet()->setCellValue('F'. ($highRow+1), '=SUM(E2:E'.$highRow.')');
        $event->getSheet()->setCellValue('G'. ($highRow+1), '=SUM(F2:F'.$highRow.')');
        $event->getSheet()->setCellValue('H'. ($highRow+1), '=SUM(G2:G'.$highRow.')');
        $event->getSheet()->setCellValue('I'. ($highRow+1), '=SUM(H2:H'.$highRow.')');
        $event->getSheet()->setCellValue('J'. ($highRow+1), '=SUM(I2:I'.$highRow.')');
        $event->getSheet()->setCellValue('K'. ($highRow+1), '=SUM(J2:J'.$highRow.')');
        $event->getSheet()->setCellValue('L'. ($highRow+1), '=SUM(K2:K'.$highRow.')');
        $event->getSheet()->setCellValue('M'. ($highRow+1), '=SUM(L2:L'.$highRow.')');
        $event->getSheet()->setCellValue('N'. ($highRow+1), '=SUM(M2:M'.$highRow.')');
        $event->getSheet()->setCellValue('O'. ($highRow+1), '=SUM(N2:N'.$highRow.')');
        $event->getSheet()->setCellValue('P'. ($highRow+1), '=SUM(O2:O'.$highRow.')');
    }

    public function columnFormats(): array
    {
        return [];
    }

}
