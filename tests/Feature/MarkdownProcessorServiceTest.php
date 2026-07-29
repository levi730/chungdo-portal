<?php

use App\Services\MarkdownProcessorService;

it('renders null or empty markdown as an empty string', function () {
    $svc = new MarkdownProcessorService();

    expect($svc->render(null))->toBe('');
    expect($svc->render(''))->toBe('');
});

it('still renders real markdown', function () {
    $html = (new MarkdownProcessorService())->render('**bold**');

    expect($html)->toContain('bold')->not->toBe('');
});
