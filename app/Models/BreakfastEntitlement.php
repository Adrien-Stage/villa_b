<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BreakfastEntitlement : Droit journalier au petit-déjeuner pour une réservation active.
 *
 * Fait le pont entre le PMS (droits inclus dans la réservation) et le POS restaurant
 * (pointage et service effectif).
 */
class BreakfastEntitlement extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CONSUMED  = 'consumed';
    public const STATUS_PARTIAL   = 'partially_consumed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'room_id',
        'service_date',
        'adults_included',
        'children_included',
        'adults_consumed',
        'children_consumed',
        'status',
        'consumed_at',
        'served_by',
        'notes',
    ];

    protected $casts = [
        'service_date'      => 'date:Y-m-d',
        'adults_included'   => 'integer',
        'children_included' => 'integer',
        'adults_consumed'   => 'integer',
        'children_consumed' => 'integer',
        'consumed_at'       => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('service_date', $date);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('service_date', now()->toDateString());
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeConsumed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_CONSUMED, self::STATUS_PARTIAL]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isConsumed(): bool
    {
        return $this->status === self::STATUS_CONSUMED;
    }

    public function remainingAdults(): int
    {
        return max(0, $this->adults_included - $this->adults_consumed);
    }

    public function remainingChildren(): int
    {
        return max(0, $this->children_included - $this->children_consumed);
    }
}
