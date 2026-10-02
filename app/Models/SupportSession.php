<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session du support de l'éditeur, ouverte par le mode assistance de la
 * console sous le compte technique « Support Wetchah ». L'hôtel les voit
 * toutes : qui est entré, quand, et combien de temps.
 */
class SupportSession extends Model
{
    protected $fillable = ['user_id', 'technicien', 'reference', 'debut', 'fin', 'ip_address'];

    protected $casts = [
        'debut' => 'datetime',
        'fin' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
