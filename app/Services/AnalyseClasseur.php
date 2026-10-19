<?php

namespace App\Services;

use App\Models\ServiceStore;
use App\Models\StockItem;
use App\Support\ClasseurStock as C;
use Illuminate\Support\Collection;

/**
 * L'analyse d'un classeur importé, en cours de construction : ce qui serait
 * créé ou modifié, les mouvements retenus, les erreurs et avertissements à
 * leur ligne. Voir ClasseurStockImport::analyser().
 */
class AnalyseClasseur
{
    /** @var Collection<string, \App\Models\StockCategory> */
    public Collection $categoriesConnues;

    /** @var Collection<string, \App\Models\Supplier> */
    public Collection $fournisseursConnus;

    /** @var array<string, string> nom en minuscules => nom */
    public array $categoriesACreer = [];

    /** @var array<string, array<string, string>> nom => coordonnées */
    public array $fournisseursACreer = [];

    /** @var array<string, array<string, mixed>> référence en minuscules => article */
    public array $articles = [];

    /** @var list<array<string, mixed>> mouvements retenus, dans l'ordre d'application */
    public array $mouvements = [];

    /** @var array<string, float> stock simulé par référence */
    public array $stockSimule = [];

    /** @var array<string, bool> l'article a déjà vécu autre chose qu'un stock initial */
    public array $vecu = [];

    public int $dejaEnregistres = 0;
    public int $dejaImportes = 0;

    private array $erreurs = [];
    private array $avertissements = [];

    private const LIBELLES = [
        'reference'   => 'Référence',
        'name'        => 'Désignation',
        'unit'        => 'Unité',
        'categorie'   => 'Catégorie',
        'fournisseur' => 'Fournisseur',
        'description' => 'Description',
        'min_stock'   => 'Stock minimum',
        'is_active'   => 'Actif',
    ];

    public function __construct(public readonly ?ServiceStore $depot)
    {
        $this->categoriesConnues = collect();
        $this->fournisseursConnus = collect();
    }

    public function erreur(?string $onglet, ?int $ligne, string $message): void
    {
        $this->erreurs[] = ['onglet' => $onglet, 'ligne' => $ligne, 'message' => $message];
    }

    public function avertissement(?string $onglet, ?int $ligne, string $message): void
    {
        $this->avertissements[] = ['onglet' => $onglet, 'ligne' => $ligne, 'message' => $message];
    }

    /** Le nom de la catégorie telle qu'elle existe ou sera créée ; null si vide. */
    public function categorie(string $saisie): ?string
    {
        $nom = mb_substr(trim($saisie), 0, 120);
        if ($nom === '') {
            return null;
        }
        $cle = mb_strtolower($nom);

        if ($connue = $this->categoriesConnues->get($cle)) {
            return $connue->name;
        }

        return $this->categoriesACreer[$cle] ??= $nom;
    }

    /** Le nom du fournisseur tel qu'il existe ou sera créé. */
    public function fournisseur(string $saisie, array $coordonnees = []): string
    {
        $nom = mb_substr(trim($saisie), 0, 160);
        $cle = mb_strtolower($nom);

        if ($connu = $this->fournisseursConnus->get($cle)) {
            return $connu->name;
        }

        foreach ($this->fournisseursACreer as $existant => $details) {
            if (mb_strtolower($existant) === $cle) {
                $this->fournisseursACreer[$existant] = $details + $coordonnees;

                return $existant;
            }
        }

        $this->fournisseursACreer[$nom] = $coordonnees;

        return $nom;
    }

    /** Une ligne de l'onglet Articles : création, modification ou rien à faire. */
    public function ajouterArticle(string $cle, int $ligne, ?StockItem $article, array $voulu): void
    {
        $changements = [];

        if ($article !== null) {
            $actuel = [
                // Référence tirée du N° à l'export : l'article n'en a pas, il n'en prend pas.
                'reference'   => trim((string) $article->reference) !== '' ? $article->reference : sprintf('ART-%05d', $article->id),
                'name'        => $article->name,
                'unit'        => $article->unit,
                'categorie'   => $article->category?->name,
                'fournisseur' => $article->supplier?->name,
                'description' => $article->description,
                'min_stock'   => (float) $article->min_stock,
                'is_active'   => (bool) $article->is_active,
            ];

            foreach ($voulu as $champ => $valeur) {
                // Une case vide ne vide pas la fiche.
                if ($valeur === null) {
                    continue;
                }
                $egal = is_float($valeur) ? abs($valeur - (float) $actuel[$champ]) < 0.0005 : $valeur === $actuel[$champ];
                if (!$egal) {
                    $changements[$champ] = [self::LIBELLES[$champ], $this->lisible($actuel[$champ]), $this->lisible($valeur)];
                }
            }
        }

        $this->articles[$cle] = [
            'cle'         => $cle,
            'ligne'       => $ligne,
            'id'          => $article?->id,
            'reference'   => $voulu['reference'],
            'name'        => $voulu['name'],
            'unit'        => $voulu['unit'],
            'action'      => $article === null ? 'creer' : ($changements === [] ? 'inchange' : 'modifier'),
            'voulu'       => $voulu,
            'changements' => $changements,
        ];
    }

    /**
     * Un article cité par un mouvement sans figurer dans l'onglet Articles :
     * connu de l'application par sa référence (ou celle tirée de son N°).
     */
    public function articleConnu(string $reference): ?array
    {
        $cle = mb_strtolower($reference);
        $article = StockItem::query()->whereRaw('LOWER(TRIM(reference)) = ?', [$cle])->first();

        if ($article === null && preg_match('/^art-(\d+)$/', $cle, $m)) {
            $article = StockItem::query()->whereKey((int) $m[1])->where(fn ($q) => $q->whereNull('reference')->orWhere('reference', ''))->first();
        }

        if ($article === null) {
            return null;
        }

        $this->articles[$cle] = [
            'cle'         => $cle,
            'ligne'       => null,
            'id'          => $article->id,
            'reference'   => $reference,
            'name'        => $article->name,
            'unit'        => $article->unit,
            'action'      => 'inchange',
            'voulu'       => [],
            'changements' => [],
        ];

        return $this->articles[$cle];
    }

    private function lisible(mixed $valeur): string
    {
        return match (true) {
            $valeur === null  => '—',
            is_bool($valeur)  => $valeur ? C::OUI : C::NON,
            is_float($valeur) => rtrim(rtrim(number_format($valeur, 3, ',', ' '), '0'), ','),
            default           => (string) $valeur,
        };
    }

    /** L'analyse, prête à être gardée avec l'import et affichée. */
    public function resultat(): array
    {
        $articles = array_values($this->articles);
        $parType = array_fill_keys(C::TYPES, 0);
        $valeurEntrees = 0;
        foreach ($this->mouvements as $m) {
            $parType[$m['type']]++;
            if (in_array($m['type'], [C::TYPE_INITIAL, C::TYPE_ENTREE], true)) {
                $valeurEntrees += (int) round($m['quantite'] * ($m['cout'] ?? 0));
            }
        }

        return [
            'magasin'              => $this->depot ? 'Dépôt « ' . $this->depot->name . ' »' : 'Magasin central (économat)',
            'depot_id'             => $this->depot?->id,
            'erreurs'              => $this->erreurs,
            'avertissements'       => $this->avertissements,
            'categories_a_creer'   => array_values($this->categoriesACreer),
            'fournisseurs_a_creer' => $this->fournisseursACreer,
            'articles'             => $articles,
            'mouvements'           => $this->mouvements,
            'resume'               => [
                'categories_creees'   => count($this->categoriesACreer),
                'fournisseurs_crees'  => count($this->fournisseursACreer),
                'articles_crees'      => count(array_filter($articles, fn ($a) => $a['action'] === 'creer')),
                'articles_modifies'   => count(array_filter($articles, fn ($a) => $a['action'] === 'modifier')),
                'articles_inchanges'  => count(array_filter($articles, fn ($a) => $a['action'] === 'inchange')),
                'mouvements'          => count($this->mouvements),
                'par_type'            => $parType,
                'valeur_entrees'      => $valeurEntrees,
                'deja_enregistres'    => $this->dejaEnregistres,
                'deja_importes'       => $this->dejaImportes,
            ],
        ];
    }
}
