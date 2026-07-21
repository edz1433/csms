<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseItem extends Model
{
    protected $fillable = [
        'release_id', 'item_id', 'account_title_id', 'rca_code',
        'unit_id', 'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
