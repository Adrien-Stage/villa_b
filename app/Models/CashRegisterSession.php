<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegisterSession extends Model
{
    protected $fillable = [
        'user_id',
        'module',
        'status',
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

    /**
     * Solde théorique du tiroir, en centimes : fond initial, plus les
     * encaissements en espèces de la session, moins les décaissements.
     *
     * Calculé ici et nulle part ailleurs. L'écart de caisse est la pièce
     * qui met en cause une personne : le terme auquel on compare son
     * comptage ne peut pas venir du formulaire qu'elle envoie, sinon il
     * suffit de déclarer un théorique égal au comptage pour afficher un
     * écart nul. L'écran de clôture et l'enregistrement de la clôture
     * appellent donc la même méthode.
     */
    public function theoreticalBalance(): int
    {
        $total = (int) $this->opening_amount;

        $total += $this->module === 'shop'
            ? (int) $this->shopOrders()
                ->where('payment_method', 'cash')
                ->where('payment_status', 'paid')
                ->sum('total_amount')
            : (int) $this->payments()
                ->where('method', 'cash')
                ->where('status', 'completed')
                ->sum('amount');

        return $total - (int) $this->disbursements()->sum('amount');
    }

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

    public function receptionSales()
    {
        return $this->hasMany(ReceptionSale::class);
    }
}
