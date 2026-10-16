<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passation de service : le tiroir et les consignes qu'un réceptionniste
 * remet à celui qui lui succède. Elle attend jusqu'à ce que quelqu'un la
 * reprenne en recomptant le tiroir.
 */
class ShiftHandover extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_TAKEN = 'taken';

    protected $fillable = [
        'module', 'status',
        'from_user_id', 'from_session_id', 'handed_amount', 'instructions', 'handed_at',
        'planned_user_id', 'planned_assignment_id',
        'to_user_id', 'to_session_id', 'received_amount', 'discrepancy_amount', 'reception_notes', 'taken_at',
    ];

    protected $casts = [
        'handed_amount'      => 'integer',
        'received_amount'    => 'integer',
        'discrepancy_amount' => 'integer',
        'handed_at'          => 'datetime',
        'taken_at'           => 'datetime',
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function plannedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planned_user_id');
    }

    public function plannedAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'planned_assignment_id');
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'from_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'to_session_id');
    }

    /** Repris par une autre personne que celle que le planning attendait. */
    public function reprisHorsPlanning(): bool
    {
        return $this->planned_user_id !== null && $this->to_user_id !== null && $this->planned_user_id !== $this->to_user_id;
    }
}
