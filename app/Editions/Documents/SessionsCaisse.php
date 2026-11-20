<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les sessions de caisse de la période : fonds, théorique, compté, écart, contresignature. */
class SessionsCaisse extends Edition
{
    private const MODULES = ['reception' => 'Réception', 'shop' => 'Boutique', 'restaurant' => 'Restaurant'];

    public function cle(): string { return 'sessions-caisse'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Sessions de caisse et écarts'; }

    public function description(): string
    {
        return "Chaque session de caisse ouverte sur la période : qui, quand, le fonds de départ, ce que le système attendait, ce qui a été compté, l'écart et qui l'a contresigné.";
    }

    public function droits(): array { return ['accounting.cash_reviews', 'accounting.cash']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('jour'),
            Filtre::choix('caisse', 'Caisse', fn () => self::MODULES, 'Toutes les caisses'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $points = PointOfSale::query()->pluck('name', 'id');
        $journee = $valeurs['etablissement'] ?? false;
        $fin = $au->endOfDay();

        $sessions = CashRegisterSession::query()
            ->with(['user:id,name', 'witness:id,name'])
            ->when($journee,
                fn ($q) => $q->where('opened_at', '<=', $fin)
                    ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $du->startOfDay())),
                fn ($q) => $q->whereBetween('opened_at', [$du->startOfDay(), $fin]),
            )
            ->when($valeurs['caisse'] !== '', fn ($q) => $q->where('module', $valeurs['caisse']))
            ->orderBy('opened_at')
            ->get()
            ->map(function (CashRegisterSession $s) use ($points, $journee, $fin) {
                $fermee = $s->closed_at && (! $journee || $s->closed_at->lessThanOrEqualTo($fin));

                return [
                    'caisse' => (self::MODULES[$s->module] ?? ucfirst((string) $s->module))
                        . ($s->module === 'restaurant' && $s->point_of_sale_id
                            ? ' — ' . ($points[$s->point_of_sale_id] ?? '') : ''),
                    'caissier' => $s->user?->name ?? '—',
                    'ouverture' => $s->opened_at,
                    'cloture' => $fermee ? $s->closed_at : null,
                    'fonds' => (int) $s->opening_amount,
                    'theorique' => (! $journee || $fermee) ? $s->theoretical_closing_amount : null,
                    'compte' => (! $journee || $fermee) ? $s->actual_closing_amount : null,
                    'ecart' => $fermee ? (int) $s->discrepancy_amount : null,
                    'temoin' => $fermee ? ($s->witness?->name ?? 'À contresigner') : 'Ouverte',
                ];
            });

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('caisse', 'Caisse'),
                Colonne::texte('caissier', 'Caissier'),
                Colonne::dateHeure('ouverture', 'Ouverture'),
                Colonne::dateHeure('cloture', 'Comptage'),
                Colonne::montant('fonds', 'Fonds'),
                Colonne::montant('theorique', 'Théorique'),
                Colonne::montant('compte', 'Compté'),
                Colonne::montant('ecart', 'Écart'),
                Colonne::texte('temoin', 'Contresigné par'),
            ])
            ->lignes($sessions)
            ->totaux(['ecart' => (int) $sessions->sum('ecart')]);
    }
}
