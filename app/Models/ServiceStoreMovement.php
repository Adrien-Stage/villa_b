<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal des mouvements d'un dépôt de service : source de vérité de son stock. */
class ServiceStoreMovement extends Model
{
    public const TYPE_IN         = 'in';
    public const TYPE_OUT        = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_REQUISITION = 'requisition';
    public const SOURCE_STOCK_COUNT = 'stock_count';

    /** Sortie vers le mini-bar d'une chambre (réapprovisionnement). */
    public const SOURCE_MINIBAR = 'minibar';

    /** Livraison, stock initial ou ajustement venus d'un classeur Excel importé. */
    public const SOURCE_IMPORT = 'import';

    /** Sortie remise à un bénéficiaire (chantier, artisan…) : consommée, elle porte sa charge. */
    public const SOURCE_BENEFICIAIRE = 'beneficiaire';

    /** Retour en stock de ce qu'un bénéficiaire n'a pas employé : la charge s'annule. */
    public const SOURCE_RETOUR = 'retour';

    /** Pièce employée par une intervention technique (MaintenanceRequest) : charge du service. */
    public const SOURCE_INTERVENTION = 'intervention';

    /** Bon de transfert du magasin central : l'économat approvisionne son propre dépôt. */
    public const SOURCE_TRANSFER = 'transfer';

    public const SOURCES = [
        self::SOURCE_REQUISITION  => "Livraison de l'économat",
        self::SOURCE_STOCK_COUNT  => 'Inventaire du dépôt',
        self::SOURCE_MINIBAR      => 'Mini-bar',
        self::SOURCE_IMPORT       => 'Import Excel',
        self::SOURCE_BENEFICIAIRE => 'Sortie à un bénéficiaire',
        self::SOURCE_RETOUR       => 'Retour en stock',
        self::SOURCE_INTERVENTION => 'Intervention technique',
        self::SOURCE_TRANSFER     => 'Transfert du magasin central',
    ];

    public const TYPES = [
        self::TYPE_IN         => 'Entrée',
        self::TYPE_OUT        => 'Sortie',
        self::TYPE_ADJUSTMENT => 'Inventaire',
    ];

    protected $fillable = [
        'service_store_id', 'stock_item_id', 'type', 'quantity', 'stock_after', 'unit_cost',
        'stock_account', 'source_type', 'source_id', 'reason', 'user_id', 'occurred_at',
        'stock_import_id', 'details', 'import_key',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'stock_after' => 'decimal:3',
        'unit_cost'   => 'integer',
        'occurred_at' => 'datetime',
        'details'     => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockImport(): BelongsTo
    {
        return $this->belongsTo(StockImport::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source_type] ?? (string) $this->source_type;
    }
}
