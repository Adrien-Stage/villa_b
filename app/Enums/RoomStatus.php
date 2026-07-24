<?php
// app/Enums/RoomStatus.php

namespace App\Enums;

/**
 * Enum RoomStatus
 * 
 * Cycle de vie d'une chambre (section 4.3.1 et 4.7 Housekeeping)
 * 
 * WORKFLOW :
 * available → occupied → dirty → cleaning → clean → inspected → available
 * 
 * Note : 'maintenance' et 'out_of_order' sont des statuts exceptionnels
 */
enum RoomStatus: string
{
    case AVAILABLE = 'available';       // Disponible à la vente
    case OCCUPIED = 'occupied';         // Client présent
    case DIRTY = 'dirty';               // À nettoyer (post check-out)
    case CLEANING = 'cleaning';         // En cours de nettoyage
    case CLEAN = 'clean';               // Nettoyée, attente contrôle
    case INSPECTED = 'inspected';       // Contrôlée, prête
    case MAINTENANCE = 'maintenance';   // Maintenance technique
    case OUT_OF_ORDER = 'out_of_order'; // Hors service longue durée

    /**
     * Labels pour l'interface utilisateur (section 5.1 Design System)
     */
    public function label(): string
    {
        return match($this) {
            self::AVAILABLE => 'Disponible',
            self::OCCUPIED => 'Occupée',
            // « À nettoyer » plutôt que « Sale » : évite la confusion avec
            // l'anglais « sales » et décrit l'action attendue.
            self::DIRTY => 'À nettoyer',
            self::CLEANING => 'En nettoyage',
            self::CLEAN => 'Nettoyée',
            self::INSPECTED => 'Contrôlée',
            self::MAINTENANCE => 'Maintenance',
            self::OUT_OF_ORDER => 'Hors service',
        };
    }

    /**
     * Couleur associée pour le UI (Tailwind classes)
     */
    public function color(): string
    {
        return match($this) {
            self::AVAILABLE => 'bg-green-100 text-green-800',
            self::OCCUPIED => 'bg-blue-100 text-blue-800',
            self::DIRTY => 'bg-red-100 text-red-800',
            self::CLEANING => 'bg-yellow-100 text-yellow-800',
            self::CLEAN => 'bg-purple-100 text-purple-800',
            self::INSPECTED => 'bg-emerald-100 text-emerald-800',
            self::MAINTENANCE => 'bg-orange-100 text-orange-800',
            self::OUT_OF_ORDER => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Transitions autorisées depuis ce statut
     * Empêche par exemple de passer directement de 'available' à 'occupied'
     * sans passer par le check-in
     */
    public function allowedTransitions(): array
    {        return match($this) {
            self::AVAILABLE => [self::OCCUPIED, self::MAINTENANCE, self::OUT_OF_ORDER],
            self::OCCUPIED => [self::DIRTY],
            self::DIRTY => [self::CLEANING],
            self::CLEANING => [self::CLEAN],
            self::CLEAN => [self::INSPECTED],
            self::INSPECTED => [self::AVAILABLE],
            self::MAINTENANCE => [self::AVAILABLE, self::OUT_OF_ORDER],
            self::OUT_OF_ORDER => [self::AVAILABLE],
        };
    }

    /**
     * Vérifie si la transition vers un nouveau statut est autorisée
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }
}