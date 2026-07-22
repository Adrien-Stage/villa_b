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

    protected $fillable = [
        'name', 'code', 'contact_name', 'email', 'phone',
        'address', 'notes', 'is_active', 'tenant_id',
    ];

    protected $casts = ['is_active' => 'boolean'];

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
}
