<?php

namespace App\Enums;

enum BookingStatus: string
{
    case DRAFT      = 'draft';
    case PENDING    = 'pending';
    case CONFIRMED  = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';
    case NO_SHOW    = 'no_show';

    public function label(): string
    {
        return match($this) {
            self::DRAFT       => 'Brouillon',
            self::PENDING     => 'En attente',
            self::CONFIRMED   => 'Confirmée',
            self::CHECKED_IN  => 'En cours de séjour',
            self::CHECKED_OUT => 'Parti',
            self::COMPLETED   => 'Terminée',
            self::CANCELLED   => 'Annulée',
            self::NO_SHOW     => 'Non présenté',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT       => 'bg-amber-100 text-amber-800',
            self::PENDING     => 'bg-yellow-100 text-yellow-800',
            self::CONFIRMED   => 'bg-blue-100 text-blue-800',
            self::CHECKED_IN  => 'bg-green-100 text-green-800',
            self::CHECKED_OUT => 'bg-gray-100 text-gray-800',
            self::COMPLETED   => 'bg-gray-100 text-gray-800',
            self::CANCELLED   => 'bg-red-80 text-red-800',
            self::NO_SHOW     => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Ce statut est-il une fin de cycle ? (pas de transition possible après)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
        ]);
    }

    /**
     * La chambre est-elle bloquée par cette réservation ?
     * Utilisé dans le scope availableBetween() du modèle Room
     */
    public function blocksRoom(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::CHECKED_IN,
        ]);
    }
}