<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande d'intervention technique (maintenance).
 *
 * Cycle : open (signalée) → assigned (affectée à un technicien) →
 * in_progress (en cours) → done (terminée, avec compte rendu). Le demandeur
 * peut rouvrir une demande terminée pendant DELAI_REOUVERTURE jours ; elle
 * repart chez le technicien. Une demande non commencée peut être annulée.
 *
 * Distincte d'Intervention, qui trace les interventions de l'administrateur
 * informatique dans l'exploitation.
 */
class MaintenanceRequest extends Model
{
    public const STATUS_OPEN        = 'open';
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN        => 'Signalée',
        self::STATUS_ASSIGNED    => 'Affectée',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_DONE        => 'Terminée',
        self::STATUS_CANCELLED   => 'Annulée',
    ];

    /** Demandes encore à traiter. */
    public const ACTIVES = [self::STATUS_OPEN, self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS];

    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_LOW    = 'low';

    public const PRIORITIES = [
        self::PRIORITY_URGENT => 'Urgente',
        self::PRIORITY_HIGH   => 'Haute',
        self::PRIORITY_NORMAL => 'Normale',
        self::PRIORITY_LOW    => 'Basse',
    ];

    /** Ordre de traitement : l'urgent d'abord. */
    public const RANG_PRIORITE = [self::PRIORITY_URGENT => 0, self::PRIORITY_HIGH => 1, self::PRIORITY_NORMAL => 2, self::PRIORITY_LOW => 3];

    public const SPECIALTIES = [
        'plomberie'    => 'Plomberie',
        'cfo'          => 'Électricité courant fort (CFO)',
        'cfa'          => 'Électricité courant faible (CFA)',
        'froid'        => 'Froid',
        'climatisation' => 'Climatisation & ventilation',
        'menuiserie'   => 'Menuiserie',
        'serrurerie'   => 'Serrurerie',
        'maconnerie'   => 'Maçonnerie & peinture',
        'cuisine'      => 'Équipements de cuisine & buanderie',
        'autre'        => 'Autre / à qualifier',
    ];

    public const SOURCE_MANUAL       = 'manual';
    public const SOURCE_HOUSEKEEPING = 'housekeeping';

    /** Jours pendant lesquels le demandeur peut rouvrir une demande terminée. */
    public const DELAI_REOUVERTURE = 7;

    protected $fillable = [
        'reference', 'status', 'priority', 'specialty', 'room_id', 'location', 'title', 'description',
        'department', 'source', 'housekeeping_assignment_id', 'requested_by', 'room_blocked',
        'assigned_to', 'assigned_by', 'assigned_at', 'started_at',
        'completed_by', 'completed_at', 'resolution', 'reopened_count',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'room_blocked'   => 'boolean',
        'reopened_count' => 'integer',
        'assigned_at'    => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $demande) {
            $demande->reference ??= self::nouvelleReference();
        });
    }

    public static function nouvelleReference(): string
    {
        $annee = now()->year;
        $derniere = self::query()->where('reference', 'like', "DIT-{$annee}-%")->orderByDesc('reference')->value('reference');
        $rang = $derniere && preg_match('/-(\d+)$/', $derniere, $m) ? (int) $m[1] + 1 : 1;

        do {
            $reference = sprintf('DIT-%d-%04d', $annee, $rang++);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    // ── Relations ──

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function housekeepingAssignment(): BelongsTo
    {
        return $this->belongsTo(HousekeepingAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceRequestEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** Les pièces sorties du dépôt technique pour cette intervention. */
    public function parts(): HasMany
    {
        return $this->hasMany(ServiceStoreMovement::class, 'source_id')
            ->where('source_type', ServiceStoreMovement::SOURCE_INTERVENTION);
    }

    // ── Portées ──

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVES);
    }

    /** L'urgent d'abord, puis le plus ancien. */
    public function scopeParPriorite(Builder $query): Builder
    {
        $cas = collect(self::RANG_PRIORITE)->map(fn ($rang, $cle) => "WHEN '{$cle}' THEN {$rang}")->implode(' ');

        return $query->orderByRaw("CASE priority {$cas} ELSE 9 END")->orderBy('created_at');
    }

    // ── Lecture ──

    public function lieu(): string
    {
        return $this->room ? 'Chambre ' . $this->room->number . ($this->location ? ' — ' . $this->location : '') : ($this->location ?: '—');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function specialtyLabel(): string
    {
        return self::SPECIALTIES[$this->specialty] ?? $this->specialty;
    }

    public function departmentLabel(): string
    {
        return StockRequisition::DEPARTMENTS[$this->department] ?? $this->department;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVES, true);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    /** Terminée depuis moins de DELAI_REOUVERTURE jours. */
    public function peutEtreRouverte(): bool
    {
        return $this->isDone() && $this->completed_at !== null
            && $this->completed_at->greaterThan(now()->subDays(self::DELAI_REOUVERTURE));
    }

    /** Annulable tant qu'aucun technicien n'a commencé. */
    public function peutEtreAnnulee(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ASSIGNED], true);
    }

    /** Valeur des pièces employées, en centimes. */
    public function valeurDesPieces(): int
    {
        return (int) $this->parts->sum(fn (ServiceStoreMovement $m) => round(abs((float) $m->quantity) * $m->unit_cost));
    }

    /** Durée de l'intervention (début → fin), en minutes. */
    public function dureeMinutes(): ?int
    {
        return $this->started_at && $this->completed_at ? (int) $this->started_at->diffInMinutes($this->completed_at) : null;
    }
}
