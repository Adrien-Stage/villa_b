<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Écriture comptable — un fait daté et figé.
 *
 * Une écriture validée ne se modifie ni ne se supprime : elle se corrige par
 * contre-passation. Toute insertion passe par LedgerService, seul garant de
 * l'équilibre débit/crédit et du respect des périodes verrouillées.
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'journal_id',
        'fiscal_period_id',
        'entry_date',
        'reference',
        'label',
        'source_type',
        'source_id',
        'schema',
        'posted_at',
        'reverses_id',
        'reversed_by_id',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at'  => 'datetime',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }

    /** Une extourne ne s'extourne pas à son tour. */
    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }

    public function totalDebit(): int
    {
        return (int) $this->lines->sum('debit');
    }

    public function totalCredit(): int
    {
        return (int) $this->lines->sum('credit');
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit() === $this->totalCredit();
    }
}
