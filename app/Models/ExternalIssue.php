<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de sortie hors établissement : un matériel qui quitte le magasin sans
 * servir l'hôtel (prêt, réparation, don, cession, transfert, restitution).
 *
 * Il garde qui l'a emporté et signe en son nom ; l'économe le valide en
 * l'enregistrant, ce qui déstocke le magasin au coût moyen.
 */
class ExternalIssue extends Model
{
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_VALIDATED => 'Sortie validée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    /** Pourquoi le matériel quitte l'établissement. */
    public const REASONS = [
        'pret'                => 'Prêt',
        'reparation'          => 'Envoi en réparation à l\'extérieur',
        'don'                 => 'Don',
        'cession'             => 'Cession ou vente',
        'transfert'           => 'Transfert vers un autre établissement',
        'restitution'         => 'Restitution à son propriétaire',
        'autre'               => 'Autre',
    ];

    /** Motifs où le matériel doit revenir : la date de retour prévue a un sens. */
    public const REASONS_AVEC_RETOUR = ['pret', 'reparation'];

    protected $fillable = [
        'number', 'reason', 'status', 'issued_at',
        'beneficiary_name', 'beneficiary_organisation', 'beneficiary_phone', 'beneficiary_id_document', 'beneficiary_signature',
        'expected_return_at', 'notes', 'total_value',
        'issued_by', 'issuer_signature',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
        'tenant_id',
    ];

    protected $casts = [
        'issued_at'          => 'datetime',
        'expected_return_at' => 'date',
        'cancelled_at'       => 'datetime',
        'total_value'        => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sortie) {
            $sortie->number ??= self::generateNumber();
            $sortie->status ??= self::STATUS_VALIDATED;
            $sortie->issued_at ??= now();
            // La signature du bon : celle de la personne qui emporte le
            // matériel, tirée de son nom, comme pour un demandeur interne.
            $sortie->beneficiary_signature ??= User::extractSignatureName($sortie->beneficiary_name);
        });
    }

    public static function generateNumber(): string
    {
        $prefixe = sprintf('BSE-%d-', now()->year);

        $dernier = self::query()->where('number', 'like', $prefixe . '%')->orderByDesc('number')->value('number');
        $suite = $dernier && preg_match('/-(\d+)$/', $dernier, $m) ? ((int) $m[1]) + 1 : 1;

        do {
            $numero = $prefixe . str_pad((string) $suite++, 4, '0', STR_PAD_LEFT);
        } while (self::query()->where('number', $numero)->exists());

        return $numero;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExternalIssueLine::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeValidees(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Un matériel prêté ou en réparation dont la date de retour est passée. */
    public function retourEnRetard(): bool
    {
        return !$this->isCancelled()
            && $this->expected_return_at !== null
            && $this->expected_return_at->isBefore(today());
    }

    /** Pour les documents exportés : « nom — structure ». */
    public function getBeneficiaireAttribute(): string
    {
        return $this->beneficiary_name . ($this->beneficiary_organisation ? ' — ' . $this->beneficiary_organisation : '');
    }
}
