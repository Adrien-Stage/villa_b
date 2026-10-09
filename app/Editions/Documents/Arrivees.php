<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les arrivées prévues d'une journée, avec ce qu'il reste à encaisser. */
class Arrivees extends Edition
{
    public function cle(): string { return 'arrivees'; }

    public function famille(): string { return self::HEBERGEMENT; }

    public function titre(): string { return 'Liste des arrivées'; }

    public function description(): string
    {
        return "Les clients attendus le jour choisi : chambre, nombre de nuits et de personnes, heure d'arrivée prévue, solde à encaisser.";
    }

    public function droits(): array { return ['bookings.voir']; }

    public function filtres(): array
    {
        return [Filtre::jour('Jour d\'arrivée')];
    }

    public function document(array $valeurs, User $user): Document
    {
        $lignes = Booking::query()
            ->with(['customer', 'room.roomType'])
            ->whereDate('check_in', $valeurs['jour']->toDateString())
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN])
            ->orderBy('check_in_time')
            ->get()
            ->map(fn (Booking $b) => self::ligneDeSejour($b) + ['heure' => $b->check_in_time ? substr((string) $b->check_in_time, 0, 5) : '—']);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('type', 'Type'),
                Colonne::nombre('nuits', 'Nuits'),
                Colonne::texte('personnes', 'Pers.'),
                Colonne::texte('heure', 'Heure'),
                Colonne::texte('statut', 'Statut'),
                Colonne::montant('solde', 'Solde dû'),
            ])
            ->lignes($lignes)
            ->totaux(['solde' => (int) $lignes->sum('solde')]);
    }

    /** @return array<string, mixed> */
    public static function ligneDeSejour(Booking $b): array
    {
        return [
            'chambre' => $b->room?->number ?? '—',
            'numero' => $b->booking_number,
            'client' => ($b->customer?->full_name ?? '—') . ($b->customer?->is_vip ? ' (VIP)' : ''),
            'type' => $b->room?->roomType?->name ?? '—',
            'nuits' => (int) $b->total_nights,
            'personnes' => $b->adults_count . ($b->children_count ? ' + ' . $b->children_count . ' enf.' : ''),
            'arrivee' => $b->check_in,
            'depart' => $b->check_out,
            'statut' => $b->status?->label() ?? '',
            'solde' => (int) $b->balance_due,
        ];
    }
}
