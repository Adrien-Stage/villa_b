<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * PartnerOrganization : organisation avec laquelle l'établissement a négocié
 * une convention (entreprise, ONG, ambassade, agence de voyage…).
 *
 * Porte les privilèges accordés à ses membres. Quand un client déclaré membre
 * réserve, ces privilèges lui sont appliqués automatiquement.
 *
 * Tous les montants sont en centimes FCFA, comme partout ailleurs.
 */
class PartnerOrganization extends Model
{
    use HasFactory;

    public const TYPES = [
        'company'       => 'Entreprise',
        'ngo'           => 'ONG / Association',
        'embassy'       => 'Ambassade / Organisation internationale',
        'travel_agency' => 'Agence de voyage',
        'institution'   => 'Institution publique',
        'other'         => 'Autre',
    ];

    public const DISCOUNT_NONE    = 'none';
    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_AMOUNT  = 'amount';

    protected $fillable = [
        'name', 'code', 'type',
        'contact_name', 'contact_email', 'contact_phone',
        'valid_from', 'valid_until', 'is_active',
        'room_discount_type', 'room_discount_value',
        'restaurant_discount_percent', 'shop_discount_percent',
        'free_service_item_ids',
        'late_checkout', 'early_checkin',
        'notes', 'tenant_id',
    ];

    protected $casts = [
        'valid_from'                  => 'date',
        'valid_until'                 => 'date',
        'is_active'                   => 'boolean',
        'room_discount_value'         => 'integer',
        'restaurant_discount_percent' => 'integer',
        'shop_discount_percent'       => 'integer',
        'free_service_item_ids'       => 'array',
        'late_checkout'               => 'boolean',
        'early_checkin'               => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ── Portées ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Organisations dont la convention couvre la date donnée. */
    public function scopeValidOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?: Carbon::today();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    // ── Règles métier ────────────────────────────────────────────────────────

    /**
     * La convention est-elle applicable à cette date ? Une organisation
     * désactivée ou hors fenêtre contractuelle n'accorde plus rien, mais reste
     * en base pour ne pas perdre l'historique des séjours rattachés.
     */
    public function isValidOn(?Carbon $date = null): bool
    {
        $date = $date ?: Carbon::today();

        if (!$this->is_active) {
            return false;
        }
        if ($this->valid_from && $date->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $date->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Remise sur l'hébergement pour un montant brut donné (centimes).
     *
     * @param  int  $grossRoomAmount  Total hébergement avant remise, en centimes.
     * @param  int  $nights           Nombre de nuitées (remise « au montant » = par nuitée).
     * @return int                    Remise en centimes, jamais supérieure au brut.
     */
    public function roomDiscountFor(int $grossRoomAmount, int $nights = 1): int
    {
        if ($grossRoomAmount <= 0) {
            return 0;
        }

        $discount = match ($this->room_discount_type) {
            self::DISCOUNT_PERCENT => (int) round($grossRoomAmount * min(100, $this->room_discount_value) / 100),
            self::DISCOUNT_AMOUNT  => $this->room_discount_value * max(1, $nights),
            default                => 0,
        };

        // Une remise ne peut pas rendre le séjour négatif : un montant fixe
        // négocié plus élevé que le tarif appliqué est plafonné au brut.
        return max(0, min($discount, $grossRoomAmount));
    }

    /** Prestations du catalogue offertes aux membres. */
    public function freeServiceItems(): Collection
    {
        $ids = $this->free_service_item_ids ?: [];

        if (empty($ids)) {
            return collect();
        }

        return ServiceItem::whereIn('id', $ids)->orderBy('name')->get();
    }

    public function grantsFreeServiceItem(int $serviceItemId): bool
    {
        return in_array($serviceItemId, $this->free_service_item_ids ?: [], false);
    }

    // ── Affichage ────────────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Autre';
    }

    /** Libellé lisible de la remise hébergement, pour l'interface. */
    public function roomDiscountLabel(): string
    {
        return match ($this->room_discount_type) {
            self::DISCOUNT_PERCENT => $this->room_discount_value . ' % sur l\'hébergement',
            self::DISCOUNT_AMOUNT  => number_format($this->room_discount_value / 100, 0, ',', ' ') . ' FCFA par nuitée',
            default                => 'Aucune remise hébergement',
        };
    }

    /**
     * Résumé des privilèges accordés, pour affichage en liste ou sur une
     * réservation. Renvoie des libellés courts déjà formatés.
     *
     * @return array<int, string>
     */
    public function privilegeLabels(): array
    {
        $labels = [];

        if ($this->room_discount_type !== self::DISCOUNT_NONE && $this->room_discount_value > 0) {
            $labels[] = $this->roomDiscountLabel();
        }
        if ($this->restaurant_discount_percent > 0) {
            $labels[] = $this->restaurant_discount_percent . ' % au restaurant';
        }
        if ($this->shop_discount_percent > 0) {
            $labels[] = $this->shop_discount_percent . ' % à la boutique';
        }

        $freeCount = count($this->free_service_item_ids ?: []);
        if ($freeCount > 0) {
            $labels[] = $freeCount . ' prestation' . ($freeCount > 1 ? 's' : '') . ' offerte' . ($freeCount > 1 ? 's' : '');
        }
        if ($this->late_checkout) {
            $labels[] = 'Départ tardif';
        }
        if ($this->early_checkin) {
            $labels[] = 'Arrivée anticipée';
        }

        return $labels;
    }
}
