<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockItemPackaging;
use App\Support\Conditionnement;
use Illuminate\Support\Collection;

/**
 * Les unités fermées d'un article conditionné : cartons, paquets…
 *
 * Le stock de l'article reste compté dans sa plus petite unité ; ce service
 * tient, à côté, combien de cartons et de paquets sont encore fermés. Ce qui
 * n'est dans aucune unité fermée est en vrac.
 *
 * Une sortie prend d'abord le vrac, puis les plus petites unités fermées, et
 * n'ouvre une unité plus grande que quand il le faut : un carton ouvert donne
 * ses paquets, un paquet ouvert donne ses pièces. Une sortie demandée dans un
 * conditionnement (« 1 carton ») sert d'abord des unités fermées de ce
 * conditionnement.
 *
 * Toujours appelé sous le verrou de l'article, par le StockService.
 */
class Conditionnements
{
    /**
     * Répercute une variation du stock sur les unités fermées.
     *
     * @return array{niveaux: list<array{nom: string, facteur: float, fermes: int}>, vrac: float, ouverts: array<string, int>}|null
     */
    public function appliquer(StockItem $item, float $avant, float $variation, ?string $niveau = null): ?array
    {
        $niveaux = $this->niveaux($item);
        if ($niveaux->isEmpty()) {
            return null;
        }

        $ouverts = [];
        if ($variation > Conditionnement::EPSILON) {
            $this->entrer($niveaux, $variation, $niveau);
        } elseif ($variation < -Conditionnement::EPSILON) {
            $ouverts = $this->sortir($niveaux, $avant, -$variation, $niveau);
        }

        $this->borner($niveaux, (float) $item->current_stock);
        $this->enregistrer($niveaux);

        return $this->etat($niveaux, (float) $item->current_stock, $ouverts);
    }

    /**
     * Ouvre des unités fermées : leur contenu passe au conditionnement du
     * dessous, ou en vrac. Le stock ne change pas.
     */
    public function ouvrir(StockItem $item, string $nom, int $nombre): array
    {
        $niveaux = $this->niveaux($item);
        $niveau = $niveaux->first(fn ($n) => $n->name === $nom);

        if ($niveau === null) {
            throw new \InvalidArgumentException("« {$item->name} » n'a pas de conditionnement « {$nom} ».");
        }
        if ($nombre < 1) {
            throw new \InvalidArgumentException("Indiquez combien d'unités ouvrir.");
        }
        if ($niveau->closed_count < $nombre) {
            throw new \RuntimeException(
                'Il ne reste que ' . Conditionnement::libelle($niveau->closed_count, $nom) . ' fermé(s) à ouvrir.'
            );
        }

        $ouverts = [];
        for ($i = 0; $i < $nombre; $i++) {
            $this->ouvrirUne($niveaux, $niveau, $ouverts);
        }
        $this->enregistrer($niveaux);

        return $this->etat($niveaux, (float) $item->current_stock, $ouverts);
    }

    /**
     * Définit les conditionnements de l'article, du plus petit au plus grand.
     * Chacun contient un nombre entier d'unités du niveau du dessous : un
     * carton de 20 paquets, un paquet de 10 pièces.
     *
     * @param  list<array{nom: string, contenance: int|float|string, fermes?: int|string|null}>  $saisie
     */
    public function definir(StockItem $item, array $saisie): array
    {
        $unite = mb_strtolower(trim((string) $item->unit));
        $vus = [];
        $facteur = 1.0;
        $lignes = [];

        foreach ($saisie as $i => $ligne) {
            $nom = trim((string) ($ligne['nom'] ?? ''));
            if ($nom === '') {
                continue;
            }
            $cle = mb_strtolower($nom);
            if ($cle === $unite) {
                throw new \InvalidArgumentException("« {$nom} » est déjà l'unité de l'article : un conditionnement doit être plus grand.");
            }
            if (isset($vus[$cle])) {
                throw new \InvalidArgumentException("Le conditionnement « {$nom} » est indiqué deux fois.");
            }
            $vus[$cle] = true;

            $contenance = (float) ($ligne['contenance'] ?? 0);
            if ($contenance < 2 || abs($contenance - round($contenance)) > Conditionnement::EPSILON) {
                $dessous = $lignes === [] ? $item->unit : end($lignes)['nom'];
                throw new \InvalidArgumentException(
                    "Un « {$nom} » doit contenir un nombre entier de « {$dessous} », au moins 2."
                );
            }

            $fermes = (int) ($ligne['fermes'] ?? 0);
            if ($fermes < 0) {
                throw new \InvalidArgumentException('Le nombre d’unités fermées ne peut pas être négatif.');
            }

            $facteur *= round($contenance);
            $lignes[] = ['nom' => $nom, 'facteur' => $facteur, 'fermes' => $fermes];
        }

        $ferme = array_sum(array_map(fn ($l) => $l['fermes'] * $l['facteur'], $lignes));
        if ($ferme > (float) $item->current_stock + Conditionnement::EPSILON) {
            throw new \InvalidArgumentException(
                'Les unités fermées représentent ' . Conditionnement::libelle($ferme, $item->unit)
                . ', plus que le stock de ' . Conditionnement::libelle((float) $item->current_stock, $item->unit) . '.'
            );
        }

        StockItemPackaging::where('stock_item_id', $item->id)->delete();
        foreach ($lignes as $l) {
            StockItemPackaging::create([
                'stock_item_id' => $item->id,
                'name'          => $l['nom'],
                'factor'        => $l['facteur'],
                'closed_count'  => $l['fermes'],
            ]);
        }

        return $this->etat($this->niveaux($item), (float) $item->current_stock, []);
    }

    /** État courant, sans rien modifier. */
    public function etatDe(StockItem $item): ?array
    {
        $niveaux = $item->relationLoaded('packagings') ? $item->packagings->sortBy('factor')->values() : $item->packagings()->get();

        return $niveaux->isEmpty() ? null : $this->etat($niveaux, (float) $item->current_stock, []);
    }

    // ── Mécanique ────────────────────────────────────────────────────────────

    private function niveaux(StockItem $item): Collection
    {
        return StockItemPackaging::query()
            ->where('stock_item_id', $item->id)
            ->orderBy('factor')
            ->lockForUpdate()
            ->get();
    }

    /** Une entrée dans un conditionnement, en unités entières, reste fermée. */
    private function entrer(Collection $niveaux, float $quantite, ?string $nom): void
    {
        $niveau = $nom !== null ? $niveaux->first(fn ($n) => $n->name === $nom) : null;
        if ($niveau === null) {
            return; // en vrac
        }

        $unites = $quantite / (float) $niveau->factor;
        if (abs($unites - round($unites)) < Conditionnement::EPSILON) {
            $niveau->closed_count += (int) round($unites);
        }
    }

    /** @return array<string, int> unités ouvertes par conditionnement */
    private function sortir(Collection $niveaux, float $avant, float $quantite, ?string $nom): array
    {
        $ouverts = [];
        $reste = $quantite;
        $vrac = max(0.0, $avant - $this->ferme($niveaux));

        // Demandé dans un conditionnement : des unités fermées de celui-ci d'abord.
        $demande = $nom !== null ? $niveaux->first(fn ($n) => $n->name === $nom) : null;
        if ($demande !== null) {
            $k = min($demande->closed_count, (int) floor(($reste + Conditionnement::EPSILON) / (float) $demande->factor));
            $demande->closed_count -= $k;
            $reste -= $k * (float) $demande->factor;
        }

        $garde = 0;
        while ($reste > Conditionnement::EPSILON && $garde++ < 100000) {
            $pris = min($vrac, $reste);
            $vrac -= $pris;
            $reste -= $pris;
            if ($reste <= Conditionnement::EPSILON) {
                break;
            }

            $plusPetit = $niveaux->first(fn ($n) => $n->closed_count > 0);
            if ($plusPetit === null) {
                break; // incohérence ancienne : le contrôle du stock a déjà eu lieu
            }

            $facteur = (float) $plusPetit->factor;
            if ($facteur <= $reste + Conditionnement::EPSILON) {
                $k = min($plusPetit->closed_count, (int) floor(($reste + Conditionnement::EPSILON) / $facteur));
                $plusPetit->closed_count -= $k;
                $reste -= $k * $facteur;
                continue;
            }

            $vrac += $this->ouvrirUne($niveaux, $plusPetit, $ouverts);
        }

        return $ouverts;
    }

    /**
     * Ouvre une unité : son contenu va au conditionnement du dessous, le
     * reliquat éventuel en vrac. Retourne ce qui part en vrac.
     */
    private function ouvrirUne(Collection $niveaux, StockItemPackaging $niveau, array &$ouverts): float
    {
        $niveau->closed_count--;
        $ouverts[$niveau->name] = ($ouverts[$niveau->name] ?? 0) + 1;

        $facteur = (float) $niveau->factor;
        $dessous = $niveaux->filter(fn ($n) => (float) $n->factor < $facteur)->last();
        if ($dessous === null) {
            return $facteur;
        }

        $nombre = (int) floor(($facteur + Conditionnement::EPSILON) / (float) $dessous->factor);
        $dessous->closed_count += $nombre;

        return $facteur - $nombre * (float) $dessous->factor;
    }

    /**
     * Jamais plus d'unités fermées que de stock : un ajustement à la baisse ou
     * un état ancien se corrige en ôtant d'abord les plus petites.
     */
    private function borner(Collection $niveaux, float $stock): void
    {
        foreach ($niveaux as $niveau) {
            $exces = $this->ferme($niveaux) - $stock;
            if ($exces <= Conditionnement::EPSILON) {
                return;
            }
            $retrait = min($niveau->closed_count, (int) ceil(($exces - Conditionnement::EPSILON) / (float) $niveau->factor));
            $niveau->closed_count -= $retrait;
        }
    }

    private function ferme(Collection $niveaux): float
    {
        return (float) $niveaux->sum(fn ($n) => $n->closed_count * (float) $n->factor);
    }

    private function enregistrer(Collection $niveaux): void
    {
        foreach ($niveaux as $niveau) {
            if ($niveau->isDirty('closed_count')) {
                $niveau->save();
            }
        }
    }

    private function etat(Collection $niveaux, float $stock, array $ouverts): array
    {
        return [
            'niveaux' => $niveaux->map(fn ($n) => [
                'nom'     => $n->name,
                'facteur' => (float) $n->factor,
                'fermes'  => (int) $n->closed_count,
            ])->values()->all(),
            'vrac'    => round(max(0.0, $stock - $this->ferme($niveaux)), 3),
            'ouverts' => $ouverts,
        ];
    }
}
