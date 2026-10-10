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

        return view('economat.items.transformation', [
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
