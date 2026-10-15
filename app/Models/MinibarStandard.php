<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dotation standard du mini-bar d'un type de chambre, article par article. */
class MinibarStandard extends Model
{
    protected $fillable = ['room_type_id', 'minibar_article_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(MinibarArticle::class, 'minibar_article_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
