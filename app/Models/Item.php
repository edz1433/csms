<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'stock_number', 'name', 'description',
        'unit_id', 'account_title_id', 'on_hand_qty', 'is_active',
    ];

    protected $casts = [
        'on_hand_qty' => 'decimal:2',
        'is_active' => 'boolean',
    ];

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
