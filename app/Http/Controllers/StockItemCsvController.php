<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import / export CSV des articles de l'économat (magasin central). Doublons
 * repérés par nom. Le stock démarre à zéro, sauf reprise du stock initial
 * (colonne facultative) : elle passe par un mouvement, pour que toute
 * quantité ait une trace.
 */
class StockItemCsvController extends Controller
{
    use HandlesCsv;

    private const HEADERS = ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock_min', 'cout_moyen_fcfa', 'actif'];

    /**
     * Colonne facultative du modèle : la quantité déjà en magasin, reprise au
     * coût moyen de la ligne. Facultative pour que les fichiers déjà préparés
     * sans elle restent importables.
     */
    private const OPENING_STOCK = 'stock_initial';

    public function export(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_articles_economat.csv', [...self::HEADERS, self::OPENING_STOCK], [
                ['Savon liquide', 'SAV-01', 'litre', 'Produits d\'entretien', 'Grossiste Central', '10', '1200', 'oui', '24'],
                ['Drap blanc', '', 'pièce', 'Linge', '', '5', '8000', 'oui', ''],
            ]);
        }

        $rows = StockItem::with('category', 'supplier')->orderBy('name')->get()
            ->map(fn (StockItem $it) => [
                $it->name,
                $it->reference,
                $it->unit,
                $it->category?->name,
                $it->supplier?->name,
                rtrim(rtrim(number_format((float) $it->min_stock, 3, '.', ''), '0'), '.'),
                (int) round($it->average_cost / 100),
                $it->is_active ? 'oui' : 'non',
            ])->all();

        return $this->streamCsv('articles_economat_' . now()->format('Ymd_His') . '.csv', self::HEADERS, $rows);
    }

    public function import(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId       = $this->csvTenantId();
        $categoriesByName = StockCategory::all()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $suppliersByName  = Supplier::all()->keyBy(fn ($s) => mb_strtolower(trim($s->name)));
        $existingNames  = StockItem::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();

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

            // Catégorie et fournisseur sont facultatifs : renseignés mais
            // introuvables, on le signale plutôt que de créer en silence.
            $category = null;
            $catName  = trim((string) ($row['categorie'] ?? ''));
            if ($catName !== '') {
                $category = $categoriesByName->get(mb_strtolower($catName));
                if (!$category) {
                    $errors[] = "Ligne {$line} : catégorie « {$catName} » introuvable.";
                    continue;
                }
            }

            $supplier = null;
            $supName  = trim((string) ($row['fournisseur'] ?? ''));
            if ($supName !== '') {
                $supplier = $suppliersByName->get(mb_strtolower($supName));
                if (!$supplier) {
                    $errors[] = "Ligne {$line} : fournisseur « {$supName} » introuvable — créez-le d'abord.";
                    continue;
                }
            }

            $cost = $row['cout_moyen_fcfa'] ?? '';
            if ($cost !== '' && (!is_numeric($cost) || (float) $cost < 0)) {
                $errors[] = "Ligne {$line} : cout_moyen_fcfa invalide.";
                continue;
            }

            $initial = trim((string) ($row[self::OPENING_STOCK] ?? ''));
            if ($initial !== '' && (!is_numeric($initial) || (float) $initial < 0)) {
                $errors[] = "Ligne {$line} : stock_initial invalide.";
                continue;
            }
            $initial = $initial === '' ? 0.0 : (float) $initial;
            // Un stock repris sans coût fausserait le CUMP de toutes les entrées suivantes.
            if ($initial > 0 && ($cost === '' || (float) $cost <= 0)) {
                $errors[] = "Ligne {$line} : un stock_initial exige un cout_moyen_fcfa.";
                continue;
            }

            $item = StockItem::create([
                'name'              => $name,
                'reference'         => trim((string) ($row['reference'] ?? '')) ?: null,
                'unit'              => trim((string) ($row['unite'] ?? '')) ?: 'pièce',
                'stock_category_id' => $category?->id,
                'supplier_id'       => $supplier?->id,
                'min_stock'         => is_numeric($row['stock_min'] ?? null) ? (float) $row['stock_min'] : 0,
                'current_stock'     => 0, // alimenté ensuite par réception / ajustement
                'average_cost'      => $cost !== '' ? (int) round((float) $cost * 100) : 0,
                'is_active'         => $this->parseBool($row['actif'] ?? 'oui'),
                'tenant_id'         => $tenantId,
            ]);
            if ($initial > 0) {
                try {
                    app(StockService::class)->recordOpening($item, $initial, (int) round((float) $cost * 100), 'Reprise du stock initial (import CSV)');
                } catch (\RuntimeException $e) {
                    // Inventaire en cours : l'article existe, sa reprise attendra.
                    $errors[] = "Ligne {$line} : article créé, stock initial non repris — " . $e->getMessage();
                }
            }

            $existingNames[mb_strtolower($name)] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'stock_items_import',
            "Import CSV d'articles économat : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'economat');

        return $this->csvImportRedirect('economat.items.index', [], $created, $skipped, $errors,
            'article(s) créé(s)');
    }
}
