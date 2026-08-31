<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant représente un établissement de l'ONG.
 * 
 * Architecture : Un tenant = un établissement physique (Villa Boutanga, 
 * futurs établissements). Toutes les données sont isolées par tenant_id.
 * 
 * @property string $name Nom de l'établissement
 * @property string $slug Identifiant unique URL-friendly
 * @property array $settings Configuration JSON par établissement
 * @property string $currency Devise par défaut (XAF/FCFA pour le Cameroun)
 */

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'settings',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array', // PostgreSQL JSON column
        'is_active' => 'boolean',
    ];

    /**
     * L'établissement de cette instance.
     *
     * Une base ne contient qu'un établissement : il n'y a donc rien à choisir.
     * Passer par les colonnes `tenant_id` ne fonctionne pas — héritées d'un
     * modèle où toutes les filiales partageaient une base, elles ne sont jamais
     * renseignées, et `$user->tenant` ou `$booking->tenant` valent toujours
     * null. Les déréférencer produisait des erreurs 500 en cascade.
     *
     * Volontairement non mémoïsé. Un cache statique paraissait gratuit — la
     * table n'a qu'une ligne — mais il survit à la requête : en test il rendait
     * un établissement détruit entre-temps, et dans un worker de file il
     * servirait indéfiniment une raison sociale périmée. Une lecture sur clé
     * primaire d'une table à une ligne ne justifie pas ce risque.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * Les utilisateurs de cet établissement
     * Relation : Un tenant a plusieurs users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Les chambres de cet établissement
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Les réservations de cet établissement
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Les clients de cet établissement
     * Note : Un client peut être partagé entre tenants en V2, 
     * mais pour l'MVP, isolation stricte.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
