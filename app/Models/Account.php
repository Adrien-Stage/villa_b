<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compte du plan SYSCOHADA révisé.
 *
 * Un compte collectif (411000, 401000, 421000) centralise une catégorie de
 * tiers : le détail par client ou par fournisseur ne s'exprime jamais par un
 * compte dédié, mais par l'auxiliaire porté sur la ligne d'écriture.
 */
class Account extends Model
{
    /** Comptes collectifs de référence. */
    public const CLIENTS = '411000';
    public const SUPPLIERS = '401000';
    public const PERSONNEL = '421000';

    /** Comptes de trésorerie. */
    public const BANK = '521000';
    public const CASH = '571000';

    /** Comptes de taxes. */
    public const VAT_COLLECTED = '443100';
    public const VAT_SURTAX = '443700';
    public const VAT_DEDUCTIBLE = '445100';
    public const WITHHOLDING = '442100';
    public const TOURIST_TAX = '447100';

    /** Comptes de produits. */
    public const REVENUE_ACCOMMODATION = '706000';
    public const REVENUE_RESTAURANT = '706100';
    public const REVENUE_SHOP = '701000';

    protected $fillable = [
        'code',
        'label',
        'account_class',
        'is_collective',
        'is_postable',
        'is_active',
    ];

    protected $casts = [
        'account_class' => 'integer',
        'is_collective' => 'boolean',
        'is_postable'   => 'boolean',
        'is_active'     => 'boolean',
    ];

    /** Libellé de la classe, pour l'affichage de la balance. */
    public const CLASS_LABELS = [
        1 => 'Ressources durables',
        2 => 'Actif immobilisé',
        3 => 'Stocks',
        4 => 'Tiers',
        5 => 'Trésorerie',
        6 => 'Charges',
        7 => 'Produits',
        8 => 'Autres charges et produits',
        9 => 'Comptabilité analytique',
    ];

    public function classLabel(): string
    {
        return self::CLASS_LABELS[$this->account_class] ?? 'Classe inconnue';
    }

    /** Un compte de bilan garde son solde d'un exercice à l'autre. */
    public function isBalanceSheet(): bool
    {
        return $this->account_class <= 5;
    }

    /** Un compte de gestion repart à zéro chaque exercice. */
    public function isIncomeStatement(): bool
    {
        return in_array($this->account_class, [6, 7, 8], true);
    }

    public function scopePostable($query)
    {
        return $query->where('is_active', true)->where('is_postable', true);
    }
}
