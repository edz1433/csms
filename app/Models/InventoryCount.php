<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCount extends Model
{
    protected $fillable = [
        'inventory_session_id', 'item_id', 'unit_id',
        'system_qty', 'counted_qty', 'counted_by', 'counted_at', 'remarks',
    ];

    protected $casts = [
        'system_qty' => 'decimal:2',
        'counted_qty' => 'decimal:2',
        'counted_at' => 'datetime',
    ];

    /** A line is counted once someone saves a quantity — zero included. */
    public function isCounted(): bool
    {
        return $this->counted_at !== null;
    }

    /**
     * Counted minus system: positive is an overage, negative a shortage.
     * Null until the line is actually counted, so an untouched line is never
     * mistaken for a full shortage.
     */
    public function getVarianceAttribute(): ?float
    {
        return $this->isCounted() ? (float) $this->counted_qty - (float) $this->system_qty : null;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
