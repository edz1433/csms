<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical-count run. "Casting" an inventory opens it; closing it stops the
 * QR scan pages from accepting any more counts.
 */
class InventorySession extends Model
{
    /** Created but not counting yet — the only state that can be cast. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    /** Final: a closed inventory is the record of that count and never reopens. */
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'reference', 'title', 'status', 'location_id',
        'started_by', 'started_at', 'closed_by', 'closed_at', 'remarks',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** The one open session, or null when nothing is being counted. */
    public static function current(): ?self
    {
        return static::active()->latest('started_at')->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** The draft or active session, if any — only one may exist at a time. */
    public static function open(): ?self
    {
        return static::whereIn('status', [self::STATUS_DRAFT, self::STATUS_ACTIVE])
            ->latest('started_at')->first();
    }

    /**
     * The most recent inventory may be re-cast after it is closed — a count
     * often needs a second pass. Older ones are sealed for good.
     */
    public function isLatest(): bool
    {
        return $this->id === static::max('id');
    }

    public function canBeCast(): bool
    {
        return $this->isDraft() || ($this->isClosed() && $this->isLatest());
    }

    public function counts(): HasMany
    {
        return $this->hasMany(InventoryCount::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Every item gets a line when the inventory is cast, so the totals come
     * from the lines themselves. A line counts as done once counted_at is set,
     * which is what makes a counted zero count.
     */
    public function progress(): array
    {
        $total = $this->counts()->count();
        $counted = $this->counts()->whereNotNull('counted_at')->count();
        $variance = $this->counts()->whereNotNull('counted_at')
            ->whereColumn('counted_qty', '!=', 'system_qty')
            ->count();

        return [
            'counted' => $counted,
            'total' => $total,
            'remaining' => max(0, $total - $counted),
            'variance' => $variance,
            'percent' => $total > 0 ? (int) round($counted / $total * 100) : 0,
        ];
    }

    /**
     * Snapshot every active item onto the sheet with the stock it is expected
     * to have. Quantities stay null until someone counts them.
     */
    public function seedLines(): int
    {
        $existing = $this->counts()->pluck('item_id')->all();
        $now = now();
        $seeded = 0;

        Item::where('is_active', true)
            ->whereNotIn('id', $existing)
            ->select(['id', 'unit_id', 'on_hand_qty'])
            ->chunkById(500, function ($items) use ($now, &$seeded) {
                $rows = $items->map(fn (Item $item) => [
                    'inventory_session_id' => $this->id,
                    'item_id' => $item->id,
                    'unit_id' => $item->unit_id,
                    'system_qty' => $item->on_hand_qty,
                    'counted_qty' => null,
                    'counted_by' => null,
                    'counted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                InventoryCount::insert($rows);
                $seeded += count($rows);
            });

        return $seeded;
    }

    /**
     * Re-snapshot the expected stock on lines nobody has counted yet. Called
     * when the inventory is cast, so "previous qty" is the stock as of the
     * moment counting actually started rather than when the sheet was created.
     */
    public function refreshExpectedQuantities(): void
    {
        $this->counts()->whereNull('counted_at')->with('item:id,on_hand_qty')
            ->chunkById(500, function ($lines) {
                foreach ($lines as $line) {
                    if ($line->item && (float) $line->system_qty !== (float) $line->item->on_hand_qty) {
                        $line->update(['system_qty' => $line->item->on_hand_qty]);
                    }
                }
            });
    }
}
