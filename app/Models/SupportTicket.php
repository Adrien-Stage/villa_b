<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remarque ou suggestion remontée par un membre du personnel vers le support
 * technique. Lue et traitée depuis l'ERP, qui écrit en retour le statut et sa
 * réponse dans cette même ligne.
 */
class SupportTicket extends Model
{
    public const TYPES = ['probleme', 'suggestion'];

    public const STATUTS = ['nouveau', 'en_cours', 'resolu', 'rejete'];

    protected $fillable = [
        'user_id',
        'author_name',
        'author_role',
        'type',
        'subject',
        'message',
        'context_url',
        'status',
        'reply',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Libellé lisible du statut, partagé par l'application et ses vues. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'en_cours' => 'En cours de traitement',
            'resolu'   => 'Résolu',
            'rejete'   => 'Écarté',
            default    => 'Reçu',
        };
    }
}
