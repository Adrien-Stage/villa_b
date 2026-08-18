<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constat de clôture d'une journée d'exploitation.
 *
 * Les totaux sont figés à la clôture, jamais recalculés : le night audit est
 * un constat daté. Une correction ultérieure du grand livre ne doit pas
 * réécrire l'histoire de ce qui a été constaté ce soir-là.
 */
class NightAudit extends Model
{
    protected $fillable = [
        'audit_date',
        'closed_at',
        'closed_by',
        'revenue_accommodation',
        'revenue_restaurant',
        'revenue_shop',
        'revenue_total',
        'cash_collected',
        'cash_discrepancy',
        'registers_closed',
        'registers_left_open',
        'entries_posted',
        'notes',
    ];

    protected $casts = [
        'audit_date'            => 'date',
        'closed_at'             => 'datetime',
        'revenue_accommodation' => 'integer',
        'revenue_restaurant'    => 'integer',
        'revenue_shop'          => 'integer',
        'revenue_total'         => 'integer',
        'cash_collected'        => 'integer',
        'cash_discrepancy'      => 'integer',
        'registers_closed'      => 'integer',
        'registers_left_open'   => 'integer',
        'entries_posted'        => 'integer',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Cette journée a-t-elle été clôturée ? */
    public static function isClosed(CarbonInterface $date): bool
    {
        return static::query()->whereDate('audit_date', $date->toDateString())->exists();
    }

    /** Dernière journée clôturée. */
    public static function lastClosedDate(): ?\Illuminate\Support\Carbon
    {
        return static::query()->orderByDesc('audit_date')->value('audit_date');
    }

    /** Un écart de caisse a-t-il été constaté ce jour-là ? */
    public function hasDiscrepancy(): bool
    {
        return $this->cash_discrepancy !== 0;
    }

    /** Des caisses sont restées ouvertes à la clôture. */
    public function hasOpenRegisters(): bool
    {
        return $this->registers_left_open > 0;
    }
}
