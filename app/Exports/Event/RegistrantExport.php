<?php

namespace App\Exports\Event;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class RegistrantExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    private Event $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];

    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $this->event->load('registrations.rank', 'registrations.school');
        $registrations = $this->event->registrations;

        // Attach add-on answers to each pivot so the accessors can read them.
        $answers = \App\Models\EventRegistrationAddon::whereIn('event_registration_id', $registrations->pluck('pivot.id')->filter())
            ->get()->groupBy('event_registration_id');
        foreach ($registrations as $reg) {
            $reg->pivot->setRelation('addonAnswers', $answers->get($reg->pivot->id, collect()));
        }

        return view('event.exports.registrants', [
            'registrations' => $registrations,
        ]);
    }

    public static function afterSheet(AfterSheet $event)
    {
        $event->getSheet()->freezePane('A2');
    }

    public function columnFormats(): array
    {
        return [
            'E' => 'mm/dd/YYYY',        // DOB
            'I' => 'mm/dd/YYYY hh:mm',   // Signup Time
        ];
    }
}
