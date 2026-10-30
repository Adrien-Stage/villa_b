<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de transfert : l'économat approvisionne lui-même un dépôt qui lui
 * appartient, sans demande d'un service. La marchandise quitte le magasin
 * central au coût moyen et entre au dépôt au même coût ; elle reste du stock.
 */
class StockTransfer extends Model
{
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_VALIDATED => 'Transfert effectué',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'number', 'service_store_id', 'status', 'transferred_at', 'notes', 'total_value',
        'transferred_by', 'issuer_signature',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
        'tenant_id',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'cancelled_at'   => 'datetime',
        'total_value'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transfert) {
            $transfert->number ??= self::generateNumber();
            $transfert->status ??= self::STATUS_VALIDATED;
            $transfert->transferred_at ??= now();
        });
    }

    public static function generateNumber(): string
    {
        $prefixe = sprintf('BT-%d-', now()->year);

        $dernier = self::query()->where('number', 'like', $prefixe . '%')->orderByDesc('number')->value('number');
        $suite = $dernier && preg_match('/-(\d+)$/', $dernier, $m) ? ((int) $m[1]) + 1 : 1;

        do {
            $numero = $prefixe . str_pad((string) $suite++, 4, '0', STR_PAD_LEFT);
        } while (self::query()->where('number', $numero)->exists());

        return $numero;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeValides(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
