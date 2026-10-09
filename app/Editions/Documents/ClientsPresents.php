<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les clients présents dans l'hôtel une nuit donnée (« en-house »). */
class ClientsPresents extends Edition
{
    public function cle(): string { return 'clients-presents'; }

    public function famille(): string { return self::HEBERGEMENT; }

    public function titre(): string { return 'Clients présents'; }

    public function description(): string
    {
        return "Qui dort à l'hôtel la nuit choisie : chambre, client, dates du séjour, nombre de personnes et solde. La liste à remettre au veilleur de nuit.";
    }

    public function droits(): array { return ['bookings.voir']; }

    public function filtres(): array
    {
        return [Filtre::jour('Nuit du')];
    }

    public function document(array $valeurs, User $user): Document
    {
        $nuit = $valeurs['jour']->toDateString();

        $lignes = Booking::query()
            ->with(['customer', 'room.roomType'])
            ->whereIn('status', [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])
            ->whereDate('check_in', '<=', $nuit)
            ->whereDate('check_out', '>', $nuit)
            ->get()
            ->sortBy(fn (Booking $b) => $b->room?->number)
            ->map(fn (Booking $b) => Arrivees::ligneDeSejour($b))
            ->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('personnes', 'Pers.'),
                Colonne::date('arrivee', 'Arrivée'),
                Colonne::date('depart', 'Départ'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::montant('solde', 'Solde dû'),
            ])
            ->lignes($lignes)
            ->totaux(['solde' => (int) $lignes->sum('solde')])
            ->note($lignes->count() . ' chambre(s) occupée(s).');
    }
}
