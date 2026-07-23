<?php

namespace App\Http\Controllers;

use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Services\RoomCostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fiches techniques des chambres : marge sur une chambre louée par type.
 */
class RoomCostSheetController extends Controller
{
    public function __construct(private RoomCostingService $costing)
    {
    }

    /** Liste des types de chambre avec leur marge de contribution. */
    public function index(): View
    {
        $rows = RoomType::where('is_active', true)->orderBy('name')->get()
            ->map(fn (RoomType $type) => [
                'type'    => $type,
                'summary' => $this->costing->summaryFor($type),
            ]);

        return view('rooms.cost_sheets.index', compact('rows'));
    }

    /** Fiche détaillée d'un type de chambre. */
    public function show(RoomType $roomType): View
    {
        $sheet       = $this->costing->sheetFor($roomType);
        $stockItems  = StockItem::active()->orderBy('name')->get(['id', 'name', 'unit', 'average_cost']);

        return view('rooms.cost_sheets.show', [
            'sheet'       => $sheet,
            'roomType'    => $roomType,
            'stockItems'  => $stockItems,
            'categories'  => RoomCostItem::CATEGORIES,
            'bases'       => RoomCostItem::BASES,
        ]);
    }

    /** Enregistre les hypothèses de la fiche (occupants, séjour moyen, charge fixe). */
    public function updateAssumptions(Request $request, RoomType $roomType): RedirectResponse
    {
        $validated = $request->validate([
            'reference_occupants'  => ['nullable', 'integer', 'min:1', 'max:20'],
            'avg_length_of_stay'   => ['nullable', 'numeric', 'min:0.1', 'max:365'],
            'fixed_cost_per_night' => ['nullable', 'integer', 'min:0'],   // FCFA
            'notes'                => ['nullable', 'string', 'max:1000'],
        ]);

        RoomCostSheet::updateOrCreate(
            ['room_type_id' => $roomType->id],
            [
                'reference_occupants'  => $validated['reference_occupants'] ?? null,
                'avg_length_of_stay'   => $validated['avg_length_of_stay'] ?? null,
                'fixed_cost_per_night' => (int) ($validated['fixed_cost_per_night'] ?? 0) * 100,
                'notes'                => $validated['notes'] ?? null,
                'tenant_id'            => $this->tenantId(),
            ]
        );

        return back()->with('success', 'Hypothèses de la fiche mises à jour.');
    }

    public function storeItem(Request $request, RoomType $roomType): RedirectResponse
    {
        $validated = $this->validatedItem($request);

        RoomCostItem::create($this->itemAttributes($validated, $roomType));

        return back()->with('success', 'Poste de coût ajouté.');
    }

    public function updateItem(Request $request, RoomType $roomType, RoomCostItem $item): RedirectResponse
    {
        abort_unless($item->room_type_id === $roomType->id, 404);

        $validated = $this->validatedItem($request);

        $item->update($this->itemAttributes($validated, $roomType));

        return back()->with('success', 'Poste de coût mis à jour.');
    }

    public function destroyItem(RoomType $roomType, RoomCostItem $item): RedirectResponse
    {
        abort_unless($item->room_type_id === $roomType->id, 404);

        $item->delete();

        return back()->with('success', 'Poste supprimé.');
    }

    private function validatedItem(Request $request): array
    {
        return $request->validate([
            'category'      => ['required', Rule::in(array_keys(RoomCostItem::CATEGORIES))],
            'label'         => ['required', 'string', 'max:160'],
            'basis'         => ['required', Rule::in(array_keys(RoomCostItem::BASES))],
            'quantity'      => ['required', 'numeric', 'min:0'],
            // Prix unitaire en FCFA ; ignoré si un article d'économat est lié.
            'unit_cost'     => ['nullable', 'integer', 'min:0'],
            'stock_item_id' => ['nullable', 'exists:stock_items,id'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function itemAttributes(array $validated, RoomType $roomType): array
    {
        return [
            'room_type_id'  => $roomType->id,
            'category'      => $validated['category'],
            'label'         => trim($validated['label']),
            'basis'         => $validated['basis'],
            'quantity'      => $validated['quantity'],
            'unit_cost'     => (int) ($validated['unit_cost'] ?? 0) * 100,
            'stock_item_id' => $validated['stock_item_id'] ?? null,
            'notes'         => $validated['notes'] ?? null,
            'tenant_id'     => $this->tenantId(),
        ];
    }

    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
    }
}
