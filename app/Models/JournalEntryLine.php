<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ligne d'écriture : un compte, un sens, un montant.
 *
 * L'auxiliaire porte la granularité par tiers — le compte reste 411000 pour
 * tous les clients, c'est `auxiliary_id` qui dit lequel.
 *
 * Montants en centimes FCFA.
 */
class JournalEntryLine extends Model
{
    /** Centres d'analyse (classe 9). */
    public const CENTER_ACCOMMODATION = 'hebergement';
    public const CENTER_RESTAURANT = 'restaurant';
    public const CENTER_SHOP = 'boutique';
    public const CENTER_STORE = 'economat';

    public const CENTERS = [
        self::CENTER_ACCOMMODATION => 'Hébergement',
        self::CENTER_RESTAURANT    => 'Restauration',
        self::CENTER_SHOP          => 'Boutique',
        self::CENTER_STORE         => 'Économat',
    ];

    protected $fillable = [
        'journal_entry_id',
        'account_code',
        'label',
        'debit',
        'credit',
        'auxiliary_type',
        'auxiliary_id',
        'analytic_center',
        'reconciliation_code',
        'reconciled_at',
    ];

    protected $casts = [
        'debit'         => 'integer',
        'credit'        => 'integer',
        'reconciled_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function auxiliary(): MorphTo
    {
        return $this->morphTo('auxiliary');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_code', 'code');
    }

    /** Solde signé de la ligne : positif au débit, négatif au crédit. */
    public function signedAmount(): int
    {
        return $this->debit - $this->credit;
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }

    public function scopeForAccount($query, string $code)
    {
        return $query->where('account_code', $code);
    }

    public function scopeForAuxiliary($query, string $type, int $id)
    {
        return $query->where('auxiliary_type', $type)->where('auxiliary_id', $id);
    }
}
