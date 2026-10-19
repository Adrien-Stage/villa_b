<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une spécialité d'un technicien (MaintenanceRequest::SPECIALTIES). */
class TechnicianSpecialty extends Model
{
    protected $fillable = ['user_id', 'specialty'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
