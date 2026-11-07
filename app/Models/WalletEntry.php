<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Journal générique immuable : correction par contre-opération uniquement. */
class WalletEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Un mouvement de portefeuille ne se modifie pas.'));
        static::deleting(fn () => throw new \LogicException('Un mouvement de portefeuille ne se supprime pas.'));
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(WalletEntryAttachment::class);
    }
}
