<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/**
 * L'historique des livraisons d'un fournisseur : ce qu'il a livré, quand,
 * contre quel bon et pour quel montant. Le nom du fournisseur fait le titre
 * du document ; sans fournisseur choisi, tous figurent, chacun nommé.
 */
class HistoriqueLivraisons extends Edition
{
    public function cle(): string { return 'historique-livraisons'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Historique des livraisons'; }

    public function description(): string
    {
        return "Les livraisons d'un fournisseur sur la période : date, bon d'entrée, bon de commande, bordereau, livraison partielle ou complète, réceptionnaire et montant.";
    }

    public function droits(): array { return ['economat.suppliers.voir', 'economat.receipts.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::choix('fournisseur', 'Fournisseur', fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Tous les fournisseurs'),
            Filtre::periode('douze_mois'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $fournisseur = $valeurs['fournisseur'] !== '' ? Supplier::find((int) $valeurs['fournisseur']) : null;

        $lignes = GoodsReceipt::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,number', 'receivedBy:id,name'])
            ->withCount('attachments')
            // Une livraison attendue n'est pas encore une livraison.
            ->where('status', '!=', GoodsReceipt::STATUS_PENDING)
            ->whereBetween('received_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($fournisseur, fn ($q) => $q->where('supplier_id', $fournisseur->id))
            ->orderBy('received_at')->orderBy('id')
            ->get()
            ->map(fn (GoodsReceipt $r) => [
                'fournisseur' => $r->supplier?->name ?? '—',
                'date' => $r->received_at,
                'numero' => $r->number,
                'commande' => $r->purchaseOrder?->number ?? '—',
                'bordereau' => $r->delivery_note_number ?: '—',
                'pieces' => (int) $r->attachments_count,
                'livraison' => $r->status === GoodsReceipt::STATUS_CANCELLED ? 'Annulée' : ($r->deliveryLabel() ?? '—'),
                'par' => $r->receivedBy?->name ?? '—',
                // Une livraison annulée reste à l'historique, sans montant.
                'montant' => $r->status === GoodsReceipt::STATUS_CANCELLED ? 0 : (int) $r->total_amount,
            ]);

        $colonnes = [
            Colonne::date('date', 'Reçu le'),
            Colonne::texte('numero', "Bon d'entrée"),
            Colonne::texte('commande', 'Bon de commande'),
            Colonne::texte('bordereau', 'Bordereau'),
            Colonne::nombre('pieces', 'Pièces jointes'),
            Colonne::texte('livraison', 'Livraison'),
            Colonne::texte('par', 'Réceptionnaire'),
            Colonne::montant('montant', 'Montant'),
        ];

        // Le fournisseur fait le titre ; tous ensemble, chaque ligne le nomme.
        if ($fournisseur === null) {
            array_unshift($colonnes, Colonne::texte('fournisseur', 'Fournisseur'));
        }

        $livrees = $lignes->where('livraison', '!=', 'Annulée')->count();

        return Document::intitule($this->titre() . ' — ' . ($fournisseur?->name ?? 'tous les fournisseurs'))
            ->sousTitre(collect([
                $fournisseur?->code, $fournisseur?->city, $fournisseur?->phone,
                $fournisseur?->tax_id ? 'NIU ' . $fournisseur->tax_id : null,
                $livrees . ' livraison(s) sur la période',
            ])->filter()->implode(' · '))
            ->filtres($this->filtresImprimes($valeurs, $user))
            ->colonnes($colonnes)
            ->lignes($lignes)
            ->totaux(['pieces' => (int) $lignes->sum('pieces'), 'montant' => (int) $lignes->sum('montant')]);
    }
}
