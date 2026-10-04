<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\RestaurantPantryCategory;
use App\Models\StockCategory;
use App\Services\CountSheetService;
use App\Support\InventorySchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Fiches de comptage à imprimer pour chaque service le jour de l'inventaire. */
class CountSheetController extends Controller
{
    public function __construct(private CountSheetService $sheets)
    {
    }

    public function index(): View
    {
        $calendrier = InventorySchedule::current();

        return view('economat.count_sheets.index', [
            'services'          => $this->sheets->services(),
            'stockCategories'   => StockCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'pantryCategories'  => RestaurantPantryCategory::orderBy('name')->get(['id', 'name']),
            'prochainInventaire' => $calendrier->next(now()),
        ]);
    }

    public function print(Request $request): View
    {
        $validated = $request->validate([
            'service'   => ['required', 'string', 'in:' . implode(',', [...array_keys($this->sheets->services()), CountSheetService::ALL])],
            'categorie' => ['nullable', 'integer'],
            // Comptage à l'aveugle par défaut : le compteur qui voit le
            // théorique a tendance à le recopier plutôt qu'à compter.
            'theorique' => ['nullable', 'boolean'],
        ]);

        return view('economat.count_sheets.print', [
            'sheets'         => $this->sheets->sheets($validated['service'], $validated['categorie'] ?? null),
            'showTheoretical' => (bool) ($validated['theorique'] ?? false),
            'tenant'         => \App\Models\Tenant::first(),
            // Qui a sorti la fiche : une fiche qui circule doit dire d'où elle vient.
            'printedBy'      => $request->user()?->name,
        ]);
    }
}
