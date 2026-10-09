<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use Illuminate\Support\Collection;

/**
 * Le fichier de comptage d'un inventaire : le modèle Excel qu'on télécharge
 * et remplit après le comptage, puis qu'on importe pour saisir d'un coup les
 * quantités comptées. La clôture de l'inventaire ajuste ensuite le stock.
 *
 * Une ligne se rattache à l'article par son identifiant, sinon sa référence,
 * sinon son nom. Une ligne sans quantité comptée est laissée telle quelle.
 */
class StockCountImportService
{
    public const COLONNES = ['id', 'référence', 'article', 'catégorie', 'unité', 'stock théorique', 'stock compté', 'motif', 'note'];

    /** Colonnes sans lesquelles un fichier ne se lit pas. */
    public const COLONNES_REQUISES = ['article', 'stock compté'];

    /**
     * Les lignes du modèle : celles de l'inventaire, avec leur théorique figé ;
     * hors inventaire, les articles actifs (d'une catégorie) et leur stock.
     *
     * @return list<list<mixed>>
     */
    public function modele(?StockCount $inventaire, ?int $categorieId = null): array
    {
        $articles = $inventaire
            ? $inventaire->lines()->with('item.category')->get()
                ->filter(fn (StockCountLine $l) => $l->item !== null)
                ->map(fn (StockCountLine $l) => [$l->item, (float) $l->theoretical_quantity, $l->counted_quantity, $l->reason, $l->notes])
            : StockItem::query()->active()->with('category')
                ->when($categorieId, fn ($q) => $q->where('stock_category_id', $categorieId))
                ->get()
                ->map(fn (StockItem $i) => [$i, (float) $i->current_stock, null, null, null]);

        return $articles
            ->sortBy(fn ($a) => mb_strtolower(($a[0]->category?->name ?? 'zzz') . ' ' . $a[0]->name))
            ->map(fn ($a) => [
                $a[0]->id,
                $a[0]->reference ?? '',
                $a[0]->name,
                $a[0]->category?->name ?? '',
                $a[0]->unit,
                $a[1],
                $a[2] !== null ? (float) $a[2] : '',
                $a[3] ? (StockCountLine::REASONS[$a[3]] ?? $a[3]) : '',
                $a[4] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Traduit les lignes lues en saisies de comptage pour l'inventaire.
     *
     * @param  list<array<string, ?string>>  $rows  lignes lues (en-têtes en minuscules)
     * @return array{0: array<int, array{counted_quantity: float, reason: ?string, notes: ?string}>, 1: list<string>}
     *               [saisies par ligne d'inventaire, erreurs]
     */
    public function saisies(StockCount $inventaire, array $rows): array
    {
        $lignes = $inventaire->lines()->with('item')->get()->filter(fn (StockCountLine $l) => $l->item !== null);
        $parId = $lignes->keyBy(fn ($l) => (string) $l->stock_item_id);
        $parReference = $this->indexer($lignes, fn ($l) => $l->item->reference);
        $parNom = $this->indexer($lignes, fn ($l) => $l->item->name);

        $saisies = [];
        $erreurs = [];

        foreach ($rows as $i => $row) {
            $numero = $i + 2;
            $compte = trim((string) ($row['stock compté'] ?? ''));

            if ($compte === '') {
                continue;
            }

            $ligne = $parId->get(trim((string) ($row['id'] ?? '')))
                ?? $parReference->get($this->cle($row['référence'] ?? ''))
                ?? $parNom->get($this->cle($row['article'] ?? ''));

            $nom = trim((string) ($row['article'] ?? '')) ?: trim((string) ($row['référence'] ?? ''));

            if ($ligne === null) {
                $erreurs[] = "Ligne {$numero} : « {$nom} » ne fait pas partie de cet inventaire.";
                continue;
            }

            $quantite = $this->nombre($compte);
            if ($quantite === null || $quantite < 0) {
                $erreurs[] = "Ligne {$numero} : « {$compte} » n'est pas une quantité valable pour « {$ligne->item->name} ».";
                continue;
            }

            if (isset($saisies[$ligne->id])) {
                $erreurs[] = "Ligne {$numero} : « {$ligne->item->name} » apparaît deux fois dans le fichier ; seule la première ligne est retenue.";
                continue;
            }

            [$motif, $note] = $this->motif($row['motif'] ?? '', $row['note'] ?? '');
            $saisies[$ligne->id] = ['counted_quantity' => $quantite, 'reason' => $motif, 'notes' => $note];
        }

        return [$saisies, $erreurs];
    }

    /** « 12 », « 12,5 », « 1 250,75 » ou « 12.5 » ; null si ce n'est pas un nombre. */
    private function nombre(string $texte): ?float
    {
        $normalise = str_replace(["\u{00A0}", "\u{202F}", ' '], '', $texte);
        if (substr_count($normalise, ',') === 1 && !str_contains($normalise, '.')) {
            $normalise = str_replace(',', '.', $normalise);
        } else {
            $normalise = str_replace(',', '', $normalise);
        }

        return is_numeric($normalise) ? round((float) $normalise, 3) : null;
    }

    /**
     * Le motif se donne par son libellé ou son code ; un motif inconnu devient
     * « autre » et son texte passe dans la note, pour ne rien perdre.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function motif(?string $saisi, ?string $note): array
    {
        $saisi = trim((string) $saisi);
        $note = trim((string) $note) ?: null;

        if ($saisi === '') {
            return [null, $note];
        }

        foreach (StockCountLine::REASONS as $code => $libelle) {
            if (in_array($this->cle($saisi), [$this->cle($code), $this->cle($libelle)], true)) {
                return [$code, $note];
            }
        }

        return [StockCountLine::REASON_OTHER, trim($saisi . ($note ? ' — ' . $note : ''))];
    }

    private function indexer(Collection $lignes, callable $valeur): Collection
    {
        return $lignes->filter(fn ($l) => trim((string) $valeur($l)) !== '')->keyBy(fn ($l) => $this->cle($valeur($l)));
    }

    private function cle(?string $texte): string
    {
        return mb_strtolower(trim((string) $texte));
    }
}
