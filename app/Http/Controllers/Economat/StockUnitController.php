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
 * Les unités de stockage des articles (Paramètres › Économat) : l'économe
 * les crée, les renomme, les met hors service. La fiche d'un article choisit
 * la sienne dans cette liste.
 */
class StockUnitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $nom = $this->nom($request);

        $unite = StockUnit::create([
            'name'       => $nom,
            'sort_order' => (int) StockUnit::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        $this->journal("Unité de stockage « {$unite->name} » créée", $unite);

        return $this->retour()->with('success', "Unité « {$unite->name} » ajoutée.");
    }

    public function update(Request $request, StockUnit $unite): RedirectResponse
    {
        $nom = $this->nom($request, $unite);
        $ancien = $unite->name;

        // L'article garde le nom de son unité : il suit le renommage.
        DB::transaction(function () use ($unite, $nom, $ancien, $request) {
            $unite->update(['name' => $nom, 'is_active' => $request->boolean('is_active')]);

            if ($nom !== $ancien) {
                StockItem::query()->where('unit', $ancien)->update(['unit' => $nom]);
            }
        });

        $action = $nom !== $ancien ? "Unité de stockage « {$ancien} » renommée « {$nom} »" : "Unité de stockage « {$nom} » modifiée";
        $this->journal($action . ($unite->is_active ? '' : ' (hors service)'), $unite);

        return $this->retour()->with('success', "Unité « {$nom} » enregistrée.");
    }

    public function destroy(StockUnit $unite): RedirectResponse
    {
        $articles = $unite->articlesCount();

        // Une unité employée reste lisible sur les fiches et les bons.
        if ($articles > 0) {
            return $this->retour()->withErrors(['unite' => "L'unité « {$unite->name} » est employée par {$articles} article(s) : mettez-la hors service plutôt que de la supprimer."]);
        }

        $unite->delete();
        $this->journal("Unité de stockage « {$unite->name} » supprimée", $unite);

        return $this->retour()->with('success', "Unité « {$unite->name} » supprimée.");
    }

    private function nom(Request $request, ?StockUnit $unite = null): string
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:20'],
        ], [
            'name.required' => "Donnez le nom de l'unité.",
            'name.max'      => "Le nom d'une unité tient en 20 caractères.",
        ]);

        $nom = trim($valide['name']);

        // « Kg » et « kg » sont la même unité. La liste est courte : la
        // comparer en PHP tient compte des accents, que LOWER() de SQLite ignore.
        $existe = StockUnit::query()
            ->when($unite, fn ($q) => $q->whereKeyNot($unite->id))
            ->pluck('name')
            ->contains(fn (string $autre) => mb_strtolower($autre) === mb_strtolower($nom));

        if ($existe) {
            throw ValidationException::withMessages([
                'name' => "L'unité « {$nom} » existe déjà.",
            ]);
        }

        return $nom;
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
