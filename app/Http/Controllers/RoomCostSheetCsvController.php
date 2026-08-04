<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\RoomCostItem;
use App\Models\RoomType;
use App\Services\RoomCostingService;
use Illuminate\Http\Request;

/**
 * Export tableur des fiches techniques.
 *
 * Pensé pour le déploiement chez un client : on exporte le squelette des
 * fiches, le personnel le remplit dans Excel — un outil qu'il maîtrise déjà —
 * et les données sont ensuite reversées dans la plateforme.
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

    public function __construct(private RoomCostingService $costing)
    {
    }

    /**
     * Export des fiches. Sans sélection, tout le catalogue actif part dans un
     * seul fichier ; avec « types[] », seules les fiches cochées.
     */
    public function export(Request $request)
    {
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
}
