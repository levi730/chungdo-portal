<?php

use App\Models\Event;
use App\Services\RegistrationCardPdf;

// Render all registration cards for an event to a single PDF (2-up per page) via
// Typst, and drop it at a public path. Run with:  ddev artisan tinker < oneoff/print_regs.php

$event = Event::find(9);

echo "Rendering cards for {$event->name}...\n";
$start = microtime(true);

$pdf = (new RegistrationCardPdf())->generate($event);

$dest = '/www/chungdo.org/www/public/RegCards_Winter2026.pdf';
if (file_exists($dest)) {
    unlink($dest);
}
copy($pdf, $dest);
@unlink($pdf);

printf("Done in %.2fs: %s\n", microtime(true) - $start, $dest);
