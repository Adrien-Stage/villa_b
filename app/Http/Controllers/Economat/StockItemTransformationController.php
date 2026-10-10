<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockUnit;
use App\Services\Conditionnements;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * « Transformation » d'un article de l'économat : définir ses conditionnements
 * (paquet de 10 pièces, carton de 20 paquets), dire combien d'unités restent
 * fermées, et ouvrir des unités à la main.
 *
 * Le stock reste compté dans l'unité de l'article ; les sorties ouvrent
 * d'elles-mêmes paquets et cartons quand il le faut.
 */
class StockItemTransformationController extends Controller
{
    public function show(StockItem $item, Conditionnements $conditionnements): View
    {
        $item->load('packagings', 'category');

        // La découpe verse au garde-manger d'un restaurant : il en faut un.
        $restaurants = \App\Support\TenantModules::has('restaurant')
            ? \App\Models\PointOfSale::query()->restaurants()->active()->orderBy('name')->get()
                ->filter(fn ($r) => $r->offre(\App\Models\PointOfSale::SERVICE_STOCK))->values()
            : collect();
        $portions = \App\Models\RestaurantPantryItem::query()->active()
            ->whereIn('point_of_sale_id', $restaurants->pluck('id'))->orderBy('name')
            ->get(['id', 'name', 'unit', 'point_of_sale_id'])->groupBy('point_of_sale_id');

        return view('economat.items.transformation', [
            'restaurants' => $restaurants,
            'portions'    => $portions,
            'decoupes'    => \App\Models\StockCut::query()->where('stock_item_id', $item->id)->with('restaurant')
                ->latest('cut_at')->latest('id')->take(10)->get(),
            'item'       => $item,
            'etat'       => $conditionnements->etatDe($item),
            // Une unité mise hors service depuis reste proposée là où elle sert.
            'unites'     => collect(StockUnit::choix())->merge($item->packagings->pluck('name'))->unique()->values()->all(),
            'historique' => $item->movements()->with('user')
                ->where('source_type', StockMovement::SOURCE_PACKAGING)
                ->latest('occurred_at')->latest('id')->take(10)->get(),
        ]);
    }

    public function packagings(Request $request, StockItem $item, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'niveaux'              => ['nullable', 'array', 'max:6'],
            'niveaux.*.nom'        => ['nullable', 'string', 'max:40'],
            'niveaux.*.contenance' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'niveaux.*.fermes'     => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $niveaux = array_values(array_filter($data['niveaux'] ?? [], fn ($n) => trim((string) ($n['nom'] ?? '')) !== ''));

        try {
            $mouvement = $stock->definirConditionnements($item, $niveaux);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'conditionnement', "{$item->name} — {$mouvement->reason}", 'economat',
            ['stock_item_id' => $item->id]);

        return redirect()->route('economat.items.transformation.show', $item)
            ->with('success', $niveaux === [] ? 'Conditionnements retirés.' : 'Conditionnements enregistrés.');
    }

    /**
     * Découpe : une quantité de l'article sort de l'économat et se répartit en
     * portions au garde-manger d'un restaurant, valorisées au poids.
     */
    public function cut(Request $request, StockItem $item, \App\Services\StockCutService $decoupes): RedirectResponse
    {
        $data = $request->validate([
            'point_of_sale_id'       => ['required', 'integer'],
            'quantity'               => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'lines'                  => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.pantry_item_id' => ['nullable', 'integer'],
            'lines.*.nom'            => ['nullable', 'string', 'max:120'],
            'lines.*.quantity'       => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'point_of_sale_id.required' => 'Choisissez le restaurant qui reçoit les portions.',
            'quantity.gt'               => 'Indiquez la quantité prise.',
            'lines.required'            => 'Indiquez au moins une portion.',
        ]);

        try {
            $decoupe = $decoupes->decouper($item, $data, Auth::user());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'decoupe', "Découpe {$decoupe->number} — "
            . \App\Support\Conditionnement::libelle((float) $decoupe->quantity, $item->unit) . " de {$item->name} vers {$decoupe->restaurant->name}",
            'economat', ['stock_cut_id' => $decoupe->id]);

        return redirect()->route('economat.cuts.show', $decoupe)
            ->with('success', "Découpe {$decoupe->number} enregistrée : les portions sont au garde-manger de {$decoupe->restaurant->name}.");
    }

    public function open(Request $request, StockItem $item, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'niveau' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'integer', 'min:1', 'max:100000'],
            'motif'  => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.min' => 'Indiquez combien d’unités ouvrir.',
        ]);

        try {
            $mouvement = $stock->ouvrirConditionnement($item, $data['niveau'], (int) $data['nombre'], $data['motif'] ?? null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('economat.items.transformation.show', $item)
            ->with('success', ucfirst($mouvement->ouvertures() ?? 'Ouverture enregistrée') . '. Stock : ' . $item->fresh()->stockDecompose() . '.');
    }
}
