<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une entrée de la corbeille : ce qu'un bouton « Supprimer » a effacé,
 * photographié juste avant (voir App\Services\Corbeille). Seule la console,
 * par son administrateur TECH, la lit, la restaure ou la purge.
 */
class TrashEntry extends Model
{
    protected $fillable = [
        'table_name', 'record_id', 'type', 'module', 'label', 'payload',
        'deleted_by', 'deleted_by_name', 'deleted_at', 'restored_at', 'restored_by',
    ];

    protected $casts = [
        'payload'     => 'array',
        'deleted_at'  => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeEnCorbeille(Builder $query): Builder
    {
        return $query->whereNull('restored_at');
    }

    public function scopeRestaurees(Builder $query): Builder
    {
        return $query->whereNotNull('restored_at');
    }

    /** Combien de lignes sont parties avec la fiche, et de fichiers. */
    public function resume(): array
    {
        $payload = $this->payload ?? [];

        return [
            'lignes_emportees' => array_sum(array_map(fn ($g) => count($g['lignes'] ?? []), $payload['enfants'] ?? [])),
            'liens'            => array_sum(array_map(fn ($l) => count($l['paires'] ?? []), $payload['liens'] ?? [])),
            'fichiers'         => count($payload['fichiers'] ?? []),
        ];
    }
}
