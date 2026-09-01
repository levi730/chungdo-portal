<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order, written with the order and never rewritten. The name
 * and price are snapshots: editing a product later must not change what someone
 * was charged.
 *
 * @property int $product_order_id
 * @property string $product_name
 * @property string $variant_name
 * @property float $unit_price
 * @property int $quantity
 * @property float $amount
 */
class ProductOrderItem extends Model
{
    protected $keyType = 'integer';

    protected $fillable = [
        'product_order_id', 'product_id', 'product_run_id', 'product_variant_id',
        'product_name', 'variant_name', 'unit_price', 'quantity', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Which print run this line came from — what the pick list groups by, and
     * what carries the expected arrival date.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ProductRun::class, 'product_run_id');
    }

    /** May be null once a variant is removed from the catalog. */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
