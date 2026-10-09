<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande d'un département à l'économat.
 *
 * Cycle : à viser par le chef du service → en attente de l'économat →
 * validée (ou refusée) → livrée. Le chef vise la demande d'un membre de son
 * service avant qu'elle arrive chez l'économe ; celle qu'il fait lui-même porte
 * déjà son visa. Le déstockage n'a lieu qu'à la livraison : valider n'engage
 * que l'accord de l'économe, pas encore la sortie physique des articles.
 */
class StockRequisition extends Model
{
    use HasFactory;

    public const DEPARTMENTS = [
        'hebergement'  => 'Hébergement / Réception',
        'housekeeping' => 'Housekeeping / Étages',
        'restaurant'   => 'Restauration / Cuisine & Bar',
        'boutique'     => 'Boutique / Vente',
        'comptabilite' => 'Comptabilité / Finances',
        'autre'        => 'Autre service',
    ];

    public const STATUS_AWAITING_ENDORSEMENT = 'awaiting_endorsement';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_AWAITING_ENDORSEMENT => 'À viser par le chef de service',
        self::STATUS_PENDING   => 'En attente de l\'économat',
        self::STATUS_APPROVED  => 'Validée',
        self::STATUS_REJECTED  => 'Refusée',
        self::STATUS_DELIVERED => 'Livrée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    /**
     * Rôles habilités à demander, par département. Un chef exerce aussi le
     * rôle de son équipe (RoleCatalog) : il demande pour son service.
     */
    public const DEPARTMENT_ROLES = [
        'hebergement'  => ['reception', 'manager'],
        'housekeeping' => ['housekeeping_staff', 'manager'],
        'restaurant'   => ['restaurant_cook', 'restaurant_staff', 'manager'],
        'boutique'     => ['shop_cashier', 'manager'],
        'comptabilite' => ['accountant', 'manager'],
        'autre'        => ['manager', 'econome', 'controller'],
    ];

    /**
     * Les chefs qui visent les demandes de leur service avant l'économat.
     * La direction vise pour tout service, quand le chef est absent ou que
     * le service n'en a pas.
     */
    public const CHEFS = [
        'hebergement'  => ['reception_chief'],
        'housekeeping' => ['housekeeping_leader'],
        'restaurant'   => ['restaurant_chief', 'restaurant_manager'],
        'boutique'     => ['shop_manager'],
        'comptabilite' => ['finance_manager'],
        'autre'        => ['manager'],
    ];

    protected $fillable = [
        'number', 'department', 'service_store_id', 'point_of_sale_id', 'status', 'purpose', 'review_notes',
        'requested_by', 'endorsed_by', 'endorsed_at', 'endorsement_notes',
        'reviewed_by', 'reviewed_at', 'delivered_at', 'tenant_id',
        'requester_signature',
    ];

    protected $casts = [
        'endorsed_at'  => 'datetime',
        'reviewed_at'  => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $requisition) {
            if (empty($requisition->number)) {
                $requisition->number = self::generateNumber();
            }
            if (empty($requisition->status)) {
                $requisition->status = self::STATUS_PENDING;
            }
            if (empty($requisition->requester_signature)) {
                $user = auth()->user() ?? ($requisition->requested_by ? User::find($requisition->requested_by) : null);
                if ($user) {
                    $requisition->requester_signature = method_exists($user, 'signatureName')
                        ? $user->signatureName()
                        : User::extractSignatureName($user->name);
                }
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'DM';
        $year = now()->year;
        $pattern = sprintf('%s-%d-%%', $prefix, $year);

        $last = self::withoutGlobalScopes()
            ->where('number', 'like', $pattern)
            ->orderBy('number', 'desc')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        do {
            $number = sprintf('%s-%d-%04d', $prefix, $year, $seq);
            $seq++;
        } while (self::withoutGlobalScopes()->where('number', $number)->exists());

        return $number;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(StockRequisitionLine::class);
    }

    /** Dépôt de service destinataire : la livraison y entre en stock. */
    /** Restaurant dont la cuisine reçoit la livraison. */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function serviceStore(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Le chef de service qui a visé la demande (ou l'a refusée au visa). */
    public function endorsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Les demandes qu'une personne hors de l'économat voit : les siennes, et
     * celles des services qu'elle dirige (pour un restaurant, ceux où elle
     * travaille). L'économat, la direction et le contrôle voient tout : le
     * contrôleur ne pose pas cette portée.
     */
    public function scopeVisiblesPour(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->exerce(['storekeeper', 'manager', 'controller', 'quality_auditor'])) {
            return $query;
        }

        $services = self::servicesDirigesPar($user);

        return $query->where(function (Builder $q) use ($user, $services) {
            $q->where('requested_by', $user->id);

            foreach ($services as $service) {
                $q->orWhere(function (Builder $s) use ($service, $user) {
                    $s->where('department', $service);

                    if ($service === 'restaurant') {
                        $restaurants = app(\App\Services\RestaurantContext::class)->accessibles($user)->pluck('id');
                        $s->where(fn (Builder $r) => $r->whereNull('point_of_sale_id')->orWhereIn('point_of_sale_id', $restaurants));
                    }
                });
            }
        });
    }

    /** @return list<string> services dont cette personne vise les demandes */
    public static function servicesDirigesPar(User $user): array
    {
        if ($user->exerce(['manager'])) {
            return array_keys(self::DEPARTMENTS);
        }

        return array_keys(array_filter(self::CHEFS, fn (array $chefs) => $user->exerce($chefs)));
    }

    /**
     * Cette demande, faite par cette personne pour ce service, doit-elle être
     * visée ? Non quand le demandeur est le chef du service, l'économe ou la
     * direction : sa demande porte déjà le visa.
     */
    public static function visaRequis(User $demandeur, string $department): bool
    {
        return !$demandeur->exerce(['econome', 'manager'])
            && !$demandeur->exerce(self::CHEFS[$department] ?? ['manager']);
    }

    /** Cette personne peut-elle viser (ou refuser au visa) cette demande ? */
    public function peutEtreViseePar(User $user): bool
    {
        if (!$this->canBeEndorsed() || $user->id === $this->requested_by) {
            return false;
        }

        if ($user->exerce(['manager'])) {
            return true;
        }

        if (!$user->exerce(self::CHEFS[$this->department] ?? ['manager'])) {
            return false;
        }

        // Un chef de restaurant vise pour les restaurants où il travaille.
        return $this->department !== 'restaurant'
            || $this->point_of_sale_id === null
            || app(\App\Services\RestaurantContext::class)->accessibles($user)->contains('id', $this->point_of_sale_id);
    }

    // ── Règles de cycle ──────────────────────────────────────────────────────

    public function canBeEndorsed(): bool
    {
        return $this->status === self::STATUS_AWAITING_ENDORSEMENT;
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** La livraison n'est possible qu'une fois la demande validée. */
    public function canBeDelivered(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_AWAITING_ENDORSEMENT, self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    /** Refusée au visa du chef, avant d'arriver à l'économat. */
    public function refuseeAuVisa(): bool
    {
        return $this->status === self::STATUS_REJECTED && $this->reviewed_by === null && $this->endorsed_by !== null;
    }

    /**
     * Libellés exposés en attributs, pour que les vues et les documents y
     * accèdent par leur clé — « department_label » — sans appeler de méthode.
     * Ils délèguent aux méthodes existantes : un seul endroit décide du texte.
     */
    // Forme héritée volontairement : la forme moderne exigerait une méthode
    // nommée departmentLabel(), déjà prise par la méthode publique ci-dessous.
    public function getDepartmentLabelAttribute(): string
    {
        return $this->departmentLabel();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusLabel();
    }

    public function departmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? $this->department;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Le stock permet-il de servir intégralement la demande ? */
    public function isFullyServiceable(): bool
    {
        return $this->lines()->with('item')->get()->every(
            fn (StockRequisitionLine $l) => $l->item
                && (float) $l->item->current_stock >= (float) $l->quantity_requested
        );
    }

    /** Montant total valorisé au CUMP des articles demandés (centimes FCFA). */
    public function totalRequestedCost(): int
    {
        return (int) $this->lines->sum(fn ($l) => $l->totalRequestedCost());
    }

    /** Montant total valorisé au CUMP des articles réellement servis (centimes FCFA). */
    public function totalIssuedCost(): int
    {
        return (int) $this->lines->sum(fn ($l) => $l->totalIssuedCost());
    }

    /**
     * Nom extrait pour la signature automatique du demandeur.
     */
    public function requesterSignature(): ?string
    {
        if (!empty($this->requester_signature)) {
            return $this->requester_signature;
        }

        if ($this->requestedBy) {
            return method_exists($this->requestedBy, 'signatureName')
                ? $this->requestedBy->signatureName()
                : User::extractSignatureName($this->requestedBy->name);
        }

        return null;
    }
}
