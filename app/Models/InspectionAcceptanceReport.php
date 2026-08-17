<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAcceptanceReport extends Model
{
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'delivery_id', 'iar_number', 'iar_date', 'requisitioning_office',
        'responsibility_center_code', 'invoice_number', 'invoice_date',
        'inspection_date', 'inspection_officer', 'acceptance_date',
        'acceptance_status', 'partial_quantity', 'accepted_by', 'remarks',
        'created_by', 'or_number', 'is_paid', 'paid_at', 'paid_by',
    ];

    protected $casts = [
        'iar_date' => 'date',
        'invoice_date' => 'date',
        'inspection_date' => 'date',
        'acceptance_date' => 'date',
        'partial_quantity' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isComplete(): bool
    {
        return $this->acceptance_status === self::STATUS_COMPLETE;
    }
}
