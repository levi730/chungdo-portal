<?php

namespace App\Models;

use App\EventAddons\AddonHandler;
use App\EventAddons\AddonRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $type
 * @property bool $enabled
 * @property int $sort_order
 * @property array $settings
 */
class EventAddon extends Model
{
    protected $fillable = ['event_id', 'type', 'enabled', 'sort_order', 'settings', 'closes_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * Whether this add-on is currently accepting sign-ups and changes: it must
     * be enabled and either have no deadline or have a deadline still in the
     * future.
     */
    public function isOpen(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationAddon::class);
    }

    /**
     * The handler class that knows how to render, validate, price and persist
     * this add-on. Returns null if the stored type is no longer registered.
     */
    public function handler(): ?AddonHandler
    {
        return AddonRegistry::for($this->type);
    }

    /**
     * Read a single configuration value from settings, falling back to the
     * handler's default when unset.
     */
    public function setting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $defaults = $this->handler()?->defaultSettings() ?? [];

        return $defaults[$key] ?? $default;
    }

    public function label(): string
    {
        return $this->handler()?->label() ?? ucwords(str_replace('_', ' ', $this->type));
    }
}
