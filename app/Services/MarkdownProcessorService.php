<?php

namespace App\Services;

use Illuminate\Support\Str;

class MarkdownProcessorService
{
    /** @var array<string, callable[]> */
    protected array $customParsers = [];

    public function __construct()
    {
        // Register built-in custom tags
        $this->register('zoom', [$this, 'parseZoomTag']);
    }

    public function render(?string $markdown): string
    {
        if ($markdown === null || $markdown === '') {
            return '';
        }

        return $this->toHtml($markdown);
    }

    /**
     * Register a custom tag handler.
     */
    public function register(string $tag, callable $handler): void
    {
        $this->customParsers[$tag] = $handler;
    }

    /**
     * Apply all custom tag replacements.
     *
     * Supports syntax: !tag[arg1|arg2|arg3]
     * Arguments are split by | and handlers receive an array of args.
     */
    protected function applyCustomTags(string $markdown): string
    {
        foreach ($this->customParsers as $tag => $handler) {

            // Matches: !tag[ ... ]
            $pattern = "/!{$tag}\[(.*?)\]/";

            $markdown = preg_replace_callback($pattern, function ($matches) use ($handler) {
                $rawArgs = $matches[1];

                // Split args on pipes (|), but allow escaped \|
                $args = preg_split('/(?<!\\\\)\|/', $rawArgs);

                // Clean escape sequences: "\|" → "|"
                $args = array_map(
                    fn($a) => str_replace('\|', '|', trim($a)),
                    $args
                );

                // Call the handler with argument array
                return $handler($args);
            }, $markdown);
        }

        return $markdown;
    }

    /**
     * Process full Markdown -> HTML with custom tags.
     */
    public function toHtml(string $markdown): string
    {
        $markdown = $this->applyCustomTags($markdown);

        return Str::markdown($markdown); // Uses league/commonmark
    }

    /**
     * Handler for !zoom[] syntax.
     */

    protected function parseZoomTag(array $args): string
    {

        $src = $args[0] ?? '';
        $size_limit = $args[1] ?? 'w300';
        if(str_starts_with($size_limit, 'h')) {
            $cmd = "?h=" . substr($size_limit, 1);
        } else {
            $cmd = "?w=" . substr($size_limit, 1);
        }
        $thumb = '/glide/public' . $src . $cmd;

        return <<<HTML
<a href="{$src}" target="_blank">
    <img src="{$thumb}" style="cursor:pointer;" />
</a>
HTML;
    }
}
