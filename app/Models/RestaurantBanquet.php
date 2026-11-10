<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Un banquet : un événement réservé dans l'un des restaurants — date, salle,
 * client, nombre de couverts, menu, acompte —, réalisé par sa cuisine et
 * réglé à sa caisse.
 */
class RestaurantBanquet extends Model
{
    use AppartientAUnRestaurant;

    public const DEVIS = 'devis';

    public const CONFIRME = 'confirme';

    public const REALISE = 'realise';

    public const SOLDE = 'solde';

    public const ANNULE = 'annule';

    public const SOURCE_SUR_PLACE = 'sur_place';

    public const SOURCE_TELEPHONE = 'telephone';

    public const SOURCE_EMAIL = 'email';

    public const SOURCE_RESTAURANT = 'restaurant';

    public const SOURCE_SITE_WEB = 'site_web';

    public const SOURCES = [
        self::SOURCE_SUR_PLACE => 'Sur place (Réception)',
        self::SOURCE_TELEPHONE => 'Téléphone',
        self::SOURCE_EMAIL => 'Email',
        self::SOURCE_RESTAURANT => 'Restaurant',
        self::SOURCE_SITE_WEB => 'Site web',
    ];

    public const STATUTS = [
        self::DEVIS => 'Devis',
        self::CONFIRME => 'Confirmé',
        self::REALISE => 'Réalisé',
        self::SOLDE => 'Soldé',
        self::ANNULE => 'Annulé',
    ];

    public const MENU_SECTIONS = [
        'aperitif' => 'Cocktail / Mise en bouche',
        'entree' => 'Entrée',
        'plat' => 'Plat principal',
        'accompagnement' => 'Accompagnement',
        'dessert' => 'Dessert',
        'boisson' => 'Boissons',
        'autre' => 'Autre / Prestation',
    ];

    protected $fillable = [
        'point_of_sale_id', 'reference', 'title', 'customer_id', 'booking_id', 'client_name', 'client_phone', 'client_email',
        'event_date', 'start_time', 'end_time', 'space_id', 'covers', 'price_per_cover', 'extras_amount',
        'total_amount', 'deposit_required', 'menu', 'menu_items', 'status', 'source', 'notes', 'created_by', 'confirmed_at', 'canceled_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'covers' => 'integer',
        'price_per_cover' => 'integer',
        'extras_amount' => 'integer',
        'total_amount' => 'integer',
        'deposit_required' => 'integer',
        'menu_items' => 'array',
        'confirmed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(RestaurantBanquetPayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Montant dû : couverts au prix convenu, plus les suppléments. */
    public static function totalPour(int $couverts, int $prixParCouvert, int $supplements = 0): int
    {
        return $couverts * $prixParCouvert + $supplements;
    }

    public function encaisse(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function resteDu(): int
    {
        return max(0, $this->total_amount - $this->encaisse());
    }

    public function depositRemaining(): int
    {
        return max(0, (int) $this->deposit_required - $this->encaisse());
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->status] ?? $this->status;
    }

    public function libelleSource(): string
    {
        return self::SOURCES[$this->source ?? self::SOURCE_RESTAURANT] ?? ucfirst((string) $this->source);
    }

    public function estModifiable(): bool
    {
        return in_array($this->status, [self::DEVIS, self::CONFIRME], true);
    }

    /**
     * Regroupe les éléments structurés du menu par temps de service dans l'ordre du catalogue.
     *
     * @return Collection<int, array{course: string, label: string, items: array<int, array<string, mixed>>}>
     */
    public function groupedMenuItems(): Collection
    {
        $raw = is_array($this->menu_items) ? $this->menu_items : [];
        if ($raw === []) {
            return collect();
        }

        $grouped = [];
        foreach (self::MENU_SECTIONS as $code => $label) {
            $items = array_values(array_filter(
                $raw,
                fn ($row) => is_array($row) && ($row['course'] ?? 'plat') === $code && trim((string) ($row['name'] ?? '')) !== ''
            ));
            if ($items !== []) {
                $grouped[] = [
                    'course' => $code,
                    'label' => $label,
                    'items' => $items,
                ];
            }
        }

        return collect($grouped);
    }

    /** Somme indicative par couvert des éléments structurés du menu (en centimes). */
    public function menuItemsUnitTotal(): int
    {
        $raw = is_array($this->menu_items) ? $this->menu_items : [];

        return (int) array_sum(array_map(
            fn ($row) => is_array($row) ? max(0, (int) ($row['unit_price'] ?? 0)) : 0,
            $raw
        ));
    }

    /**
     * Génère une synthèse textuelle lisible du menu structuré (utile en repli si la colonne JSON n'existe pas encore).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function formatMenuText(array $items): string
    {
        $lines = [];
        foreach (self::MENU_SECTIONS as $code => $label) {
            $sectionItems = array_values(array_filter(
                $items,
                fn ($row) => is_array($row) && ($row['course'] ?? 'plat') === $code && trim((string) ($row['name'] ?? '')) !== ''
            ));
            if ($sectionItems === []) {
                continue;
            }
            $lines[] = $label . ' :';
            foreach ($sectionItems as $row) {
                $line = '- ' . trim((string) $row['name']);
                $note = trim((string) ($row['notes'] ?? ''));
                if ($note !== '') {
                    $line .= ' (' . $note . ')';
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
