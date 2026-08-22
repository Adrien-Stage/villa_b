<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * BookingDraft : Session de réservation en cours.
 *
 * Un brouillon représente le travail en cours d'un agent qui n'a pas encore
 * finalisé une réservation. Les données partielles y sont sauvegardées
 * automatiquement à chaque étape afin de permettre la reprise de session.
 *
 * Cycle de vie :
 *   active → (wizard validé) → completed (supprimé en pratique ou conservé en log)
 *   active → (inactivité) → abandoned (nettoyé par scheduled task)
 */
class BookingDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'created_by',
        'tenant_id',
        'current_step',
        // Étape 1
        'customer_id',
        'booker_id',
        // Étape 2
        'check_in',
        'check_out',
        'check_in_time',
        'adults',
        'children',
        'source',
        // Étape 3
        'room_id',
        // Global
        'notes',
        'expires_at',
        'last_activity_at',
        'status',
    ];

    protected $casts = [
        'check_in'          => 'date',
        'check_out'         => 'date',
        'expires_at'        => 'datetime',
        'last_activity_at'  => 'datetime',
        'adults'            => 'integer',
        'children'          => 'integer',
        'current_step'      => 'integer',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $draft) {
            if (empty($draft->token)) {
                $draft->token = Str::random(48);
            }
            if (empty($draft->last_activity_at)) {
                $draft->last_activity_at = now();
            }
            // Expiration par défaut : 48 heures
            if (empty($draft->expires_at)) {
                $draft->expires_at = now()->addHours(48);
            }
        });

        static::updating(function (self $draft) {
            $draft->last_activity_at = now();
            // Proroger l'expiration à chaque activité
            if ($draft->expires_at && $draft->expires_at->isPast()) {
                $draft->expires_at = now()->addHours(48);
            }
        });
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'booker_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Brouillons actifs, non expirés. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(fn ($q) => $q->whereNull('expires_at')
                                          ->orWhere('expires_at', '>', now()));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Label de l'étape courante.
     */
    public function stepLabel(): string
    {
        return match ($this->current_step) {
            1 => 'Sélection du client',
            2 => 'Dates & informations',
            3 => 'Chambre sélectionnée',
            4 => 'Confirmation & paiement',
            default => 'Brouillon',
        };
    }

    /**
     * L'URL de reprise de session pour l'étape courante.
     */
    public function resumeUrl(): string
    {
        return match ($this->current_step) {
            1, 2 => route('bookings.draft.resume', $this->token),
            3    => route('bookings.draft.resume', $this->token),
            4    => route('bookings.draft.resume', $this->token),
            default => route('bookings.draft.resume', $this->token),
        };
    }

    /**
     * Marque le brouillon comme terminé (réservation créée).
     */
    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Marque le brouillon comme abandonné.
     */
    public function markAbandoned(): void
    {
        $this->update(['status' => 'abandoned']);
    }

    /**
     * Crée un nouveau brouillon ou met à jour le brouillon existant identifié
     * par $token appartenant à $userId. Si $token est vide ou introuvable,
     * un nouveau brouillon est créé.
     *
     * @param  string|null  $token   Token transmis depuis la vue
     * @param  int          $userId  ID de l'agent connecté
     * @param  array        $data    Données partielles à persister
     */
    public static function upsertDraft(?string $token, int $userId, array $data): self
    {
        if ($token) {
            $existing = self::active()
                ->where('token', $token)
                ->where('created_by', $userId)
                ->first();

            if ($existing) {
                $existing->update($data);
                return $existing->fresh();
            }
        }

        return self::create(array_merge($data, ['created_by' => $userId]));
    }

    /**
     * Résumé textuel du brouillon pour l'affichage dans la liste.
     */

    public function summary(): string
    {
        $parts = [];

        if ($this->customer) {
            $parts[] = $this->customer->full_name;
        }

        if ($this->check_in && $this->check_out) {
            $parts[] = \Carbon\Carbon::parse($this->check_in)->locale('fr')->isoFormat('D MMM')
                . ' → '
                . \Carbon\Carbon::parse($this->check_out)->locale('fr')->isoFormat('D MMM YYYY');
        }

        if ($this->room) {
            $parts[] = 'Chambre ' . $this->room->number;
        }

        return implode(' · ', $parts) ?: 'Brouillon vide';
    }
}
