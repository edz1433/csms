<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'po_number', 'fund_cluster_id', 'supplier_id', 'received_by', 'received_at', 'remarks',
        'or_number', 'is_paid', 'paid_at', 'paid_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    /**
     * A delivery is still "partial" while any line has an ordered quantity that
     * has not been fully received yet. Lines without an ordered quantity are
     * treated as complete — nothing to wait for.
     */
    public function isPartial(): bool
    {
        return $this->items->contains(fn (DeliveryItem $line) => ! $line->isFullyReceived());
    }

    public function outstandingQty(): float
    {
        return (float) $this->items->sum(fn (DeliveryItem $line) => $line->balanceQty());
    }

    /**
     * Deliveries stay editable so the balance of a partial shipment can be
     * added later, but only until accounting has paid them — a paid delivery
     * backs an OR and must not move.
     */
    public function isEditable(): bool
    {
        return ! $this->is_paid;
    }

    public function fundCluster(): BelongsTo
    {
        return $this->belongsTo(FundCluster::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function iar(): HasOne
    {
        return $this->hasOne(InspectionAcceptanceReport::class);
    }
}
