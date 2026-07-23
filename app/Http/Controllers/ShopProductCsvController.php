<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import / export CSV des articles de la boutique. Doublons repérés par SKU ;
 * un SKU vide est généré automatiquement (ART-XXXXXX).
 */
class ShopProductCsvController extends Controller
{
    use HandlesCsv;

    private const HEADERS = ['sku', 'nom', 'categorie', 'description', 'prix_fcfa', 'stock', 'seuil_reappro', 'actif'];

    public function export(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_articles_boutique.csv', self::HEADERS, [
                ['ART-000001', 'Bracelet artisanal', 'Souvenirs', 'Perles locales', '5000', '20', '5', 'oui'],
                ['', 'Carte postale', 'Souvenirs', '', '500', '100', '20', 'oui'],
            ]);
        }

        $rows = ShopProduct::with('category')->orderBy('name')->get()
            ->map(fn (ShopProduct $p) => [
                $p->sku,
                $p->name,
                $p->category?->name,
                $p->description,
                (int) round($p->price / 100),
                $p->stock_quantity,
                $p->reorder_level,
                $p->is_active ? 'oui' : 'non',
            ])->all();

        return $this->streamCsv('articles_boutique_' . now()->format('Ymd_His') . '.csv', self::HEADERS, $rows);
    }

    public function import(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId       = $this->csvTenantId();
        $categoriesByName = ShopCategory::all()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $existingSkus   = ShopProduct::pluck('sku')->map(fn ($s) => mb_strtoupper(trim($s)))->flip();

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

            $catName = trim((string) ($row['categorie'] ?? ''));
            if ($catName === '') {
                $errors[] = "Ligne {$line} : catégorie obligatoire.";
                continue;
            }
            $category = $categoriesByName->get(mb_strtolower($catName));
            if (!$category) {
                $errors[] = "Ligne {$line} : catégorie « {$catName} » introuvable — créez-la d'abord dans la boutique.";
                continue;
            }

            $price = $row['prix_fcfa'] ?? '';
            if (!is_numeric($price) || (float) $price < 0) {
                $errors[] = "Ligne {$line} : prix_fcfa invalide (nombre en FCFA attendu).";
                continue;
            }

            $sku = mb_strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                $sku = $this->generateSku($existingSkus);
            } elseif (isset($existingSkus[$sku])) {
                $skipped++;
                continue;
            }

            ShopProduct::create([
                'shop_category_id' => $category->id,
                'name'             => $name,
                'description'      => trim((string) ($row['description'] ?? '')) ?: null,
                'sku'              => $sku,
                'price'            => (int) round((float) $price * 100), // FCFA -> centimes
                'stock_quantity'   => (int) ($row['stock'] ?? 0),
                'reorder_level'    => (int) ($row['seuil_reappro'] ?? 0),
                'is_active'        => $this->parseBool($row['actif'] ?? 'oui'),
                'tenant_id'        => $tenantId,
            ]);
            $existingSkus[$sku] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'shop_products_import',
            "Import CSV d'articles boutique : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'shop');

        return $this->csvImportRedirect('shop.products.index', [], $created, $skipped, $errors, 'article(s) créé(s)');
    }

    /** SKU séquentiel ART-XXXXXX qui évite les collisions déjà rencontrées dans le lot. */
    private function generateSku(\Illuminate\Support\Collection $existing): string
    {
        $max = 0;
        foreach ($existing->keys() as $sku) {
            if (preg_match('/^ART-(\d+)$/', (string) $sku, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('ART-%06d', $max + 1);
    }
}
