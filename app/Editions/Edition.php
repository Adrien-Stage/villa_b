<?php

namespace App\Editions;

use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\Document\Document;
use App\Support\TenantModules;

/**
 * Une édition : un document que l'établissement imprime, d'après des filtres.
 *
 * Elle décrit son document (Document) ; DocumentExporter le rend à l'écran,
 * à l'impression, en PDF, Excel ou Word. Elle déclare aussi qui peut la
 * lire : au moins un des droits qui ouvrent déjà les mêmes données ailleurs
 * dans l'application. L'Édition n'ouvre rien que l'écran d'origine ne
 * montrerait pas.
 */
abstract class Edition
{
    public const FINANCES = 'Finances et caisse';
    public const HEBERGEMENT = 'Hébergement';
    public const RESTAURATION = 'Restauration et boutique';
    public const ACHATS = 'Achats et stocks';
    public const PERSONNEL = 'Personnel';

    /** Ordre d'affichage des familles. */
    public const FAMILLES = [self::FINANCES, self::HEBERGEMENT, self::RESTAURATION, self::ACHATS, self::PERSONNEL];

    /** Identifiant d'adresse : « journal-encaissements ». */
    abstract public function cle(): string;

    abstract public function famille(): string;

    abstract public function titre(): string;

    /** Ce que l'édition dit, et quand s'en servir. */
    abstract public function description(): string;

    /** @return list<string> droits dont un seul suffit */
    abstract public function droits(): array;

    /** Module de l'établissement dont l'édition dépend, ou null. */
    public function module(): ?string
    {
        return null;
    }

    /** @return list<Filtre> */
    public function filtres(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $valeurs  valeurs validées des filtres, par clé */
    abstract public function document(array $valeurs, User $user): Document;

    /** Document titré, avec ses filtres imprimés en tête : le point de départ de chaque édition. */
    protected function base(array $valeurs, User $user, ?string $sousTitre = null): Document
    {
        return Document::intitule($this->titre())
            ->sousTitre($sousTitre)
            ->filtres($this->filtresImprimes($valeurs, $user));
    }

        public function accessiblePour(?User $user): bool
    {
        if ($user === null || ($this->module() !== null && ! TenantModules::has($this->module()))) {
            return false;
        }

        $resolveur = app(PermissionResolver::class);

        foreach ($this->droits() as $droit) {
            if ($resolveur->allows($user, $droit)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valeurs validées des filtres, lues depuis la requête.
     *
     * @param  array<string, mixed>  $saisie
     * @return array<string, mixed>
     */
    public function valeurs(array $saisie, User $user): array
    {
        $valeurs = [];
        foreach ($this->filtres() as $filtre) {
            $valeurs[$filtre->cle] = $filtre->lire($saisie, $user);
        }

        return $valeurs;
    }

    /**
     * Libellés des filtres appliqués, imprimés en tête du document.
     *
     * @param  array<string, mixed>  $valeurs
     * @return array<string, string>
     */
    public function filtresImprimes(array $valeurs, User $user): array
    {
        $libelles = [];
        foreach ($this->filtres() as $filtre) {
            $valeur = $valeurs[$filtre->cle] ?? null;
            $libelles[$filtre->libelle] = match ($filtre->type) {
                Filtre::PERIODE => 'du ' . $valeur[0]->format('d/m/Y') . ' au ' . $valeur[1]->format('d/m/Y'),
                Filtre::JOUR => $valeur->format('d/m/Y'),
                Filtre::SEMAINE => 'du ' . $valeur->format('d/m/Y') . ' au ' . $valeur->addDays(6)->format('d/m/Y'),
                default => $valeur === Filtre::TOUS ? $filtre->libelleTous : ($filtre->options($user)[$valeur] ?? $valeur),
            };
        }

        return $libelles;
    }
}
