<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Services\RestaurantRecipeWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import / export CSV du restaurant : plats du menu, articles du
 * garde-manger et fiches techniques (recettes).
 */
class RestaurantCsvController extends Controller
{
    use HandlesCsv;

    public function __construct(private RestaurantRecipeWorkbook $workbook)
    {
    }

    private const MENU_HEADERS   = ['categorie', 'nom', 'description', 'type', 'prix_fcfa', 'services', 'actif'];
    private const PANTRY_HEADERS = ['categorie', 'nom', 'unite', 'preparation', 'unite_achat', 'conversion_achat', 'cout_fcfa', 'stock_min', 'actif'];
    private const RECIPE_HEADERS = [
        'nom_fiche', 'type', 'plat_menu', 'article_produit', 'rendement',
        'notes_fiche', 'ingredient', 'quantite', 'perte_pct', 'notes_ingredient',
    ];

    private const MENU_TYPES   = ['food', 'drink', 'other'];
    private const PANTRY_UNITS = ['pcs', 'kg', 'g', 'l', 'ml'];

    // Repas acceptés à l'import : libellés FR et clés techniques → clé stockée.
    private const MEAL_MAP = [
        'breakfast' => 'breakfast', 'petit déjeuner' => 'breakfast', 'petit dejeuner' => 'breakfast',
        'petit-déjeuner' => 'breakfast', 'petit-dejeuner' => 'breakfast',
        'lunch' => 'lunch', 'déjeuner' => 'lunch', 'dejeuner' => 'lunch',
        'dinner' => 'dinner', 'dîner' => 'dinner', 'diner' => 'dinner', 'souper' => 'dinner',
    ];

    // ── Menus ─────────────────────────────────────────────────────────────────

    public function exportMenus(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_menus.csv', self::MENU_HEADERS, [
                ['Plats', 'Ndolè aux crevettes', 'Spécialité maison', 'food', '6000', 'Déjeuner|Dîner', 'oui'],
                ['Boissons', 'Jus de gingembre', 'Fait maison', 'drink', '1500', '', 'oui'],
            ]);
        }

        $rows = RestaurantMenuItem::with('category')->orderBy('name')->get()
            ->map(fn (RestaurantMenuItem $m) => [
                $m->category?->name,
                $m->name,
                $m->description,
                $m->type,
                (int) round($m->price / 100),
                collect($m->meal_services ?? [])
                    ->map(fn ($k) => RestaurantMenuItem::MEAL_SERVICES[$k] ?? $k)->implode('|'),
                $m->is_active ? 'oui' : 'non',
            ])->all();

        return $this->streamCsv('menus_' . now()->format('Ymd_His') . '.csv', self::MENU_HEADERS, $rows);
    }

    public function importMenus(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::MENU_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $categoriesByName = RestaurantMenuCategory::all()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $existingNames    = RestaurantMenuItem::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $name = trim((string) ($row['nom'] ?? ''));
            if ($name === '') {
                $errors[] = "Ligne {$line} : nom obligatoire.";
                continue;
            }
            if (isset($existingNames[mb_strtolower($name)])) {
                $skipped++;
                continue;
            }

            $type = mb_strtolower(trim((string) ($row['type'] ?? '')));
            if (!in_array($type, self::MENU_TYPES, true)) {
                $errors[] = "Ligne {$line} : type « {$row['type']} » invalide (valeurs : " . implode(', ', self::MENU_TYPES) . ').';
                continue;
            }

            $price = $row['prix_fcfa'] ?? '';
            if (!is_numeric($price) || (float) $price < 0) {
                $errors[] = "Ligne {$line} : prix_fcfa invalide (nombre en FCFA attendu).";
                continue;
            }

            $categoryId = null;
            $catName    = trim((string) ($row['categorie'] ?? ''));
            if ($catName !== '') {
                $category = $categoriesByName->get(mb_strtolower($catName));
                if (!$category) {
                    $errors[] = "Ligne {$line} : catégorie « {$catName} » introuvable — créez-la d'abord dans les menus.";
                    continue;
                }
                $categoryId = $category->id;
            }

            RestaurantMenuItem::create([
                'restaurant_menu_category_id' => $categoryId,
                'name'          => $name,
                'description'   => trim((string) ($row['description'] ?? '')) ?: null,
                'price'         => (int) round((float) $price * 100), // FCFA -> centimes
                'type'          => $type,
                'meal_services' => $this->parseMeals($row['services'] ?? ''),
                'is_active'     => $this->parseBool($row['actif'] ?? 'oui'),
            ]);
            $existingNames[mb_strtolower($name)] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'menus_import',
            "Import CSV de menus : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'restaurant');

        return $this->csvImportRedirect('restaurant.menus.index', [], $created, $skipped, $errors, 'plat(s) créé(s)');
    }

    // ── Garde-manger ────────────────────────────────────────────────────────

    public function exportPantry(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_garde_manger.csv', self::PANTRY_HEADERS, [
                ['Épicerie', 'Riz parfumé', 'g', 'non', 'sac 25kg', '25000', '900', '5000', 'oui'],
                ['Préparations', 'Sauce ndolè', 'g', 'oui', '', '', '', '2000', 'oui'],
            ]);
        }

        $rows = RestaurantPantryItem::with('category')->orderBy('name')->get()
            ->map(fn (RestaurantPantryItem $it) => [
                $it->category?->name,
                $it->name,
                $it->unit,
                $it->is_prepared ? 'oui' : 'non',
                $it->purchase_unit,
                $it->purchase_conversion ? rtrim(rtrim((string) $it->purchase_conversion, '0'), '.') : '',
                $it->cost_price !== null ? (int) round($it->cost_price / 100) : '',
                rtrim(rtrim(number_format((float) $it->min_stock, 3, '.', ''), '0'), '.'),
                $it->is_active ? 'oui' : 'non',
            ])->all();

        return $this->streamCsv('garde_manger_' . now()->format('Ymd_His') . '.csv', self::PANTRY_HEADERS, $rows);
    }

    public function importPantry(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::PANTRY_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $categoriesByName = RestaurantPantryCategory::all()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $existingNames    = RestaurantPantryItem::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $name = trim((string) ($row['nom'] ?? ''));
            if ($name === '') {
                $errors[] = "Ligne {$line} : nom obligatoire.";
                continue;
            }
            if (isset($existingNames[mb_strtolower($name)])) {
                $skipped++;
                continue;
            }

            $unit = mb_strtolower(trim((string) ($row['unite'] ?? '')));
            if (!in_array($unit, self::PANTRY_UNITS, true)) {
                $errors[] = "Ligne {$line} : unité « {$row['unite']} » invalide (valeurs : " . implode(', ', self::PANTRY_UNITS) . ').';
                continue;
            }

            $categoryId = null;
            $catName    = trim((string) ($row['categorie'] ?? ''));
            if ($catName !== '') {
                $category = $categoriesByName->get(mb_strtolower($catName));
                if (!$category) {
                    $errors[] = "Ligne {$line} : catégorie « {$catName} » introuvable — créez-la d'abord.";
                    continue;
                }
                $categoryId = $category->id;
            }

            $cost = $row['cout_fcfa'] ?? '';
            if ($cost !== '' && (!is_numeric($cost) || (float) $cost < 0)) {
                $errors[] = "Ligne {$line} : cout_fcfa invalide.";
                continue;
            }
            $costPrice = $cost !== '' ? (int) round((float) $cost * 100) : null;

            $conversion = $row['conversion_achat'] ?? '';
            RestaurantPantryItem::create([
                'restaurant_pantry_category_id' => $categoryId,
                'name'                => $name,
                'unit'                => $unit,
                'is_prepared'         => $this->parseFlag($row['preparation'] ?? ''),
                'purchase_unit'       => trim((string) ($row['unite_achat'] ?? '')) ?: null,
                'purchase_conversion' => is_numeric($conversion) && (float) $conversion > 0 ? (float) $conversion : 1,
                'min_stock'           => is_numeric($row['stock_min'] ?? null) ? (string) (float) $row['stock_min'] : '0',
                'current_stock'       => '0', // alimenté par une réception ensuite
                'cost_price'          => $costPrice,
                'average_cost'        => $costPrice ?? 0,
                'is_active'           => $this->parseBool($row['actif'] ?? 'oui'),
            ]);
            $existingNames[mb_strtolower($name)] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'pantry_import',
            "Import CSV du garde-manger : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'restaurant');

        return $this->csvImportRedirect('restaurant.pantry.index', [], $created, $skipped, $errors,
            'article(s) créé(s) — le stock initial se règle via une réception');
    }

    // ── Fiches Techniques (Recettes) ──────────────────────────────────────────

    public function exportRecipes(Request $request)
    {
        if ($request->boolean('template')) {
            $templateRows = [
                ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', '1', '', 'Sauce Ndole', '200', '0', ''],
                ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', '1', '', 'Viande de Boeuf', '125', '20', ''],
                ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', '1', '', 'Crevettes', '50', '0', ''],
                ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', '1', '', 'Plantain mûr', '2', '0', ''],
                ['Sauce Ndole', 'preparation', '', 'Sauce Ndole', '5000', '', 'Feuilles de ndolé', '2000', '0', ''],
                ['Sauce Ndole', 'preparation', '', 'Sauce Ndole', '5000', '', 'Arachide décortiquée', '1500', '0', ''],
                ['Sauce Ndole', 'preparation', '', 'Sauce Ndole', '5000', '', 'Oignon', '400', '0', ''],
                ['Sauce Ndole', 'preparation', '', 'Sauce Ndole', '5000', '', 'Huile', '400', '0', ''],
                ['Sauce Ndole', 'preparation', '', 'Sauce Ndole', '5000', '', 'Crevettes séchées & épices', '100', '0', ''],
            ];

            if ($request->query('format') === 'csv') {
                return $this->streamCsv('modele_fiches_techniques_restaurant.csv', self::RECIPE_HEADERS, $templateRows);
            }

            return $this->streamXlsx('modele_fiches_techniques_restaurant.xlsx', 'Fiches Techniques', self::RECIPE_HEADERS, $templateRows);
        }

        // « lines.item.recipe » : une ligne qui pointe une préparation doit
        // pouvoir renvoyer vers l'onglet qui la fabrique.
        $recipes = RestaurantRecipe::with([
                'lines.item.category', 'lines.item.recipe',
                'menuItem.category', 'producedItem',
            ])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $tenant = $this->tenantCourant();
        $etablissement = $tenant?->slug ? \Illuminate\Support\Str::slug($tenant->slug) . '_' : '';
        $nom = $etablissement . 'fiches_techniques_restaurant_' . now()->format('Ymd_His');

        if ($request->query('format') !== 'csv') {
            return $this->exportRecipesXlsx($recipes, $tenant, $nom);
        }

        $rows = [];
        foreach ($recipes as $recipe) {
            $recipeType       = $recipe->type === RestaurantRecipe::TYPE_PREP ? 'preparation' : 'plat';
            $menuItemName     = $recipe->menuItem?->name ?? '';
            $producedItemName = $recipe->producedItem?->name ?? '';
            $yield            = rtrim(rtrim(number_format((float) $recipe->yield_quantity, 3, ',', ''), '0'), ',');

            $base = [
                $recipe->name,
                $recipeType,
                $menuItemName,
                $producedItemName,
                $yield,
                $recipe->notes,
            ];

            if ($recipe->lines->isEmpty()) {
                $rows[] = array_merge($base, ['', '', '', '']);
                continue;
            }

            foreach ($recipe->lines as $line) {
                $rows[] = array_merge($base, [
                    $line->item?->name ?? '',
                    rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ''), '0'), ','),
                    rtrim(rtrim(number_format((float) $line->waste_percent, 2, ',', ''), '0'), ','),
                    $line->notes,
                ]);
            }
        }

        return $this->streamCsv($nom . '.csv', self::RECIPE_HEADERS, $rows);
    }

    /**
     * Classeur Excel des fiches de cuisine : tableau de bord de la carte,
     * mercuriale, puis une fiche par préparation de base et par plat — aux
     * couleurs de l'établissement, comme les fiches de l'hébergement.
     */
    private function exportRecipesXlsx($recipes, ?\App\Models\Tenant $tenant, string $nom)
    {
        $pantry = RestaurantPantryItem::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $classeur = $this->workbook->build($recipes, $pantry, $tenant);

        return response()->streamDownload(function () use ($classeur) {
            $writer = new Xlsx($classeur);
            // Excel recalcule à l'ouverture : inutile d'évaluer ici des
            // formules qui référencent une douzaine d'onglets.
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');

            // Les feuilles se référencent l'une l'autre : sans rompre ces
            // liens, le classeur reste en mémoire après l'envoi.
            $classeur->disconnectWorksheets();
        }, $nom . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function importRecipes(Request $request)
    {
        $request->validate([
            'csv_file'   => ['nullable', 'file', 'max:10240'],
            'file'       => ['nullable', 'file', 'max:10240'],
            'excel_file' => ['nullable', 'file', 'max:10240'],
        ]);

        $uploaded = $request->file('csv_file') ?? $request->file('file') ?? $request->file('excel_file');
        if (!$uploaded) {
            return back()->with('error', 'Veuillez sélectionner un fichier à importer (Excel ou CSV).');
        }

        [$rows, $parseError] = $this->parseSpreadsheet($uploaded->getRealPath(), self::RECIPE_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $menuItemsByName   = RestaurantMenuItem::all()->keyBy(fn ($m) => mb_strtolower(trim($m->name)));
        $pantryItemsByName = RestaurantPantryItem::all()->keyBy(fn ($p) => mb_strtolower(trim($p->name)));

        $recipesByName = [];
        $created = 0;
        $skipped = 0;
        $errors  = [];

        // PASSE 1 : Création / mise à jour des fiches (en-têtes et articles de garde-manger pour les préparations)
        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $recipeName = trim((string) ($row['nom_fiche'] ?? ''));
            if ($recipeName === '') {
                continue;
            }

            if (isset($recipesByName[mb_strtolower($recipeName)])) {
                continue;
            }

            $rawType = mb_strtolower(trim((string) ($row['type'] ?? '')));
            $isPrep  = in_array($rawType, ['prep', 'préparation', 'preparation'], true);
            $type    = $isPrep ? RestaurantRecipe::TYPE_PREP : RestaurantRecipe::TYPE_DISH;

            $menuItemId     = null;
            $producedItemId = null;

            if ($type === RestaurantRecipe::TYPE_DISH) {
                $platName = trim((string) ($row['plat_menu'] ?? ''));
                if ($platName === '') {
                    $platName = $recipeName;
                }
                if ($platName !== '') {
                    $menuItem = $menuItemsByName->get(mb_strtolower($platName));
                    if (!$menuItem) {
                        $errors[] = "Ligne {$line} : plat du menu « {$platName} » introuvable — créez-le d'abord dans la carte.";
                        continue;
                    }
                    $menuItemId = $menuItem->id;
                }
            } else {
                $prepItemName = trim((string) ($row['article_produit'] ?? ''));
                if ($prepItemName === '') {
                    $prepItemName = $recipeName;
                }
                if ($prepItemName !== '') {
                    $producedItem = $pantryItemsByName->get(mb_strtolower($prepItemName));
                    if (!$producedItem) {
                        $producedItem = RestaurantPantryItem::create([
                            'name'        => $prepItemName,
                            'unit'        => 'g',
                            'is_prepared' => true,
                            'is_active'   => true,
                        ]);
                        $pantryItemsByName->put(mb_strtolower($prepItemName), $producedItem);
                    }
                    $producedItemId = $producedItem->id;
                }
            }

            $yieldRaw   = str_replace(',', '.', (string) ($row['rendement'] ?? '1'));
            $yieldVal   = is_numeric($yieldRaw) && (float) $yieldRaw > 0 ? (float) $yieldRaw : 1.0;
            $notesFiche = trim((string) ($row['notes_fiche'] ?? '')) ?: null;

            // Création ou mise à jour de la fiche technique (par son nom)
            $recipe = RestaurantRecipe::where('name', $recipeName)->first();
            if ($recipe) {
                $recipe->update([
                    'type'                    => $type,
                    'restaurant_menu_item_id' => $menuItemId ?? $recipe->restaurant_menu_item_id,
                    'produces_pantry_item_id' => $producedItemId ?? $recipe->produces_pantry_item_id,
                    'yield_quantity'          => $yieldVal,
                    'notes'                   => $notesFiche ?? $recipe->notes,
                ]);
            } else {
                $recipe = RestaurantRecipe::create([
                    'name'                    => $recipeName,
                    'type'                    => $type,
                    'restaurant_menu_item_id' => $menuItemId,
                    'produces_pantry_item_id' => $producedItemId,
                    'yield_quantity'          => $yieldVal,
                    'notes'                   => $notesFiche,
                    'is_active'               => true,
                ]);
            }

            $recipesByName[mb_strtolower($recipeName)] = $recipe;
        }

        // PASSE 2 : Création / mise à jour des ingrédients (lignes de fiche)
        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $recipeName = trim((string) ($row['nom_fiche'] ?? ''));
            if ($recipeName === '') {
                $errors[] = "Ligne {$line} : nom_fiche obligatoire.";
                continue;
            }

            $recipe = $recipesByName[mb_strtolower($recipeName)] ?? null;
            if (!$recipe) {
                continue;
            }

            // Gestion de l'ingrédient (si renseigné)
            $ingredientName = trim((string) ($row['ingredient'] ?? ''));
            if ($ingredientName === '') {
                $created++;
                continue;
            }

            $pantryItem = $pantryItemsByName->get(mb_strtolower($ingredientName));
            if (!$pantryItem) {
                $errors[] = "Ligne {$line} : ingrédient « {$ingredientName} » introuvable dans le garde-manger.";
                continue;
            }

            $qtyRaw   = str_replace(',', '.', (string) ($row['quantite'] ?? '0'));
            $wasteRaw = str_replace(',', '.', (string) ($row['perte_pct'] ?? '0'));
            $quantity = is_numeric($qtyRaw) && (float) $qtyRaw > 0 ? (float) $qtyRaw : 0.001;
            $wastePct = is_numeric($wasteRaw) && (float) $wasteRaw >= 0 ? (float) $wasteRaw : 0.0;
            $notesIng = trim((string) ($row['notes_ingredient'] ?? '')) ?: null;

            $recipeLine = RestaurantRecipeLine::where('restaurant_recipe_id', $recipe->id)
                ->where('restaurant_pantry_item_id', $pantryItem->id)
                ->first();

            if ($recipeLine) {
                $recipeLine->update([
                    'quantity'      => $quantity,
                    'waste_percent' => $wastePct,
                    'notes'         => $notesIng,
                ]);
            } else {
                RestaurantRecipeLine::create([
                    'restaurant_recipe_id'      => $recipe->id,
                    'restaurant_pantry_item_id' => $pantryItem->id,
                    'quantity'                  => $quantity,
                    'waste_percent'             => $wastePct,
                    'notes'                     => $notesIng,
                ]);
            }

            $created++;
        }

        AuditLog::record(Auth::id(), 'recipes_import',
            "Import des fiches techniques : {$created} ligne(s) traitée(s), " . count($errors) . ' erreur(s)',
            'restaurant');

        return $this->csvImportRedirect('restaurant.recipes.index', [], $created, $skipped, $errors, 'ligne(s) de fiche technique traitée(s)');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** Convertit une liste « Déjeuner|Dîner » en clés [lunch, dinner]. */
    private function parseMeals(string $raw): array
    {
        $meals = [];
        foreach (explode('|', $raw) as $token) {
            $key = self::MEAL_MAP[mb_strtolower(trim($token))] ?? null;
            if ($key && !in_array($key, $meals, true)) {
                $meals[] = $key;
            }
        }

        return $meals;
    }
}
