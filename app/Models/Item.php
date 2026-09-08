<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'stock_number', 'name', 'description',
        'unit_id', 'account_title_id', 'on_hand_qty', 'unit_cost', 'is_active',
    ];

    protected $casts = [
        'on_hand_qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Item $item) {
            if ($item->is_active ?? true) {
                static::syncToOpenInventorySessions();
            }
        });

        static::updated(function (Item $item) {
            if (($item->is_active ?? true) && $item->wasChanged('is_active')) {
                static::syncToOpenInventorySessions();
            }
        });
    }

    /**
     * The next free number in the automated CS00001 series. Derived from the
     * highest code already issued, so deleting an item never hands out a code
     * that is already taken.
     */
    public static function nextStockNumberSequence(): int
    {
        $last = static::where('stock_number', 'like', 'CS%')
            ->orderByRaw('CAST(SUBSTRING(stock_number, 3) AS UNSIGNED) DESC')
            ->value('stock_number');

        return $last ? ((int) substr($last, 2)) + 1 : 1;
    }

    public static function formatStockNumber(int $sequence): string
    {
        return sprintf('CS%05d', $sequence);
    }

    public static function syncToOpenInventorySessions(): void
    {
        $openSessions = InventorySession::whereIn('status', [
            InventorySession::STATUS_DRAFT,
            InventorySession::STATUS_ACTIVE,
        ])->get();

        foreach ($openSessions as $session) {
            $session->seedLines();
        }
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function releaseItems(): HasMany
    {
        return $this->hasMany(ReleaseItem::class);
    }
}
