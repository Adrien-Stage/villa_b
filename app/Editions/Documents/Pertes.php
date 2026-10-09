<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\RestaurantWasteLog;
use App\Models\User;
use App\Services\RestaurantContext;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les pertes déclarées au garde-manger, valorisées. */
class Pertes extends Edition
{
    public function cle(): string { return 'pertes'; }

    public function famille(): string { return self::RESTAURATION; }

    public function titre(): string { return 'Pertes et déchets'; }

    public function description(): string
    {
        return 'Les pertes déclarées sur la période : article, quantité, coût, motif et responsable. Chaque PV reste imprimable seul depuis « Retrouver une pièce ».';
    }

    public function module(): ?string { return 'restaurant'; }

    public function droits(): array { return ['restaurant.waste.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('restaurant', 'Restaurant', fn (User $u) => app(RestaurantContext::class)->accessibles($u)->pluck('name', 'id')
                ->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom])->all(), 'Tous les restaurants'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = RestaurantWasteLog::query()
            ->with('item:id,name,unit')
            ->visiblesPour($user)
            ->when($valeurs['restaurant'] !== '', fn ($q) => $q->where('point_of_sale_id', (int) $valeurs['restaurant']))
            ->whereBetween('occurred_at', [$du->startOfDay(), $au->endOfDay()])
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (RestaurantWasteLog $p) => [
                'date' => $p->occurred_at,
                'reference' => $p->reference,
                'article' => $p->item?->name ?? '—',
                'quantite' => (float) $p->quantity . ' ' . ($p->item?->unit ?? ''),
                'motif' => $p->reason,
                'responsable' => $p->responsible_person ?: '—',
                'cout' => (int) $p->total_cost,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::date('date', 'Date'),
                Colonne::texte('reference', 'PV'),
                Colonne::texte('article', 'Article'),
                Colonne::texte('quantite', 'Quantité'),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('responsable', 'Responsable'),
                Colonne::montant('cout', 'Coût'),
            ])
            ->lignes($lignes)
            ->totaux(['cout' => (int) $lignes->sum('cout')]);
    }
}
