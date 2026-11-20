<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceFiscalEvent extends Model
{
    protected $fillable = [
        'invoice_id', 'status', 'issuer_niu', 'authority_reference', 'digest', 'evidence', 'observations', 'recorded_by',
    ];

    protected $hidden = ['evidence'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
