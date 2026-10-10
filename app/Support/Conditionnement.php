<?php

namespace App\Support;

/**
 * Écriture des quantités par conditionnement : « 4 cartons · 19 paquets ·
 * 5 pièces ». Partagée par les écrans, les bons et le journal des mouvements.
 */
final class Conditionnement
{
    /** Écarts d'arrondi tolérés sur des quantités à trois décimales. */
    public const EPSILON = 0.0005;

    /** Les unités abrégées ne prennent pas la marque du pluriel. */
    private const INVARIABLES = ['kg', 'g', 'mg', 'l', 'cl', 'ml', 'dl', 'm', 'cm', 'mm', 'm2', 'm3', 'u'];

    public static function quantite(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 3, ',', ' '), '0'), ',');
    }

    /** « 1 carton », « 4 cartons », « 2,5 kg ». */
    public static function libelle(float $nombre, string $unite): string
    {
        return self::quantite($nombre) . ' ' . self::accorde($unite, $nombre);
    }

    public static function accorde(string $unite, float $nombre): string
    {
        $unite = trim($unite);
        if (abs($nombre) < 2 || $unite === '' || in_array(mb_strtolower($unite), self::INVARIABLES, true)
            || preg_match('/[sxz]$/iu', $unite)) {
            return $unite;
        }

        // « boîte » → « boîtes », « sac » → « sacs » ; un mot composé accorde son premier mot.
        $mots = explode(' ', $unite, 2);
        $mots[0] .= str_ends_with($mots[0], 'eau') || str_ends_with($mots[0], 'au') ? 'x' : 's';

        return implode(' ', $mots);
    }

    /**
     * L'état décomposé d'un article : unités fermées du plus grand au plus
     * petit conditionnement, puis le vrac dans l'unité de l'article.
     *
     * @param  array{niveaux: list<array{nom: string, fermes: int}>, vrac: float}  $etat
     */
    public static function decomposition(array $etat, string $unite): string
    {
        $parties = [];
        foreach (array_reverse($etat['niveaux'] ?? []) as $niveau) {
            if ((int) $niveau['fermes'] > 0) {
                $parties[] = self::libelle((int) $niveau['fermes'], $niveau['nom']);
            }
        }
        if (abs((float) ($etat['vrac'] ?? 0)) > self::EPSILON || $parties === []) {
            $parties[] = self::libelle((float) ($etat['vrac'] ?? 0), $unite);
        }

        return implode(' · ', $parties);
    }

    /** « ouverture : 1 carton », « ouvertures : 2 cartons et 1 paquet ». */
    public static function ouvertures(array $ouverts): string
    {
        $parties = [];
        foreach ($ouverts as $nom => $nombre) {
            if ((int) $nombre > 0) {
                $parties[] = self::libelle((int) $nombre, $nom);
            }
        }
        if ($parties === []) {
            return '';
        }
        $total = array_sum(array_map('intval', $ouverts));
        $liste = count($parties) > 1
            ? implode(', ', array_slice($parties, 0, -1)) . ' et ' . end($parties)
            : $parties[0];

        return ($total > 1 ? 'ouvertures : ' : 'ouverture : ') . $liste;
    }
}
