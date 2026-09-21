<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Services\DocumentExporter;
use App\Services\RoomCostingService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Vue consolidée en document : une ligne par type, la marge de chacun.
     *
     * C'est ce que le comptable classe et transmet ; la fiche détaillée ne
     * s'ouvre qu'au besoin, pour comprendre d'où vient un coût.
     */
    public function document(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);

        abort_unless(DocumentExporter::formatValide($format), 404);

        $lignes = RoomType::where('is_active', true)->orderBy('name')->get()
            ->map(function (RoomType $type) {
                $resume = $this->costing->summaryFor($type);

                return [
                    'type'    => $type->name,
                    // Une fiche non renseignée n'affiche pas de zéro : un coût
                    // nul et un coût inconnu ne se lisent pas pareil.
                    'prix'    => $resume['reference_price'],
                    'cout'    => $resume['is_configured'] ? $resume['variable_cost'] : null,
                    'marge'   => $resume['is_configured'] ? $resume['contribution_margin'] : null,
                    'pct'     => $resume['is_configured'] ? $resume['contribution_pct'] . ' %' : 'à remplir',
                    'postes'  => $resume['line_count'],
                ];
            })
            // Le moins rentable d'abord : c'est ce qu'on cherche en ouvrant le
            // document.
            ->sortBy(fn (array $l) => $l['marge'] ?? PHP_INT_MIN)
            ->values();

        $renseignees = $lignes->filter(fn (array $l) => $l['marge'] !== null);

        AuditLog::record(Auth::id(), 'export', 'Export des marges par type de chambre (' . $format . ')',
            'comptabilite', ['format' => $format]);

        $document = Document::intitule('Marges par type de chambre')
            ->sousTitre('Marge de contribution : prix pratiqué moins coût variable par nuitée')
            ->colonnes([
                Colonne::texte('type', 'Type de chambre'),
                Colonne::montant('prix', 'Prix / nuit'),
                Colonne::montant('cout', 'Coût variable'),
                Colonne::montant('marge', 'Marge'),
                Colonne::texte('pct', '%'),
                Colonne::nombre('postes', 'Postes'),
            ])
            ->lignes($lignes)
            ->totaux([
                'prix'  => $renseignees->sum('prix'),
                'cout'  => $renseignees->sum('cout'),
                'marge' => $renseignees->sum('marge'),
            ])
            ->note($renseignees->count() < $lignes->count()
                ? ($lignes->count() - $renseignees->count()) . ' fiche(s) restent à remplir : les totaux '
                  . 'ne portent que sur les ' . $renseignees->count() . ' renseignée(s).'
                : 'La marge de contribution ne couvre pas les charges fixes de l\'établissement.');

        return $exporteur->rendre($document, $format);
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

    /**
     * Postes courants d'une chambre d'hôtel, avec des valeurs de départ
     * plausibles. Point de départ à ajuster, pour éviter la page blanche où
     * l'utilisateur doit tout inventer.
     *
     * Montants en FCFA (convertis en centimes à la création).
     */
    private const STARTER_ITEMS = [
        ['category' => 'energy',       'label' => 'Électricité',            'basis' => 'per_night',       'quantity' => 8,    'unit_cost' => 75],
        ['category' => 'water',        'label' => 'Eau',                    'basis' => 'per_guest_night', 'quantity' => 0.15, 'unit_cost' => 500],
        ['category' => 'consumable',   'label' => "Kit d'accueil",          'basis' => 'per_guest_night', 'quantity' => 1,    'unit_cost' => 500],
        ['category' => 'linen',        'label' => 'Blanchisserie du linge', 'basis' => 'per_stay',        'quantity' => 1,    'unit_cost' => 3000],
        ['category' => 'housekeeping', 'label' => 'Ménage (main-d\'œuvre)', 'basis' => 'per_night',       'quantity' => 1,    'unit_cost' => 2000],
    ];

    /**
     * Crée les postes courants d'un coup. N'ajoute que ceux qui manquent :
     * relancer l'action ne crée pas de doublon.
     */
    public function applyStarter(RoomType $roomType): RedirectResponse
    {
        $existing = RoomCostItem::where('room_type_id', $roomType->id)
            ->pluck('label')
            ->map(fn ($l) => mb_strtolower(trim($l)))
            ->flip();

        $created = 0;
        foreach (self::STARTER_ITEMS as $index => $item) {
            if (isset($existing[mb_strtolower($item['label'])])) {
                continue;
            }

            RoomCostItem::create([
                'room_type_id' => $roomType->id,
                'category'     => $item['category'],
                'label'        => $item['label'],
                'basis'        => $item['basis'],
                'quantity'     => $item['quantity'],
                'unit_cost'    => $item['unit_cost'] * 100,
                'sort_order'   => $index,
                'tenant_id'    => $this->tenantId(),
            ]);
            $created++;
        }

        return back()->with('success', $created > 0
            ? "{$created} poste(s) ajouté(s) — ajustez maintenant les quantités et les prix à votre établissement."
            : 'Ces postes existent déjà dans la fiche.');
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
            ?? \App\Models\Tenant::current()?->id;
    }
}
