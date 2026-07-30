<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Renders a household's meal-ticket voucher to a printable PDF with Typst — one
 * voucher per registrant household, showing the total meals purchased ("Admits
 * N") plus the event's menu. Reuses the same Typst pipeline as RegistrationCardPdf.
 */
class MealVoucherPdf
{
    private string $template = 'resources/typst/meal-voucher.typ';

    /**
     * Sum the meals across a set of registrations (each with addonAnswers loaded)
     * and build a per-registrant breakdown.
     *
     * @param  iterable<EventRegistration>  $registrations
     * @return array{total: int, lines: array<int, array{name: string, meals: int}>}
     */
    public static function summarize(iterable $registrations): array
    {
        $lines = [];
        $total = 0;

        foreach ($registrations as $reg) {
            $meals = 0;
            foreach ($reg->addonAnswers as $answer) {
                if ($answer->type === 'meal_ticket') {
                    $meals += ($answer->selected ? 1 : 0) + (int) $answer->quantity;
                }
            }

            if ($meals > 0) {
                $lines[] = ['name' => $reg->user?->fullname ?? 'Registrant', 'meals' => $meals];
                $total += $meals;
            }
        }

        return ['total' => $total, 'lines' => $lines];
    }

    /**
     * Build the meal voucher for the responsible user's household for this event,
     * or null when the household bought no meals. Returns the absolute PDF path.
     */
    public function forHousehold(Event $event, User $responsibleUser): ?string
    {
        $householdIds = collect([$responsibleUser->id])
            ->merge($responsibleUser->dependents->pluck('id'))
            ->unique();

        $registrations = EventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $householdIds)
            ->with(['user', 'addonAnswers'])
            ->get();

        $summary = self::summarize($registrations);
        if ($summary['total'] < 1) {
            return null;
        }

        $reference = sprintf('MEAL-%d-%d', $event->id, $responsibleUser->id);

        return $this->render($this->payload($event, $responsibleUser, $summary, $reference));
    }

    /**
     * @param  array{total: int, lines: array}  $summary
     * @return array<string, mixed>
     */
    private function payload(Event $event, User $responsibleUser, array $summary, string $reference): array
    {
        $addon = $event->addon('meal_ticket');
        $label = (string) ($addon?->setting('label', 'Meal') ?? 'Meal');
        $menu = (string) ($addon?->setting('description', '') ?? '');

        return [
            'event' => (string) $event->name,
            'when' => $event->startdatetime?->format('l, F j, Y') ?? '',
            'where' => trim((string) preg_replace('/\s*\R\s*/', ', ', (string) $event->location)),
            'org' => 'Chung Do Association',
            'logo' => '/public/img/CDKTKD_logo.svg',
            'purchaser' => (string) $responsibleUser->fullname,
            'reference' => $reference,
            'total' => $summary['total'],
            'label' => $label,
            'lines' => $summary['lines'],
            'menu' => $menu,
        ];
    }

    /** Write the payload to JSON and compile it with Typst; returns the PDF path. */
    private function render(array $payload): string
    {
        $dir = storage_path('app/typst');
        File::ensureDirectoryExists($dir);

        $uid = Str::uuid()->toString();
        $jsonAbs = "$dir/$uid.json";
        $pdfAbs = "$dir/$uid.pdf";

        File::put($jsonAbs, json_encode($payload, JSON_UNESCAPED_SLASHES));

        // Path handed to Typst is relative to the project root (its --root).
        $jsonRel = '/'.ltrim(str_replace(base_path(), '', $jsonAbs), '/');

        $result = Process::path(base_path())->run([
            config('events.typst_bin', 'typst'),
            'compile',
            '--root', base_path(),
            '--input', "data=$jsonRel",
            $this->template,
            $pdfAbs,
        ]);

        File::delete($jsonAbs);

        if (! $result->successful()) {
            throw new \RuntimeException('Typst render failed: '.$result->errorOutput());
        }

        return $pdfAbs;
    }
}
