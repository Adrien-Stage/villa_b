<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegisterDisbursement extends Model
{
    protected $fillable = [
        'cash_register_session_id',
        'user_id',
        'amount',
        'reason',
    ];

    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
