<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une dépense décaissée de l'établissement (comptabilité de caisse).
 */
class Expense extends Model
{
    use HasFactory;

    const CATEGORY_ELECTRICITY = 'electricity';
    const CATEGORY_WATER       = 'water';
    const CATEGORY_PURCHASE    = 'purchase';
    const CATEGORY_RENT        = 'rent';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_TRANSPORT   = 'transport';
    const CATEGORY_OTHER       = 'other';

    /**
     * Catégories de charges → libellé affiché. Volontairement court : pas de
     * salaires (l'appli ne gère pas les employés), pas de fournisseurs (→ module
     * Inventaire à venir).
     */
    public const CATEGORIES = [
        self::CATEGORY_ELECTRICITY => 'Électricité',
        self::CATEGORY_WATER       => 'Eau',
        self::CATEGORY_PURCHASE    => 'Achats',
        self::CATEGORY_RENT        => 'Loyer',
        self::CATEGORY_MAINTENANCE => 'Entretien',
        self::CATEGORY_TRANSPORT   => 'Transport',
        self::CATEGORY_OTHER       => 'Divers',
    ];

    protected $fillable = [
        'occurred_at',
        'category',
        'label',
        'amount',
        'payment_method',
        'receipt_path',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'amount'      => 'integer',
    ];

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeInPeriod(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('occurred_at', [$from, $to]);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Divers';
    }
}
