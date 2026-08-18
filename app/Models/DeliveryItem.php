<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id', 'item_id', 'unit_id', 'ordered_qty', 'quantity', 'unit_cost', 'received_at',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'received_at' => 'date',
    ];

    /**
     * Quantity still owed by the supplier on this line. Null ordered_qty means
     * the PO quantity was never captured, so there is nothing to compare.
     */
    public function balanceQty(): float
    {
        if ($this->ordered_qty === null) {
            return 0.0;
        }

        return max(0.0, (float) $this->ordered_qty - (float) $this->quantity);
    }

    public function isFullyReceived(): bool
    {
        return $this->balanceQty() <= 0.0;
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
