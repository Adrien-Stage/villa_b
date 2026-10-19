<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Import d'un classeur Excel de l'économat (voir ClasseurStockImport).
 *
 * Cycle : draft (fichier déposé, aperçu affiché, rien d'écrit) → applied
 * (articles et mouvements enregistrés). Le fichier et son analyse restent :
 * on sait d'où vient chaque mouvement importé.
 */
class StockImport extends Model
{
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [
        self::STATUS_DRAFT   => 'Aperçu, à confirmer',
        self::STATUS_APPLIED => 'Importé',
    ];

    protected $fillable = [
        'reference', 'service_store_id', 'status', 'file_name', 'file_path', 'analysis',
        'created_by', 'applied_by', 'applied_at',
    ];

    protected $casts = [
        'analysis'   => 'array',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $import) {
            $import->reference ??= self::nouvelleReference();
            $import->status ??= self::STATUS_DRAFT;
        });
    }

    public static function nouvelleReference(): string
    {
        $annee = now()->year;
        $derniere = self::query()->where('reference', 'like', "IMP-{$annee}-%")->orderByDesc('reference')->value('reference');
        $rang = $derniere && preg_match('/-(\d+)$/', $derniere, $m) ? (int) $m[1] + 1 : 1;

        do {
            $reference = sprintf('IMP-%d-%04d', $annee, $rang++);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function storeMovements(): HasMany
    {
        return $this->hasMany(ServiceStoreMovement::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Le magasin visé, tel qu'on le nomme. */
    public function magasinLabel(): string
    {
        return $this->store ? 'Dépôt « ' . $this->store->name . ' »' : 'Magasin central (économat)';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
