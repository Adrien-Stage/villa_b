<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les départs d'une journée, avec les soldes à régler avant de rendre la chambre. */
class Departs extends Edition
{
    public function cle(): string { return 'departs'; }

    public function famille(): string { return self::HEBERGEMENT; }

    public function titre(): string { return 'Liste des départs'; }

    public function description(): string
    {
        return 'Les clients qui partent le jour choisi : chambre, séjour, statut du départ et solde à régler.';
    }

    public function droits(): array { return ['bookings.voir']; }

    public function filtres(): array
    {
        return [Filtre::jour('Jour de départ')];
    }

    public function document(array $valeurs, User $user): Document
    {
        $lignes = Booking::query()
            ->with(['customer', 'room.roomType'])
            ->whereDate('check_out', $valeurs['jour']->toDateString())
            ->whereIn('status', [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])
            ->get()
            ->sortBy(fn (Booking $b) => $b->room?->number)
            ->map(fn (Booking $b) => Arrivees::ligneDeSejour($b))
            ->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::texte('client', 'Client'),
                Colonne::date('arrivee', 'Arrivée'),
                Colonne::nombre('nuits', 'Nuits'),
                Colonne::texte('statut', 'Statut'),
                Colonne::montant('solde', 'Solde dû'),
            ])
            ->lignes($lignes)
            ->totaux(['solde' => (int) $lignes->sum('solde')]);
    }
}
