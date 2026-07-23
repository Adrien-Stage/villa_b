<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import / export CSV du restaurant : plats du menu et articles du
 * garde-manger. Doublons repérés par nom dans chaque catalogue.
 */
class RestaurantCsvController extends Controller
{
    use HandlesCsv;

    private const MENU_HEADERS   = ['categorie', 'nom', 'description', 'type', 'prix_fcfa', 'services', 'actif'];
    private const PANTRY_HEADERS = ['categorie', 'nom', 'unite', 'preparation', 'unite_achat', 'conversion_achat', 'cout_fcfa', 'stock_min', 'actif'];

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
