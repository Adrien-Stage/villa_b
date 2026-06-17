<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegisterSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'module',
        'opened_at',
        'closed_at',
        'opening_amount',
        'theoretical_closing_amount',
        'actual_closing_amount',
        'discrepancy_amount',
        'notes',
        'closing_notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shopOrders()
    {
        return $this->hasMany(ShopOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'cash_register_session_id');
    }

    public function disbursements()
    {
        return $this->hasMany(CashRegisterDisbursement::class);
    }
}
