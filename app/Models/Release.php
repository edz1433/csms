<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    protected $fillable = [
        'ris_number', 'fund_cluster_id', 'location_id',
        'released_by', 'released_at', 'remarks',
    ];

    protected $casts = ['released_at' => 'datetime'];

    public function fundCluster(): BelongsTo
    {
        return $this->belongsTo(FundCluster::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReleaseItem::class);
    }
}
