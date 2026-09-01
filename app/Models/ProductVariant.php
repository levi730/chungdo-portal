<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * A buyable SKU of a single RUN. `options` is keyed by the product's
 * option_names, so one run can span item x size without a column per axis.
 *
 * Belongs to the run, not the product: prices move between runs and each run
 * keeps the list it actually sold at.
 *
 * @property int $id
 * @property int $product_run_id
 * @property string $name
 * @property array|null $options
 * @property float $price
 * @property bool $enabled
 */
class ProductVariant extends Model
{
    protected $keyType = 'integer';

    protected $fillable = ['product_run_id', 'name', 'options', 'sku', 'price', 'enabled', 'sort_order'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'options' => 'array',
            'price' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProductRun::class, 'product_run_id');
    }

    /** The design this SKU belongs to, reached through its run. */
    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            ProductRun::class,
            'id',              // product_runs.id
            'id',              // products.id
            'product_run_id',  // product_variants.product_run_id
            'product_id'       // product_runs.product_id
        );
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /** "Sweatshirt / Navy / L" — the option values, for lists and pick sheets. */
    public function optionsText(): string
    {
        return collect($this->options ?? [])->filter()->implode(' / ');
    }

    /** What to show a buyer: the explicit name, falling back to the options. */
    public function displayName(): string
    {
        return $this->name ?: ($this->optionsText() ?: 'Item');
    }
}
