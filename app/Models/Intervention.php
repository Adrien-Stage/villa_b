<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intervention de l'administrateur dans l'exploitation.
 *
 * L'administrateur — le service informatique — consulte tout et n'écrit que la
 * configuration et les comptes. Encaisser, valider, comptabiliser : il ne le
 * fait que pendant une intervention qu'il déclare, avec un motif, une durée
 * et les services concernés. Le manager en est prévenu, chaque action est
 * marquée au journal, et la trace part à la console d'orchestration — plus
 * tard, signalée tardive, si la console est injoignable.
 */
class Intervention extends Model
{
    public const TERMINEE = 'terminee';

    public const EXPIREE = 'expiree';

    /** Durées proposées, en minutes. */
    public const DUREES = [15, 30, 60, 120, 240];

    /**
     * Périmètres qu'une intervention peut couvrir : libellé et modules de
     * droits (premier segment du droit) qu'ils ouvrent à l'écriture.
     */
    public const PERIMETRES = [
        'hebergement' => ['Hébergement et réception', ['rooms', 'bookings', 'groups', 'customers', 'reception', 'agenda', 'invoices']],
        'housekeeping' => ['Housekeeping', ['housekeeping']],
        'restaurant' => ['Restaurant', ['restaurant']],
        'boutique' => ['Boutique', ['shop']],
        'economat' => ['Économat', ['economat']],
        'comptabilite' => ['Comptabilité et caisses', ['accounting']],
    ];

    protected $fillable = [
        'user_id', 'motif', 'perimetres', 'debut', 'fin_prevue', 'fin_reelle', 'cloture',
        'erp_a_transmettre', 'erp_transmis_at', 'erp_echecs', 'erp_tardive',
    ];

    protected $casts = [
        'perimetres' => 'array',
        'debut' => 'datetime',
        'fin_prevue' => 'datetime',
        'fin_reelle' => 'datetime',
        'erp_a_transmettre' => 'boolean',
        'erp_transmis_at' => 'datetime',
        'erp_tardive' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** En cours : ni terminée, ni arrivée au bout de sa durée. */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereNull('fin_reelle')->where('fin_prevue', '>', now());
    }

    public function estEnCours(): bool
    {
        return $this->fin_reelle === null && $this->fin_prevue->isFuture();
    }

    /** L'intervention ouvre-t-elle l'écriture sur ce droit ? */
    public function couvre(string $permission): bool
    {
        $module = explode('.', $permission)[0];

        foreach ($this->perimetres ?? [] as $perimetre) {
            if (in_array($module, self::PERIMETRES[$perimetre][1] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> libellés des périmètres */
    public function libellesPerimetres(): array
    {
        return array_values(array_map(
            static fn (string $p): string => self::PERIMETRES[$p][0] ?? $p,
            $this->perimetres ?? []
        ));
    }

    /** Clôt l'intervention ; la trace devra repartir vers la console. */
    public function clore(string $cloture): void
    {
        $this->update([
            'fin_reelle' => $cloture === self::EXPIREE ? $this->fin_prevue : now(),
            'cloture' => $cloture,
            'erp_a_transmettre' => true,
        ]);
    }

    /** Intervention en cours de cet administrateur, s'il en a une. */
    public static function enCoursPour(?User $user): ?self
    {
        if ($user === null || ! $user->isAdmin()) {
            return null;
        }

        return static::enCours()->where('user_id', $user->id)->latest('id')->first();
    }
}
