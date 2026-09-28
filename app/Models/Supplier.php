<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fournisseur de l'économat. Son email conditionne l'envoi automatique des
 * bons de commande.
 */
class Supplier extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'food'        => 'Alimentation & Vivres frais',
        'beverage'    => 'Boissons & Brasseries',
        'hygiene'     => 'Hygiène, Entretien & Accueil',
        'linen'       => 'Linge, Literie & Blanchisserie',
        'office'      => 'Bureautique & Papeterie',
        'maintenance' => 'Maintenance & Quincaillerie',
        'services'    => 'Prestations & Sous-traitance',
        'other'       => 'Autre / Divers',
    ];

    public const PAYMENT_TERMS = [
        'cash'        => 'Comptant à la livraison',
        '15_days'     => '15 jours',
        '30_days'     => '30 jours',
        '30_days_eom' => '30 jours fin de mois',
        '60_days'     => '60 jours',
    ];

    public const PAYMENT_METHODS = [
        'transfer'     => 'Virement bancaire',
        'check'        => 'Chèque',
        'cash'         => 'Espèces',
        'mobile_money' => 'Mobile Money (MTN / Orange)',
    ];

    protected $fillable = [
        'name', 'code', 'category', 'tax_id', 'rccm',
        'contact_name', 'email', 'phone', 'address', 'city',
        'payment_terms', 'payment_method', 'delivery_lead_time_days',
        'bank_details', 'notes', 'is_active', 'tenant_id',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'delivery_lead_time_days' => 'integer',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Un bon ne peut partir que si le fournisseur a une adresse email. */
    public function canReceiveOrdersByEmail(): bool
    {
        return !empty($this->email);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: 'Non classé');
    }

    public function getPaymentTermsLabelAttribute(): string
    {
        return self::PAYMENT_TERMS[$this->payment_terms] ?? ($this->payment_terms ?: 'Non défini');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ($this->payment_method ?: 'Non défini');
    }
}
