<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bordereau de livraison joint à un bon de réception : un PDF, une photo.
 * Plusieurs par livraison. Rangé hors de l'espace public, il ne s'ouvre
 * qu'à qui consulte les bons d'entrée ; supprimé, il part à la corbeille.
 */
class GoodsReceiptAttachment extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    /** PDF et images, photos de téléphone comprises. */
    public const TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];

    /** En kilo-octets : 10 Mo par fichier. */
    public const TAILLE_MAX = 10240;

    /** Fichiers joints en une fois. */
    public const NOMBRE_MAX = 10;

    protected $fillable = [
        'goods_receipt_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function estUneImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function taille(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', ' ') . ' Mo'
            : max(1, (int) round($this->size / 1024)) . ' Ko';
    }

    /** Ce que la corbeille affiche pour ce bordereau. */
    public function libelleCorbeille(): string
    {
        return $this->original_name . ($this->receipt?->number ? ' — ' . $this->receipt->number : '');
    }
}
