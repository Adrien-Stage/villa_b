<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/**
 * Le registre des voyageurs (fiche de police) : l'identité de chaque
 * personne arrivée sur la période, telle qu'enregistrée à l'arrivée.
 */
class RegistreVoyageurs extends Edition
{
    private const PIECES = ['passport' => 'Passeport', 'id_card' => "Carte d'identité", 'driver_license' => 'Permis'];

    public function cle(): string { return 'registre-voyageurs'; }

    public function famille(): string { return self::HEBERGEMENT; }

    public function titre(): string { return 'Registre des voyageurs'; }

    public function description(): string
    {
        return "L'identité des personnes arrivées sur la période — nom, date de naissance, nationalité, pièce d'identité — avec leur chambre et leurs dates. La fiche de police à tenir et à présenter sur demande.";
    }

    /** Données d'identité : celles que la fiche client montre déjà. */
    public function droits(): array { return ['customers.voir']; }

    public function filtres(): array
    {
        return [Filtre::periode('jour')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $sejours = Booking::query()
            ->with(['customer', 'room', 'guests'])
            ->whereIn('status', [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])
            ->whereDate('check_in', '>=', $du->toDateString())->whereDate('check_in', '<=', $au->toDateString())
            ->orderBy('check_in')
            ->get();

        $lignes = collect();
        foreach ($sejours as $b) {
            // Les occupants enregistrés à l'arrivée ; à défaut, le titulaire.
            $personnes = $b->guests->isNotEmpty() ? $b->guests : collect([$b->customer])->filter();

            foreach ($personnes as $p) {
                $lignes->push([
                    'nom' => trim(mb_strtoupper((string) $p->last_name) . ' ' . $p->first_name),
                    'naissance' => $p->date_of_birth,
                    'nationalite' => $p->nationality ?: '—',
                    'piece' => self::PIECES[$p->id_document_type ?? ''] ?? ($p->id_document_type ?: '—'),
                    'numero_piece' => $p->id_document_number ?: '—',
                    'chambre' => $b->room?->number ?? '—',
                    'arrivee' => $b->check_in,
                    'depart' => $b->check_out,
                ]);
            }
        }

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('nom', 'Nom et prénom'),
                Colonne::date('naissance', 'Né(e) le'),
                Colonne::texte('nationalite', 'Nationalité'),
                Colonne::texte('piece', 'Pièce'),
                Colonne::texte('numero_piece', 'N° de pièce'),
                Colonne::texte('chambre', 'Ch.'),
                Colonne::date('arrivee', 'Arrivée'),
                Colonne::date('depart', 'Départ'),
            ])
            ->lignes($lignes)
            ->note($lignes->count() . ' voyageur(s). Une pièce manquante se complète sur la fiche du séjour.');
    }
}
