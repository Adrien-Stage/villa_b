<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Portefeuille générique, indépendant des partenaires et des factures. */
class Wallet extends Model
{
    protected $attributes = ['balance' => 0];

    protected $fillable = ['owner_type', 'owner_id', 'currency'];

    protected $casts = ['balance' => 'integer'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
