<?php

namespace App\EventAddons;

/**
 * Resolves add-on type keys to their handler instances. The set of available
 * add-ons is declared in config/event_addons.php; adding a new add-on is a
 * matter of writing a handler class and listing it there.
 */
class AddonRegistry
{
    /** @var array<string, AddonHandler>|null */
    private static ?array $handlers = null;

    /** @return array<string, AddonHandler> keyed by type */
    public static function all(): array
    {
        if (self::$handlers === null) {
            self::$handlers = [];
            foreach (config('event_addons.handlers', []) as $class) {
                /** @var AddonHandler $handler */
                $handler = app($class);
                self::$handlers[$handler->type()] = $handler;
            }
        }

        return self::$handlers;
    }

    public static function for(string $type): ?AddonHandler
    {
        return self::all()[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** Reset the cache — used in tests when config is swapped at runtime. */
    public static function flush(): void
    {
        self::$handlers = null;
    }
}
