<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** L'état de chaque chambre à cet instant, avec son occupant. */
class EtatChambres extends Edition
{
    public function cle(): string { return 'etat-chambres'; }

    public function famille(): string { return self::HEBERGEMENT; }

    public function titre(): string { return 'État des chambres'; }

    public function description(): string
    {
        return "Chaque chambre à cet instant : type, étage, état (occupée, à nettoyer, contrôlée…), occupant et départ prévu. La feuille de route de la gouvernante.";
    }

    public function droits(): array { return ['rooms.voir', 'housekeeping.voir']; }

    public function document(array $valeurs, User $user): Document
    {
        $occupants = Booking::query()->with('customer')
            ->where('status', BookingStatus::CHECKED_IN)->get()->keyBy('room_id');

        $lignes = Room::query()->with('roomType')->where('is_active', true)
            ->orderBy('floor')->orderBy('number')->get()
            ->map(fn (Room $r) => [
                'numero' => $r->number,
                'type' => $r->roomType?->name ?? '—',
                'etage' => $r->floor,
                'etat' => $r->status?->label() ?? '—',
                'occupant' => $occupants->get($r->id)?->customer?->full_name ?? '',
                'depart' => $occupants->get($r->id)?->check_out,
            ]);

        $parEtat = $lignes->countBy('etat')->map(fn ($n, $etat) => "{$etat} : {$n}")->implode(' · ');

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('numero', 'Chambre'),
                Colonne::texte('type', 'Type'),
                Colonne::texte('etage', 'Étage'),
                Colonne::texte('etat', 'État'),
                Colonne::texte('occupant', 'Occupant'),
                Colonne::date('depart', 'Départ prévu'),
            ])
            ->lignes($lignes)
            ->note($parEtat);
    }
}
