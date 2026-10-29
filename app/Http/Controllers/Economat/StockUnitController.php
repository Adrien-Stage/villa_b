<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\StockUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Les unités de mesure et de stockage des articles (Paramètres › Économat) :
 * l'économe les crée (nom long, nom court / symbole, valeur & unité de référence),
 * les modifie et les met hors service.
 */
class StockUnitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);

        $unite = StockUnit::create([
            'name'              => $donnees['name'],
            'short_name'        => $donnees['short_name'],
            'factor'            => $donnees['factor'],
            'reference_unit_id' => $donnees['reference_unit_id'],
            'sort_order'        => (int) StockUnit::max('sort_order') + 1,
            'is_active'         => true,
        ]);

        $this->journal("Unité de mesure « {$unite->name} ({$unite->short_name}) » créée", $unite);

        return $this->retour()->with('success', "Unité « {$unite->name} ({$unite->short_name}) » ajoutée avec succès.");
    }

    public function update(Request $request, StockUnit $unite): RedirectResponse
    {
        $donnees = $this->valider($request, $unite);
        $ancienNom = $unite->name;
        $ancienCourt = $unite->short_name;

        DB::transaction(function () use ($unite, $donnees, $ancienNom, $ancienCourt, $request) {
            $unite->update([
                'name'              => $donnees['name'],
                'short_name'        => $donnees['short_name'],
                'factor'            => $donnees['factor'],
                'reference_unit_id' => $donnees['reference_unit_id'],
                'is_active'         => $request->boolean('is_active'),
            ]);

            // Mettre à jour les articles qui portaient l'ancien nom ou ancien symbole
            if ($donnees['name'] !== $ancienNom || $donnees['short_name'] !== $ancienCourt) {
                StockItem::query()
                    ->whereIn('unit', array_filter([$ancienNom, $ancienCourt]))
                    ->update(['unit' => $donnees['short_name']]);
            }
        });

        $action = "Unité de mesure « {$donnees['name']} ({$donnees['short_name']}) » modifiée";
        $this->journal($action . ($unite->is_active ? '' : ' (hors service)'), $unite);

        return $this->retour()->with('success', "Unité « {$donnees['name']} » mise à jour.");
    }

    public function destroy(StockUnit $unite): RedirectResponse
    {
        $articles = $unite->articlesCount();
        if ($articles > 0) {
            return $this->retour()->withErrors([
                'unite' => "L'unité « {$unite->name} » est employée par {$articles} article(s) : mettez-la hors service plutôt que de la supprimer.",
            ]);
        }

        $dependances = $unite->subUnits()->count();
        if ($dependances > 0) {
            return $this->retour()->withErrors([
                'unite' => "L'unité « {$unite->name} » sert d'unité de référence à {$dependances} autre(s) unité(s). Modifiez d'abord ces unités avant de la supprimer.",
            ]);
        }

        $nom = $unite->name;
        $unite->delete();
        $this->journal("Unité de mesure « {$nom} » supprimée", $unite);

        return $this->retour()->with('success', "Unité « {$nom} » supprimée.");
    }

    /**
     * @return array{name: string, short_name: string, factor: float, reference_unit_id: ?int}
     */
    private function valider(Request $request, ?StockUnit $unite = null): array
    {
        $valide = $request->validate([
            'name'              => ['required', 'string', 'max:50'],
            'short_name'        => ['required', 'string', 'max:20'],
            'factor'            => ['nullable', 'numeric', 'gt:0'],
            'reference_unit_id' => ['nullable', 'integer', 'exists:stock_units,id'],
        ], [
            'name.required'       => "Indiquez le nom complet de l'unité (ex: Kilogramme).",
            'name.max'            => "Le nom d'une unité ne peut pas dépasser 50 caractères.",
            'short_name.required' => "Indiquez le symbole / nom court (ex: kg).",
            'short_name.max'      => "Le nom court ne peut pas dépasser 20 caractères.",
            'factor.numeric'      => "La valeur / facteur d'équivalence doit être un nombre positif.",
            'factor.gt'           => "Le facteur d'équivalence doit être strictement supérieur à 0.",
        ]);

        $nom = trim($valide['name']);
        $court = trim($valide['short_name']);
        $refId = ! empty($valide['reference_unit_id']) ? (int) $valide['reference_unit_id'] : null;
        $facteur = (float) ($valide['factor'] ?? 1.0);

        if ($unite && $refId === $unite->id) {
            $refId = null;
            $facteur = 1.0;
        }

        // Vérification de circularité
        if ($unite && $refId) {
            $curr = StockUnit::find($refId);
            $visited = [$unite->id];
            while ($curr) {
                if (in_array($curr->id, $visited, true)) {
                    throw ValidationException::withMessages([
                        'reference_unit_id' => "Cette unité de référence crée une référence circulaire.",
                    ]);
                }
                $visited[] = $curr->id;
                $curr = $curr->referenceUnit;
            }
        }

        // Unicité insensible à la casse
        $nomExiste = StockUnit::query()
            ->when($unite, fn ($q) => $q->whereKeyNot($unite->id))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])
            ->exists();

        if ($nomExiste) {
            throw ValidationException::withMessages([
                'name' => "L'unité « {$nom} » existe déjà.",
            ]);
        }

        $courtExiste = StockUnit::query()
            ->when($unite, fn ($q) => $q->whereKeyNot($unite->id))
            ->whereRaw('LOWER(short_name) = ?', [mb_strtolower($court)])
            ->exists();

        if ($courtExiste) {
            throw ValidationException::withMessages([
                'short_name' => "Le symbole / nom court « {$court} » est déjà utilisé par une autre unité.",
            ]);
        }

        return [
            'name'              => $nom,
            'short_name'        => $court,
            'factor'            => $refId ? $facteur : 1.0,
            'reference_unit_id' => $refId,
        ];
    }

    private function journal(string $action, StockUnit $unite): void
    {
        AuditLog::record(Auth::id(), 'stock_unit', $action, 'economat', ['stock_unit_id' => $unite->id]);
    }

    private function retour(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'economat']);
    }
}
