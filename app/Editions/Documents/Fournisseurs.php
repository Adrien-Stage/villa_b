<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Le fichier des fournisseurs, avec ce qui leur a été commandé sur la période. */
class Fournisseurs extends Edition
{
    private const ETATS = ['actifs' => 'Actifs', 'inactifs' => 'Inactifs'];

    public function cle(): string { return 'fournisseurs'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Répertoire des fournisseurs'; }

    public function description(): string
    {
        return 'Les fournisseurs au fichier : contact, conditions de paiement, délai de livraison, et les bons envoyés sur la période avec leur montant.';
    }

    public function droits(): array { return ['economat.suppliers.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('etat', 'Fournisseurs', fn () => self::ETATS, 'Tous'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $periode = [$du->startOfDay(), $au->endOfDay()];
        // Les bons partis chez le fournisseur sur la période ; la
        // régularisation d'une réception directe n'est pas une commande.
        $commandes = fn ($q) => $q->whereBetween('sent_at', $periode)
            ->whereNotIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CANCELLED])
            ->where(fn ($r) => $r->whereNull('transmission')->orWhere('transmission', '!=', PurchaseOrder::TRANSMISSION_REGULARISATION));

        $lignes = Supplier::query()
            ->when($valeurs['etat'] === 'actifs', fn ($q) => $q->where('is_active', true))
            ->when($valeurs['etat'] === 'inactifs', fn ($q) => $q->where('is_active', false))
            ->withCount(['purchaseOrders as bons' => $commandes])
            ->withSum(['purchaseOrders as montant' => $commandes], 'total_amount')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $f) => [
                'nom' => $f->name,
                'contact' => collect([$f->contact_name, $f->phone, $f->email])->filter()->implode(' · ') ?: '—',
                'ville' => $f->city ?: '—',
                'paiement' => $f->payment_terms ?: '—',
                'delai' => $f->delivery_lead_time_days !== null ? $f->delivery_lead_time_days . ' j' : '—',
                'etat' => $f->is_active ? 'Actif' : 'Inactif',
                'bons' => (int) $f->bons,
                'montant' => (int) $f->montant,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('nom', 'Fournisseur'),
                Colonne::texte('contact', 'Contact'),
                Colonne::texte('ville', 'Ville'),
                Colonne::texte('paiement', 'Paiement'),
                Colonne::texte('delai', 'Délai'),
                Colonne::texte('etat', 'État'),
                Colonne::nombre('bons', 'Bons envoyés'),
                Colonne::montant('montant', 'Montant commandé'),
            ])
            ->lignes($lignes)
            ->totaux(['bons' => (int) $lignes->sum('bons'), 'montant' => (int) $lignes->sum('montant')]);
    }
}
