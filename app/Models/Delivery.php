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
     * A delivery header can be saved before its items are known, so it may
     * legitimately have no lines yet. It is a draft until the first line and
     * the PO number are both in place.
     */
    public function isDraft(): bool
    {
        return ! filled($this->po_number) || ! $this->hasLines();
    }

    /** Line count without a query when the caller already eager-loaded/counted. */
    public function hasLines(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->isNotEmpty();
        }

        if ($this->items_count !== null) {
            return (int) $this->items_count > 0;
        }

        return $this->items()->exists();
    }

    /** Human label for a delivery whose PO number has not been captured yet. */
    public function poLabel(): string
    {
        return $this->po_number ?: 'PO pending';
    }

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

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function iar(): HasOne
    {
        return $this->hasOne(InspectionAcceptanceReport::class);
    }
}
