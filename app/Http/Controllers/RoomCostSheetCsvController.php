<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\RoomCostingService;
use App\Services\RoomCostSheetWorkbook;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import / Export tableur des fiches techniques.
 *
 * Pensé pour le déploiement chez un client : on exporte le squelette des
 * fiches, le personnel le remplit dans Excel — un outil qu'il maîtrise déjà —
 * et les données sont ensuite reversées dans la plateforme via l'import.
 *
 * Le fichier est donc volontairement à plat : une ligne par poste de coût,
 * les hypothèses de la fiche répétées sur chaque ligne de son type. Un type
 * sans aucun poste sort quand même, avec ses colonnes de poste vides, sinon
 * il serait invisible dans le fichier et personne ne penserait à le remplir.
 */
class RoomCostSheetCsvController extends Controller
{
    use HandlesCsv;

    private const HEADERS = [
        'type_chambre', 'code_type',
        'occupants_reference', 'sejour_moyen_nuits', 'charge_fixe_par_nuitee_fcfa',
        'categorie', 'poste', 'base_calcul', 'quantite', 'cout_unitaire_fcfa',
        'actif', 'notes',
    ];

    public function __construct(
        private RoomCostingService $costing,
        private RoomCostSheetWorkbook $workbook,
    ) {
    }

    /**
     * Export des fiches.
     *
     * Deux formats, deux usages : le classeur Excel est le document de
     * gestion — une fiche par onglet, formules vivantes, aux couleurs de
     * l'établissement — tandis que le CSV à plat (?format=csv, ou ?template=1
     * pour le squelette) reste le format de l'aller-retour d'importation,
     * seul lisible par l'import.
     */
    public function export(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_fiches_techniques.csv', self::HEADERS, [
                ['Chambre Standard', 'STD', '2', '2,5', '5000', 'Électricité', 'Électricité & climatisation', 'Par nuitée', '1', '1200', 'oui', 'Éclairage + clim'],
                ['Chambre Standard', 'STD', '2', '2,5', '5000', 'Consommables', 'Savonnette', 'Par personne et nuitée', '2', '250', 'oui', '2 savons par personne'],
            ]);
        }

        $validated = $request->validate([
            'types'   => ['nullable', 'array'],
            'types.*' => ['integer', 'exists:room_types,id'],
        ]);

        $query = RoomType::query()->where('is_active', true)->orderBy('name');

        if (!empty($validated['types'])) {
            $query->whereIn('id', $validated['types']);
        }

        $types = $query->get();

        // Un modèle vide n'aiderait personne : sans type de chambre, on renvoie
        // l'utilisateur vers la liste plutôt que de livrer un fichier trompeur.
        if ($types->isEmpty()) {
            return back()->with('error', "Aucune fiche à exporter — créez d'abord un type de chambre.");
        }

        if ($request->query('format') !== 'csv') {
            return $this->exportXlsx($types);
        }

        $rows = [];

        foreach ($types as $type) {
            $sheet = $this->costing->sheetFor($type);
            $items = RoomCostItem::where('room_type_id', $type->id)
                ->orderBy('sort_order')->orderBy('id')->get();

            // Valeurs effectives, pas brutes : occupants repliés sur la capacité
            // de base, durée moyenne mesurée sur les séjours réels. Un fichier
            // pré-rempli de valeurs plausibles se corrige ; un fichier vide
            // laisse le personnel deviner ce qu'on attend de lui.
            $a = $sheet['assumptions'];
            $assumptions = [
                $type->name,
                $type->code,
                $a['reference_occupants'],
                str_replace('.', ',', (string) $a['avg_length_of_stay']),
                (int) round($a['fixed_cost_per_night'] / 100),
            ];

            if ($items->isEmpty()) {
                // Ligne d'amorce : le type apparaît, prêt à être complété.
                $rows[] = array_merge($assumptions, ['', '', '', '', '', '', '']);
                continue;
            }

            foreach ($items as $item) {
                $rows[] = array_merge($assumptions, [
                    RoomCostItem::CATEGORIES[$item->category] ?? $item->category,
                    $item->label,
                    RoomCostItem::BASES[$item->basis] ?? $item->basis,
                    // Quantités décimales : on coupe les zéros inutiles (2,500 → 2,5).
                    rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ''), '0'), ','),
                    (int) round($item->unit_cost / 100),
                    $item->is_active ? 'oui' : 'non',
                    $item->notes,
                ]);
            }
        }

        $nom = $types->count() === 1
            ? 'fiche_technique_' . \Illuminate\Support\Str::slug($types->first()->name)
            : 'fiches_techniques';

        return $this->streamCsv($nom . '_' . now()->format('Ymd_His') . '.csv', self::HEADERS, $rows);
    }

    /**
     * Classeur Excel des fiches : un onglet de synthèse, un onglet de coûts
     * unitaires, puis une fiche par type de chambre.
     */
    private function exportXlsx($types)
    {
        $tenant = $this->tenantCourant();
        $classeur = $this->workbook->build($types, $tenant);

        $etablissement = $tenant?->slug ? \Illuminate\Support\Str::slug($tenant->slug) . '_' : '';
        $nom = $types->count() === 1
            ? 'fiche_technique_' . \Illuminate\Support\Str::slug($types->first()->name)
            : $etablissement . 'fiches_techniques';

        return response()->streamDownload(function () use ($classeur) {
            $writer = new Xlsx($classeur);
            // Excel recalcule à l'ouverture : inutile d'évaluer ici des
            // formules qui référencent une douzaine d'onglets.
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $nom . '_' . now()->format('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Établissement en cours : c'est lui qui signe le document exporté. */
    private function tenantCourant(): ?Tenant
    {
        $id = $this->csvTenantId();

        return $id ? Tenant::find($id) : null;
    }

    /**
     * Importation d'un fichier CSV de fiches techniques.
     */
    public function import(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId = $this->csvTenantId();

        $roomTypesByName = RoomType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->name)));
        $roomTypesByCode = RoomType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->code)));

        // Tables de correspondance des catégories et des bases (accepte libellés ou clés techniques)
        $catMap = [];
        foreach (RoomCostItem::CATEGORIES as $key => $label) {
            $catMap[mb_strtolower($key)]   = $key;
            $catMap[mb_strtolower($label)] = $key;
        }

        $basisMap = [];
        foreach (RoomCostItem::BASES as $key => $label) {
            $basisMap[mb_strtolower($key)]   = $key;
            $basisMap[mb_strtolower($label)] = $key;
        }

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $typeName = trim((string) ($row['type_chambre'] ?? ''));
            $typeCode = trim((string) ($row['code_type'] ?? ''));

            $roomType = null;
            if ($typeCode !== '') {
                $roomType = $roomTypesByCode->get(mb_strtolower($typeCode));
            }
            if (!$roomType && $typeName !== '') {
                $roomType = $roomTypesByName->get(mb_strtolower($typeName));
            }

            if (!$roomType) {
                $identifier = $typeCode ?: $typeName ?: "Ligne {$line}";
                $errors[] = "Ligne {$line} : type de chambre « {$identifier} » introuvable.";
                continue;
            }

            // Hypothèses de la fiche
            $sheet = RoomCostSheet::firstOrNew(['room_type_id' => $roomType->id]);
            if (!empty($tenantId) && !$sheet->tenant_id) {
                $sheet->tenant_id = $tenantId;
            }

            if (isset($row['occupants_reference']) && trim((string) $row['occupants_reference']) !== '') {
                $sheet->reference_occupants = max(1, (int) $row['occupants_reference']);
            }
            if (isset($row['sejour_moyen_nuits']) && trim((string) $row['sejour_moyen_nuits']) !== '') {
                $val = (float) str_replace(',', '.', (string) $row['sejour_moyen_nuits']);
                if ($val > 0) {
                    $sheet->avg_length_of_stay = $val;
                }
            }
            if (isset($row['charge_fixe_par_nuitee_fcfa']) && trim((string) $row['charge_fixe_par_nuitee_fcfa']) !== '') {
                $val = (float) str_replace(',', '.', (string) $row['charge_fixe_par_nuitee_fcfa']);
                $sheet->fixed_cost_per_night = (int) round($val * 100);
            }
            $sheet->save();

            // Ligne de poste de coût
            $label = trim((string) ($row['poste'] ?? ''));
            if ($label === '') {
                // Type répertorié sans poste (ligne d'amorce ou mise à jour des hypothèses seules)
                $created++;
                continue;
            }

            $catKey   = mb_strtolower(trim((string) ($row['categorie'] ?? '')));
            $category = $catMap[$catKey] ?? 'other';

            $basisKey = mb_strtolower(trim((string) ($row['base_calcul'] ?? '')));
            $basis    = $basisMap[$basisKey] ?? RoomCostItem::BASIS_PER_NIGHT;

            $quantity = 1.0;
            if (isset($row['quantite']) && trim((string) $row['quantite']) !== '') {
                $quantity = max(0.001, (float) str_replace(',', '.', (string) $row['quantite']));
            }

            $unitCost = 0;
            if (isset($row['cout_unitaire_fcfa']) && trim((string) $row['cout_unitaire_fcfa']) !== '') {
                $val = (float) str_replace(',', '.', (string) $row['cout_unitaire_fcfa']);
                $unitCost = (int) round($val * 100);
            }

            $isActive = $this->parseBool($row['actif'] ?? 'oui');
            $notes    = trim((string) ($row['notes'] ?? '')) ?: null;

            $item = RoomCostItem::where('room_type_id', $roomType->id)
                ->where('label', $label)
                ->first();

            if ($item) {
                $item->update([
                    'category'  => $category,
                    'basis'     => $basis,
                    'quantity'  => $quantity,
                    'unit_cost' => $unitCost,
                    'is_active' => $isActive,
                    'notes'     => $notes,
                ]);
            } else {
                $maxSort = (int) RoomCostItem::where('room_type_id', $roomType->id)->max('sort_order');
                RoomCostItem::create([
                    'room_type_id' => $roomType->id,
                    'category'     => $category,
                    'label'        => $label,
                    'basis'        => $basis,
                    'quantity'     => $quantity,
                    'unit_cost'    => $unitCost,
                    'sort_order'   => $maxSort + 1,
                    'is_active'    => $isActive,
                    'notes'        => $notes,
                    'tenant_id'    => $tenantId,
                ]);
            }

            $created++;
        }

        return $this->csvImportRedirect(
            'rooms.cost_sheets.index',
            [],
            $created,
            $skipped,
            $errors,
            'ligne(s) traitée(s)'
        );
    }
}
