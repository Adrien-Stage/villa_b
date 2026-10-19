<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\NightAudit;
use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockImport;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\ClasseurStock as C;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Import du classeur Excel d'un magasin (voir ClasseurStockExport).
 *
 * Trois temps : lire le fichier, l'analyser — ce qui serait créé, modifié,
 * enregistré, et chaque erreur à sa ligne — puis, sur confirmation, appliquer.
 * L'application relit et réanalyse le fichier : le stock a pu bouger depuis
 * l'aperçu. Elle se fait d'un bloc : tout ou rien.
 *
 * Une ligne de mouvement qui porte un N° vient de l'application : elle est
 * déjà enregistrée. Une ligne sans N° déjà importée une fois est reconnue à
 * son empreinte. Aucune n'est rejouée.
 *
 * Vers un dépôt, ce qui entre passe par le magasin central puis lui est
 * livré, comme une demande livrée : le transfert ne coûte rien au service ;
 * la charge naît à la sortie vers un bénéficiaire ou à l'inventaire.
 */
class ClasseurStockImport
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ServiceStoreService $depots,
        private readonly StockAccountService $comptesDeStock,
    ) {
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Les onglets du classeur, lignes lues par en-tête.
     *
     * @return array{magasin: ?string, articles: list<array>, mouvements: list<array>,
     *               fournisseurs: list<array>, categories: list<array>, manquants: list<string>}
     */
    public function lire(string $chemin): array
    {
        $lecteur = IOFactory::createReaderForFile($chemin);
        $lecteur->setReadDataOnly(true);
        $classeur = $lecteur->load($chemin);

        $onglet = function (string $nom) use ($classeur): ?Worksheet {
            foreach ($classeur->getWorksheetIterator() as $ws) {
                if (C::cle($ws->getTitle()) === C::cle($nom)) {
                    return $ws;
                }
            }

            return null;
        };

        $lu = ['magasin' => null, 'manquants' => []];

        if ($lisezMoi = $onglet(C::ONGLET_LISEZ_MOI)) {
            for ($n = 1; $n <= 12; $n++) {
                if (C::cle((string) $lisezMoi->getCell("A{$n}")->getValue()) === C::cle(C::ETIQUETTE_MAGASIN)) {
                    $lu['magasin'] = trim((string) $lisezMoi->getCell("B{$n}")->getValue()) ?: null;
                    break;
                }
            }
        }

        foreach ([
            'articles'     => [C::ONGLET_ARTICLES, C::ARTICLES, ['reference', 'designation']],
            'mouvements'   => [C::ONGLET_MOUVEMENTS, array_diff_key(C::MOUVEMENTS, array_flip(C::MOUVEMENTS_CALCULS)), ['reference', 'type', 'quantite']],
            'fournisseurs' => [C::ONGLET_FOURNISSEURS, C::FOURNISSEURS, ['nom']],
            'categories'   => [C::ONGLET_CATEGORIES, C::CATEGORIES, ['nom']],
        ] as $cle => [$nom, $colonnes, $requises]) {
            $ws = $onglet($nom);
            $lu[$cle] = $ws ? $this->lignes($ws, $colonnes, $requises) : [];
            if ($ws === null && in_array($cle, ['articles', 'mouvements'], true)) {
                $lu['manquants'][] = $nom;
            }
        }

        $classeur->disconnectWorksheets();

        return $lu;
    }

    /**
     * Lignes d'un onglet : l'en-tête est la première ligne (parmi les dix
     * premières) qui nomme les colonnes requises.
     *
     * @return list<array<string, mixed>>
     */
    private function lignes(Worksheet $ws, array $colonnes, array $requises): array
    {
        $attendues = [];
        foreach ($colonnes as $cle => [$libelle]) {
            $attendues[C::cle($libelle)] = $cle;
            $attendues[C::cle(preg_replace('/\(.*\)/u', '', $libelle))] = $cle;
        }

        $carte = null;
        $ligneEntete = null;
        $derniereColonne = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());

        for ($n = 1; $n <= 10 && $carte === null; $n++) {
            $trouvees = [];
            for ($i = 1; $i <= $derniereColonne; $i++) {
                $cle = $attendues[C::cle((string) $ws->getCell([$i, $n])->getValue())] ?? null;
                if ($cle !== null && !isset($trouvees[$cle])) {
                    $trouvees[$cle] = $i;
                }
            }
            if (array_diff($requises, array_keys($trouvees)) === []) {
                $carte = $trouvees;
                $ligneEntete = $n;
            }
        }

        if ($carte === null) {
            return [];
        }

        $lignes = [];
        $fin = $ws->getHighestDataRow();
        for ($n = $ligneEntete + 1; $n <= $fin; $n++) {
            $ligne = ['_ligne' => $n];
            $vide = true;
            foreach ($carte as $cle => $i) {
                $valeur = $this->valeur($ws, $i, $n);
                $ligne[$cle] = $valeur;
                if ($valeur !== null && $valeur !== '') {
                    $vide = false;
                }
            }
            if (!$vide) {
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }

    private function valeur(Worksheet $ws, int $colonne, int $ligne): mixed
    {
        $cellule = $ws->getCell([$colonne, $ligne]);
        $valeur = $cellule->getValue();

        // Une formule dans une colonne saisie : on prend son résultat.
        if (is_string($valeur) && str_starts_with($valeur, '=')) {
            try {
                $valeur = $cellule->getCalculatedValue();
            } catch (\Throwable) {
                return null;
            }
        }

        if ($valeur instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $valeur = $valeur->getPlainText();
        }

        return is_string($valeur) ? trim($valeur) : $valeur;
    }

    // ── Analyse ──────────────────────────────────────────────────────────────

    /**
     * Ce que l'import ferait, sans rien écrire.
     *
     * @return array<string, mixed>  sérialisable : il est gardé avec l'import
     */
    public function analyser(array $lu, ?ServiceStore $depot): array
    {
        $a = new AnalyseClasseur($depot);

        foreach ($lu['manquants'] as $onglet) {
            $a->erreur($onglet, null, "Onglet « {$onglet} » introuvable : ce fichier n'a pas la forme du classeur de l'économat.");
        }

        $this->verifierMagasin($a, $lu, $depot);
        $this->verifierGel($a, $depot);
        $this->analyserListes($a, $lu);
        $this->analyserArticles($a, $lu['articles']);
        $this->analyserMouvements($a, $lu['mouvements'], $depot);

        return $a->resultat();
    }

    private function verifierMagasin(AnalyseClasseur $a, array $lu, ?ServiceStore $depot): void
    {
        $attendu = $depot ? C::PREFIXE_DEPOT . $depot->name : C::MAGASIN_CENTRAL;
        $fichier = $lu['magasin'];

        if ($fichier === null || C::cle($fichier) === C::cle($attendu)) {
            return;
        }

        // Des N° d'un autre magasin désigneraient d'autres mouvements : refusé.
        $numerote = collect($lu['mouvements'])->contains(fn ($m) => $this->entier($m['id'] ?? null) !== null);
        $message = "Ce classeur est celui de « {$fichier} », l'import vise « {$attendu} ».";

        $numerote
            ? $a->erreur(C::ONGLET_LISEZ_MOI, null, $message . ' Ses mouvements numérotés appartiennent à l\'autre magasin.')
            : $a->avertissement(C::ONGLET_LISEZ_MOI, null, $message);
    }

    private function verifierGel(AnalyseClasseur $a, ?ServiceStore $depot): void
    {
        if ($inventaire = StockCount::inProgress()) {
            $a->erreur(null, null, "Inventaire {$inventaire->reference} en cours au magasin central : aucun mouvement avant sa clôture.");
        }

        if ($depot && ($inventaire = ServiceStoreCountService::inProgress($depot))) {
            $a->erreur(null, null, "Inventaire {$inventaire->reference} en cours au dépôt {$depot->name} : aucun mouvement avant sa clôture.");
        }
    }

    private function analyserListes(AnalyseClasseur $a, array $lu): void
    {
        $a->categoriesConnues = StockCategory::query()->get()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $a->fournisseursConnus = Supplier::query()->get()->keyBy(fn ($f) => mb_strtolower(trim($f->name)));

        foreach ($lu['categories'] as $ligne) {
            $a->categorie((string) ($ligne['nom'] ?? ''));
        }

        foreach ($lu['fournisseurs'] as $ligne) {
            $nom = trim((string) ($ligne['nom'] ?? ''));
            if ($nom === '') {
                continue;
            }
            if (mb_strlen($nom) > 160) {
                $a->erreur(C::ONGLET_FOURNISSEURS, $ligne['_ligne'], 'Nom de fournisseur trop long (160 caractères au plus).');
                continue;
            }
            $a->fournisseur($nom, array_filter([
                'contact_name' => $this->texte($ligne['contact'] ?? null, 120),
                'phone'        => $this->texte($ligne['telephone'] ?? null, 30),
                'email'        => $this->texte($ligne['email'] ?? null, 150),
                'address'      => $this->texte($ligne['adresse'] ?? null, 255),
                'city'         => $this->texte($ligne['ville'] ?? null, 100),
                'notes'        => $this->texte($ligne['notes'] ?? null, 2000),
            ], fn ($v) => $v !== null));
        }
    }

    private function analyserArticles(AnalyseClasseur $a, array $lignes): void
    {
        $existants = StockItem::query()->with('category', 'supplier')->get();
        $parId = $existants->keyBy('id');
        $parReference = $existants->filter(fn ($i) => trim((string) $i->reference) !== '')
            ->keyBy(fn ($i) => mb_strtolower(trim($i->reference)));
        $parNom = $existants->groupBy(fn ($i) => mb_strtolower(trim($i->name)));
        $avecMouvements = StockMovement::query()->distinct()->pluck('stock_item_id')->flip();
        $unites = collect(StockUnit::choix())->keyBy(fn ($u) => mb_strtolower($u));

        foreach ($lignes as $ligne) {
            $n = $ligne['_ligne'];
            $reference = $this->code($ligne['reference'] ?? null);
            $designation = $this->texte($ligne['designation'] ?? null, 255);

            if ($reference === null) {
                $a->erreur(C::ONGLET_ARTICLES, $n, 'Référence obligatoire : c\'est elle qui relie l\'article à ses mouvements.');
                continue;
            }
            if (mb_strlen($reference) > 60) {
                $a->erreur(C::ONGLET_ARTICLES, $n, "Référence « {$reference} » trop longue (60 caractères au plus).");
                continue;
            }
            $cleRef = mb_strtolower($reference);
            if (isset($a->articles[$cleRef])) {
                $a->erreur(C::ONGLET_ARTICLES, $n, "Référence « {$reference} » déjà employée ligne {$a->articles[$cleRef]['ligne']} : chaque article a la sienne.");
                continue;
            }

            // Retrouver l'article : son N°, sa référence, sinon sa désignation.
            $article = null;
            $id = $this->entier($ligne['id'] ?? null);
            if ($id !== null) {
                $article = $parId->get($id);
                if ($article === null) {
                    $a->erreur(C::ONGLET_ARTICLES, $n, "N° {$id} inconnu : cet article n'existe pas dans l'application. Videz le N° pour le créer.");
                    continue;
                }
            }
            $article ??= $parReference->get($cleRef);
            if ($article === null && $designation !== null && ($homonymes = $parNom->get(mb_strtolower($designation)))) {
                $libre = $homonymes->first(fn ($i) => trim((string) $i->reference) === '' || $i->reference === sprintf('ART-%05d', $i->id));
                if ($libre === null) {
                    $a->avertissement(C::ONGLET_ARTICLES, $n, "« {$designation} » existe déjà sous la référence « {$homonymes->first()->reference} » : un second article sera créé.");
                } else {
                    $article = $libre;
                }
            }
            if ($article && ($autre = $parReference->get($cleRef)) && $autre->id !== $article->id) {
                $a->erreur(C::ONGLET_ARTICLES, $n, "Référence « {$reference} » déjà portée par « {$autre->name} ».");
                continue;
            }

            if ($designation === null && $article === null) {
                $a->erreur(C::ONGLET_ARTICLES, $n, 'Désignation obligatoire pour un nouvel article.');
                continue;
            }

            $uniteSaisie = trim((string) ($ligne['unite'] ?? ''));
            $unite = $uniteSaisie === '' ? $article?->unit : $unites->get(mb_strtolower($uniteSaisie));
            if ($unite === null) {
                $a->erreur(C::ONGLET_ARTICLES, $n, $uniteSaisie === ''
                    ? 'Unité obligatoire pour un nouvel article.'
                    : "Unité « {$uniteSaisie} » inconnue. Unités possibles : " . $unites->values()->implode(', ') . '.');
                continue;
            }
            if ($article && $unite !== $article->unit && ((float) $article->current_stock != 0 || isset($avecMouvements[$article->id]))) {
                $a->erreur(C::ONGLET_ARTICLES, $n, "« {$article->name} » a déjà du stock en {$article->unit} : son unité ne change pas.");
                continue;
            }

            $stockMin = $ligne['stock_min'] ?? null;
            if ($stockMin !== null && $stockMin !== '' && ($this->nombre($stockMin) === null || $this->nombre($stockMin) < 0)) {
                $a->erreur(C::ONGLET_ARTICLES, $n, 'Stock minimum : un nombre positif est attendu.');
                continue;
            }

            $categorie = $a->categorie((string) ($ligne['categorie'] ?? ''));
            $fournisseur = trim((string) ($ligne['fournisseur'] ?? ''));
            if ($fournisseur !== '') {
                $a->fournisseur($fournisseur);
            }

            $voulu = [
                'reference'   => $reference,
                'name'        => $designation ?? $article->name,
                'unit'        => $unite,
                'categorie'   => $categorie,
                'fournisseur' => $fournisseur !== '' ? $fournisseur : null,
                'description' => $this->texte($ligne['description'] ?? null, 2000),
                'min_stock'   => ($stockMin === null || $stockMin === '') ? null : $this->nombre($stockMin),
                'is_active'   => $this->actif($ligne['actif'] ?? null),
            ];

            $a->ajouterArticle($cleRef, $n, $article, $voulu);
        }
    }

    private function analyserMouvements(AnalyseClasseur $a, array $lignes, ?ServiceStore $depot): void
    {
        // Ce que le magasin contient déjà, article par article.
        $stocks = $depot
            ? ServiceStoreStock::query()->where('service_store_id', $depot->id)->pluck('current_stock', 'stock_item_id')->map(fn ($q) => (float) $q)
            : StockItem::query()->pluck('current_stock', 'id')->map(fn ($q) => (float) $q);

        $dejaVecus = $depot
            ? ServiceStoreMovement::query()->where('service_store_id', $depot->id)
                ->get(['stock_item_id', 'source_type', 'details'])
                ->reject(fn ($m) => ($m->details['nature'] ?? null) === 'stock_initial')
                ->pluck('stock_item_id')->flip()
            : StockMovement::query()->where('source_type', '!=', StockMovement::SOURCE_OPENING)
                ->where('type', '!=', StockMovement::TYPE_PACKAGING)
                ->distinct()->pluck('stock_item_id')->flip();

        // Rien avant le dernier inventaire clôturé : il a fixé le stock.
        $dernierInventaire = $depot
            ? ServiceStoreCount::query()->where('service_store_id', $depot->id)->where('status', ServiceStoreCount::STATUS_CLOSED)->max('closed_at')
            : StockCount::query()->where('status', StockCount::STATUS_CLOSED)->max('closed_at');
        $dernierInventaire = $dernierInventaire ? CarbonImmutable::parse($dernierInventaire)->startOfDay() : null;

        $table = $depot ? ServiceStoreMovement::query()->where('service_store_id', $depot->id) : StockMovement::query();
        $connus = (clone $table)->whereKey(collect($lignes)->map(fn ($l) => $this->entier($l['id'] ?? null))->filter()->all())
            ->get()->keyBy('id');

        $aujourdhui = CarbonImmutable::today();
        $occurrences = [];
        $nouveaux = [];

        foreach ($lignes as $ligne) {
            $n = $ligne['_ligne'];

            if (($id = $this->entier($ligne['id'] ?? null)) !== null) {
                $connu = $connus->get($id);
                if ($connu === null) {
                    $a->erreur(C::ONGLET_MOUVEMENTS, $n, "N° {$id} inconnu dans ce magasin. Videz le N° s'il s'agit d'un nouveau mouvement.");
                    continue;
                }
                $quantite = $this->nombre($ligne['quantite'] ?? null);
                if ($quantite !== null && abs(abs($quantite) - abs((float) $connu->quantity)) > 0.0005) {
                    $a->avertissement(C::ONGLET_MOUVEMENTS, $n, "Mouvement N° {$id} modifié dans le fichier : il est déjà enregistré et ne change pas. Corrigez par un ajustement.");
                }
                $a->dejaEnregistres++;
                continue;
            }

            $reference = $this->code($ligne['reference'] ?? null);
            $type = C::type($ligne['type'] ?? null);
            $quantite = $this->nombre($ligne['quantite'] ?? null);
            $date = $this->date($ligne['date'] ?? null);
            $cout = $ligne['cout'] ?? null;
            $destination = $this->texte($ligne['destination'] ?? null, 255);

            $erreurs = [];
            $article = $reference !== null ? $a->articles[mb_strtolower($reference)] ?? $a->articleConnu($reference) : null;
            if ($reference === null) {
                $erreurs[] = 'référence manquante';
            } elseif ($article === null) {
                $erreurs[] = "référence « {$reference} » absente de l'onglet Articles";
            }
            if ($type === null) {
                $erreurs[] = 'type « ' . ($ligne['type'] ?? '') . ' » inconnu (' . implode(', ', C::TYPES) . ')';
            }
            if ($date === null) {
                $erreurs[] = ($ligne['date'] ?? null) ? 'date illisible (jj/mm/aaaa)' : 'date manquante';
            } elseif ($date->greaterThan($aujourdhui)) {
                $erreurs[] = 'date à venir';
            } elseif ($dernierInventaire && $date->lessThan($dernierInventaire)) {
                $erreurs[] = 'date antérieure au dernier inventaire du magasin (' . $dernierInventaire->format('d/m/Y') . ')';
            }
            if ($quantite === null) {
                $erreurs[] = 'quantité manquante ou illisible';
            } elseif ($type === C::TYPE_AJUSTEMENT ? abs($quantite) < 0.0005 : $quantite <= 0) {
                $erreurs[] = $type === C::TYPE_AJUSTEMENT ? 'un ajustement corrige une quantité non nulle' : 'la quantité doit être positive';
            }
            $coutValeur = ($cout === null || $cout === '') ? null : $this->nombre($cout);
            if ($cout !== null && $cout !== '' && ($coutValeur === null || $coutValeur < 0)) {
                $erreurs[] = 'coût unitaire illisible';
            }
            if ($type === C::TYPE_SORTIE && $destination === null) {
                $erreurs[] = 'destination / bénéficiaire obligatoire pour une sortie';
            }

            if ($erreurs !== []) {
                $a->erreur(C::ONGLET_MOUVEMENTS, $n, ucfirst(implode(' ; ', $erreurs)) . '.');
                continue;
            }

            $fournisseur = trim((string) ($ligne['fournisseur'] ?? ''));
            if ($fournisseur !== '') {
                $a->fournisseur($fournisseur);
            }

            $mouvement = [
                'ligne'       => $n,
                'reference'   => $article['reference'],
                'designation' => $article['name'],
                'unite'       => $article['unit'],
                'date'        => $date->toDateString(),
                'type'        => $type,
                'quantite'    => round($quantite, 3),
                'cout'        => $coutValeur !== null ? (int) round($coutValeur) : null,
                'fournisseur' => $fournisseur !== '' ? $fournisseur : null,
                'document'    => $this->texte($ligne['document'] ?? null, 120),
                'destination' => $destination,
                'pointe'      => $this->pointe($ligne['pointe'] ?? null),
                'motif'       => $this->texte($ligne['motif'] ?? null, 500),
            ];

            // Empreinte : la même ligne, réimportée, se reconnaît.
            $base = hash('sha256', json_encode([$depot?->id, $mouvement['date'], mb_strtolower($mouvement['reference']), $type,
                $mouvement['quantite'], $mouvement['cout'], $mouvement['document'], $destination, $mouvement['motif']]));
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
            $mouvement['cle'] = substr(hash('sha256', $base . '#' . $occurrences[$base]), 0, 64);

            $nouveaux[] = $mouvement;
        }

        $dejaImportees = $nouveaux === [] ? collect() : (clone $table)
            ->whereIn('import_key', array_column($nouveaux, 'cle'))->pluck('import_key')->flip();

        $nouveaux = array_values(array_filter($nouveaux, function ($m) use ($a, $dejaImportees) {
            if (isset($dejaImportees[$m['cle']])) {
                $a->dejaImportes++;

                return false;
            }

            return true;
        }));

        // Dans l'ordre des dates : le stock se suit article par article.
        usort($nouveaux, fn ($x, $y) => [$x['date'], $x['ligne']] <=> [$y['date'], $y['ligne']]);

        $sansCout = 0;
        $clos = [];
        foreach ($nouveaux as &$m) {
            $cle = mb_strtolower($m['reference']);
            $article = $a->articles[$cle] ?? null;
            $id = $article['id'] ?? null;
            $stock = $a->stockSimule[$cle] ??= ($id !== null ? (float) ($stocks[$id] ?? 0) : 0.0);
            $vecu = $a->vecu[$cle] ??= ($id !== null && isset($dejaVecus[$id]));

            if ($m['type'] === C::TYPE_INITIAL && $vecu) {
                $a->erreur(C::ONGLET_MOUVEMENTS, $m['ligne'], "Stock initial impossible : « {$m['designation']} » a déjà des mouvements dans ce magasin. Passez une Entrée ou un Ajustement.");
                continue;
            }

            $apres = match ($m['type']) {
                C::TYPE_SORTIE     => $stock - $m['quantite'],
                C::TYPE_AJUSTEMENT => $stock + $m['quantite'],
                default            => $stock + $m['quantite'],
            };
            if ($apres < -0.0005) {
                $a->erreur(C::ONGLET_MOUVEMENTS, $m['ligne'], sprintf(
                    'Stock insuffisant : %s %s de « %s » le %s, %s demandé(s).',
                    $this->format($stock), $m['unite'], $m['designation'], Carbon::parse($m['date'])->format('d/m/Y'), $this->format(abs($m['quantite']))
                ));
                continue;
            }

            $a->stockSimule[$cle] = round($apres, 3);
            if ($m['type'] !== C::TYPE_INITIAL) {
                $a->vecu[$cle] = true;
            }

            if (in_array($m['type'], [C::TYPE_INITIAL, C::TYPE_ENTREE], true) && !$m['cout']) {
                $sansCout++;
            }

            // Journée close en comptabilité : enregistré aujourd'hui.
            $m['date_effective'] = $m['date'];
            if (NightAudit::isClosed(Carbon::parse($m['date']))) {
                $m['date_effective'] = $aujourdhui->toDateString();
                $clos[] = $m['ligne'];
            }

            $a->mouvements[] = $m;
        }
        unset($m);

        if ($sansCout > 0) {
            $a->avertissement(C::ONGLET_MOUVEMENTS, null, "{$sansCout} arrivée(s) (stock initial ou entrée) sans coût unitaire : la marchandise entrera à 0 FCFA et ne se valorisera pas. Complétez les coûts avant d'importer si vous les connaissez.");
        }
        if ($clos !== []) {
            $a->avertissement(C::ONGLET_MOUVEMENTS, null, count($clos) . ' mouvement(s) datés d\'une journée déjà clôturée en comptabilité (lignes ' . $this->plageDeLignes($clos) . ') : ils seront enregistrés à la date du jour, leur date d\'origine dans le motif.');
        }
    }

    // ── Application ──────────────────────────────────────────────────────────

    /**
     * Applique l'import : relit le fichier, le réanalyse, puis écrit tout ou
     * rien. Rend l'analyse appliquée.
     *
     * @throws RuntimeException  si le fichier a désormais des erreurs
     */
    public function appliquer(StockImport $import): array
    {
        if (!$import->isDraft()) {
            throw new RuntimeException("L'import {$import->reference} est déjà appliqué.");
        }

        $depot = $import->store;
        $analyse = $this->analyser($this->lire(\Illuminate\Support\Facades\Storage::path($import->file_path)), $depot);

        if ($analyse['erreurs'] !== []) {
            $import->update(['analysis' => $analyse]);

            throw new RuntimeException('Le fichier a des erreurs : corrigez-les puis déposez-le de nouveau.');
        }

        DB::transaction(function () use ($import, $analyse, $depot) {
            // Un seul import à la fois : verrou sur la ligne de l'import.
            $import = StockImport::query()->lockForUpdate()->findOrFail($import->id);
            if (!$import->isDraft()) {
                throw new RuntimeException("L'import {$import->reference} est déjà appliqué.");
            }

            $tenantId = Auth::user()?->tenant_id ?? Tenant::current()?->id;
            $categories = $this->creerCategories($analyse['categories_a_creer'], $tenantId);
            $fournisseurs = $this->creerFournisseurs($analyse['fournisseurs_a_creer'], $tenantId);
            $articles = $this->enregistrerArticles($analyse['articles'], $categories, $fournisseurs, $tenantId);

            foreach ($analyse['mouvements'] as $m) {
                $article = $articles[mb_strtolower($m['reference'])];
                $depot
                    ? $this->mouvementDuDepot($import, $depot, $article, $m)
                    : $this->mouvementDuMagasin($import, $article, $m);
            }

            $import->update([
                'status'     => StockImport::STATUS_APPLIED,
                'analysis'   => $analyse,
                'applied_by' => Auth::id(),
                'applied_at' => now(),
            ]);
        });

        $r = $analyse['resume'];
        AuditLog::record(Auth::id(), 'stock_workbook_import',
            "Import du classeur {$import->reference} ({$import->magasinLabel()}) : {$r['articles_crees']} article(s) créé(s), "
            . "{$r['articles_modifies']} modifié(s), {$r['mouvements']} mouvement(s)",
            'economat');

        return $analyse;
    }

    /** @return array<string, StockCategory> par nom en minuscules */
    private function creerCategories(array $noms, ?int $tenantId): array
    {
        $toutes = StockCategory::query()->get()->keyBy(fn ($c) => mb_strtolower(trim($c->name)))->all();
        foreach ($noms as $nom) {
            $toutes[mb_strtolower($nom)] ??= StockCategory::create(['name' => $nom, 'tenant_id' => $tenantId]);
        }

        return $toutes;
    }

    /** @return array<string, Supplier> par nom en minuscules */
    private function creerFournisseurs(array $aCreer, ?int $tenantId): array
    {
        $tous = Supplier::query()->get()->keyBy(fn ($f) => mb_strtolower(trim($f->name)))->all();
        foreach ($aCreer as $nom => $details) {
            $tous[mb_strtolower($nom)] ??= Supplier::create([...$details, 'name' => $nom, 'is_active' => true, 'tenant_id' => $tenantId]);
        }

        return $tous;
    }

    /** @return array<string, StockItem> par référence en minuscules */
    private function enregistrerArticles(array $lignes, array $categories, array $fournisseurs, ?int $tenantId): array
    {
        $articles = [];

        foreach ($lignes as $ligne) {
            $v = $ligne['voulu'];

            if ($ligne['action'] === 'creer') {
                $articles[$ligne['cle']] = StockItem::create([
                    'reference'         => $v['reference'],
                    'name'              => $v['name'],
                    'unit'              => $v['unit'],
                    'stock_category_id' => $v['categorie'] ? $categories[mb_strtolower($v['categorie'])]->id : null,
                    'supplier_id'       => $v['fournisseur'] ? $fournisseurs[mb_strtolower($v['fournisseur'])]->id : null,
                    'description'       => $v['description'],
                    'min_stock'         => $v['min_stock'] ?? 0,
                    'current_stock'     => 0,
                    'average_cost'      => 0,
                    'is_active'         => $v['is_active'] ?? true,
                    'tenant_id'         => $tenantId,
                ]);

                continue;
            }

            $article = StockItem::query()->findOrFail($ligne['id']);
            $change = $ligne['changements'];

            $champs = array_filter([
                'reference'   => isset($change['reference']) ? $v['reference'] : null,
                'name'        => isset($change['name']) ? $v['name'] : null,
                'unit'        => isset($change['unit']) ? $v['unit'] : null,
                'supplier_id' => isset($change['fournisseur']) ? $fournisseurs[mb_strtolower($v['fournisseur'])]->id : null,
                'description' => isset($change['description']) ? $v['description'] : null,
                'min_stock'   => isset($change['min_stock']) ? $v['min_stock'] : null,
                'is_active'   => isset($change['is_active']) ? $v['is_active'] : null,
            ], fn ($valeur) => $valeur !== null);

            if ($champs !== []) {
                $article->update($champs);
            }

            // Changer de catégorie peut changer de compte de stock : la valeur
            // déjà en stock suit l'article au grand livre, comme depuis sa fiche.
            if (isset($change['categorie'])) {
                $this->comptesDeStock->moveItem($article, $categories[mb_strtolower($v['categorie'])]->id);
            }

            $articles[$ligne['cle']] = $article->fresh();
        }

        return $articles;
    }

    private function mouvementDuMagasin(StockImport $import, StockItem $article, array $m): void
    {
        $motif = $this->motif($m);
        $cout = (int) (($m['cout'] ?? 0) * 100);

        $mouvement = match ($m['type']) {
            C::TYPE_INITIAL    => $this->stock->recordIn($article, $m['quantite'], $cout, StockMovement::SOURCE_OPENING, null, $motif),
            C::TYPE_ENTREE     => $this->stock->recordIn($article, $m['quantite'], $cout, StockMovement::SOURCE_IMPORT, $import->id, $motif),
            C::TYPE_RETOUR     => $this->stock->recordIn($article, $m['quantite'], (int) $article->fresh()->average_cost, StockMovement::SOURCE_IMPORT, $import->id, $motif),
            C::TYPE_SORTIE     => $this->stock->recordOut($article, $m['quantite'], StockMovement::SOURCE_EXTERNAL_ISSUE, null, $motif),
            C::TYPE_AJUSTEMENT => $this->stock->adjust($article, round((float) $article->fresh()->current_stock + $m['quantite'], 3), $motif, StockMovement::SOURCE_IMPORT, $import->id),
        };

        $mouvement?->update($this->traces($import, $m, true));
    }

    private function mouvementDuDepot(StockImport $import, ServiceStore $depot, StockItem $article, array $m): void
    {
        $motif = $this->motif($m);
        $traces = $this->traces($import, $m, true);

        if (in_array($m['type'], [C::TYPE_INITIAL, C::TYPE_ENTREE], true)) {
            // Par le magasin central, puis livré au dépôt : comme une demande livrée.
            $initial = $m['type'] === C::TYPE_INITIAL;
            $entree = $this->stock->recordIn($article, $m['quantite'], (int) (($m['cout'] ?? 0) * 100),
                $initial ? StockMovement::SOURCE_OPENING : StockMovement::SOURCE_IMPORT, $initial ? null : $import->id,
                $motif . " — pour le dépôt {$depot->name}");
            $entree->update($this->traces($import, $m, false));

            $livraison = $this->stock->recordOut($article, $m['quantite'], StockMovement::SOURCE_IMPORT, $import->id,
                "Livraison au dépôt {$depot->name} — import {$import->reference}");
            $livraison->update([...$this->traces($import, $m, false), 'details' => ['vers_depot' => $depot->name]]);

            $this->depots->receive($depot, $article, $m['quantite'], (int) $livraison->unit_cost,
                ServiceStoreMovement::SOURCE_IMPORT, $import->id, $motif)->update($traces);

            return;
        }

        $mouvement = match ($m['type']) {
            C::TYPE_RETOUR => $this->depots->receive($depot, $article, $m['quantite'], $this->coutAuDepot($depot, $article),
                ServiceStoreMovement::SOURCE_RETOUR, $import->id, $motif),
            C::TYPE_SORTIE => $this->depots->issue($depot, $article, $m['quantite'], ServiceStoreMovement::SOURCE_BENEFICIAIRE, $import->id, $motif),
            C::TYPE_AJUSTEMENT => $this->depots->ajuster($depot, $article, $m['quantite'], ServiceStoreMovement::SOURCE_IMPORT, $import->id, $motif),
        };

        $mouvement->update($traces);
    }

    private function coutAuDepot(ServiceStore $depot, StockItem $article): int
    {
        return (int) (ServiceStoreStock::query()->where('service_store_id', $depot->id)->where('stock_item_id', $article->id)->value('average_cost')
            ?? $article->average_cost);
    }

    /** Date, rattachement à l'import, précisions du fichier, empreinte. */
    private function traces(StockImport $import, array $m, bool $principal): array
    {
        $date = Carbon::parse($m['date_effective']);

        return [
            'occurred_at'     => $date->isToday() ? now() : $date->setTime(12, 0),
            'stock_import_id' => $import->id,
            'details'         => array_filter([
                'nature'       => match ($m['type']) {
                    C::TYPE_INITIAL => 'stock_initial',
                    C::TYPE_RETOUR  => 'retour',
                    default         => null,
                },
                'fournisseur'  => $m['fournisseur'],
                'document'     => $m['document'],
                'beneficiaire' => $m['type'] === C::TYPE_SORTIE ? $m['destination'] : null,
                'destination'  => $m['type'] !== C::TYPE_SORTIE ? $m['destination'] : null,
                'pointe'       => $m['pointe'],
                'ligne'        => $m['ligne'],
            ], fn ($v) => $v !== null),
            'import_key'      => $principal ? $m['cle'] : null,
        ];
    }

    private function motif(array $m): string
    {
        $libelle = match ($m['type']) {
            C::TYPE_INITIAL    => 'Stock initial',
            C::TYPE_ENTREE     => 'Entrée en stock',
            C::TYPE_SORTIE     => 'Remis à ' . $m['destination'],
            C::TYPE_RETOUR     => 'Retour en stock',
            C::TYPE_AJUSTEMENT => 'Ajustement',
        };

        $morceaux = array_filter([
            $libelle,
            $m['document'] ? 'doc. ' . $m['document'] : null,
            $m['motif'],
            $m['date_effective'] !== $m['date'] ? 'daté du ' . Carbon::parse($m['date'])->format('d/m/Y') . ' dans le fichier' : null,
        ]);

        return mb_substr(implode(' — ', $morceaux), 0, 255);
    }

    // ── Lecture des valeurs ──────────────────────────────────────────────────

    private function texte(mixed $valeur, int $max): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim(is_float($valeur) && floor($valeur) == $valeur ? (string) (int) $valeur : (string) $valeur);

        return $texte === '' ? null : mb_substr($texte, 0, $max);
    }

    /** Une référence : un nombre saisi (1520) se lit « 1520 », pas « 1520.0 ». */
    private function code(mixed $valeur): ?string
    {
        return $this->texte($valeur, 255);
    }

    private function entier(mixed $valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur;
        }
        if (is_float($valeur) && floor($valeur) == $valeur) {
            return (int) $valeur;
        }
        if (is_string($valeur) && ctype_digit(trim($valeur))) {
            return (int) trim($valeur);
        }

        return null;
    }

    private function nombre(mixed $valeur): ?float
    {
        if (is_int($valeur) || is_float($valeur)) {
            return (float) $valeur;
        }
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}"], '', (string) $valeur);
        $texte = str_replace(',', '.', $texte);

        return is_numeric($texte) ? (float) $texte : null;
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        if (is_int($valeur) || is_float($valeur)) {
            return CarbonImmutable::instance(DateExcel::excelToDateTimeObject($valeur))->startOfDay();
        }
        if ($valeur instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valeur)->startOfDay();
        }
        foreach (['d/m/Y', 'd/m/y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $date = CarbonImmutable::createFromFormat('!' . $format, trim((string) $valeur));
            if ($date !== false && $date->format($format) === trim((string) $valeur)) {
                return $date;
            }
        }

        return null;
    }

    private function actif(mixed $valeur): ?bool
    {
        return match (C::cle((string) $valeur)) {
            '' => null,
            'non', 'n', 'no', '0', 'inactif', 'false' => false,
            default => true,
        };
    }

    private function pointe(mixed $valeur): ?bool
    {
        return match (C::cle((string) $valeur)) {
            '' => null,
            'non', 'n', 'no', '0', 'false' => false,
            default => true,
        };
    }

    private function format(float $quantite): string
    {
        return rtrim(rtrim(number_format($quantite, 3, ',', ' '), '0'), ',');
    }

    /** « 5, 6, 9 » ou « 5 à 40 (12 lignes) ». */
    private function plageDeLignes(array $lignes): string
    {
        sort($lignes);

        return count($lignes) <= 6 ? implode(', ', $lignes) : reset($lignes) . ' à ' . end($lignes) . ' (' . count($lignes) . ' lignes)';
    }
}
