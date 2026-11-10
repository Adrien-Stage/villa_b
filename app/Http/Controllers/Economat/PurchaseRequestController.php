<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\Supplier;
use App\Services\PermissionResolver;
use App\Services\PurchaseRequestService;
use App\Support\PrixFournisseurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PurchaseRequestController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function __construct(private PurchaseRequestService $requestService)
    {
    }

    /**
     * L'économe et le responsable des achats demandent pour l'économat : le
     * département demandeur ne se choisit pas, c'est l'économat central.
     */
    private const DEMANDENT_POUR_L_ECONOMAT = ['econome', 'purchasing_manager'];

    /**
     * Détermine si l'utilisateur connecté peut voir les montants et prix fournisseurs
     * sur les demandes d'achat.
     *
     * L'économe et les demandeurs des services expriment des besoins en quantités :
     * ils ne voient pas les tarifs négociés ni les valorisations d'achat.
     * Seul le service achat (responsable des achats), le contrôle de gestion,
     * la direction et les finances voient les prix et montants.
     * De plus, un demandeur sur sa propre demande ne voit pas les prix fournisseurs.
     */
    public static function peutVoirPrix(?PurchaseRequest $request = null): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // L'économe et le magasinier gèrent les stocks physiques : ils n'ont pas accès aux prix d'achat fournisseurs.
        if ($user->exerce(['econome', 'storekeeper'])) {
            return false;
        }

        // Sur sa propre demande, un demandeur qui n'est pas du service achat / direction ne voit pas les prix.
        if ($request && (int) $request->requested_by === (int) $user->id && ! $user->exerce(['purchasing_manager', 'manager'])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Le service achat (responsable des achats), la direction, le contrôle de gestion et la finance.
        return $user->exerce(['purchasing_manager', 'manager', 'controller', 'finance_manager'])
            || app(PermissionResolver::class)->allows($user, 'economat.purchase_requests.convert');
    }

    private static function choisitLeDepartement(): bool
    {
        return ! Auth::user()?->exerce(self::DEMANDENT_POUR_L_ECONOMAT);
    }

    /** Les demandes en cours de circuit, ou les archives : commandées, refusées, annulées. */
    public const VUES = ['en_cours' => 'En cours', 'archives' => 'Archives'];

    public function index(Request $request): View
    {
        $vue = array_key_exists($request->query('vue'), self::VUES) ? $request->query('vue') : 'en_cours';

        $query = PurchaseRequest::with(['supplier', 'requestedBy', 'reviewedBy'])->withCount('lines')
            ->when($vue === 'archives', fn ($q) => $q->archivees(), fn ($q) => $q->enCours());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $requests = $query->orderByDesc('id')->paginate(self::PAR_PAGE)->withQueryString();

        $stats = [
            'pending'   => PurchaseRequest::where('status', PurchaseRequest::STATUS_PENDING)->count(),
            'approved'  => PurchaseRequest::where('status', PurchaseRequest::STATUS_APPROVED)->count(),
            'converted' => PurchaseRequest::where('status', PurchaseRequest::STATUS_CONVERTED)->whereNull('archived_at')->count(),
            'archived'  => PurchaseRequest::query()->archivees()->count(),
        ];

        return view('economat.purchase_requests.index', [
            'requests'    => $requests,
            'vue'         => $vue,
            'vues'        => self::VUES,
            'stats'       => $stats,
            'departments' => PurchaseRequest::DEPARTMENTS,
            'statuses'    => PurchaseRequest::STATUSES,
            'canManage'   => app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.convert'),
            'peutVoirPrix' => self::peutVoirPrix(),
        ]);
    }

    public function create(Request $request): View
    {
        // Les fournisseurs de chaque article viennent de la carte, à la demande.
        $items = StockItem::query()
            ->active()
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::active()->orderBy('name')->get();

        $sourceRequisition = null;
        $prefillLines = [];
        $prefillPurpose = '';
        $prefillPriority = PurchaseRequest::PRIORITY_NORMAL;
        $prefillDepartment = 'cuisine';

        // Pré-remplissage depuis une demande à l'économat en rupture / stock insuffisant :
        // on ne reprend que les articles actifs manquants, pour la quantité à couvrir.
        if ($request->filled('requisition')) {
            $requisition = StockRequisition::with('lines.item')->find($request->integer('requisition'));

            if ($requisition) {
                $ruptureTotale = false;
                $prefillLines = $requisition->lines
                    ->filter(fn ($line) => $line->item && $line->item->is_active && ! $line->isServiceable())
                    ->groupBy('stock_item_id')
                    ->map(function ($group) use (&$ruptureTotale) {
                        $item = $group->first()->item;
                        $requested = (float) $group->sum('quantity_requested');
                        $stockActuel = max(0.0, (float) $item->current_stock);
                        if ($stockActuel <= 0) {
                            $ruptureTotale = true;
                        }

                        return [
                            'stock_item_id' => (string) $item->id,
                            'unit'          => $item->shortUnit(),
                            'quantity'      => max(0.001, round($requested - $stockActuel, 3)),
                            'notes'         => '',
                        ];
                    })
                    ->values()
                    ->all();

                if ($prefillLines !== []) {
                    $sourceRequisition = $requisition;
                    $prefillPriority = $ruptureTotale ? PurchaseRequest::PRIORITY_URGENT : PurchaseRequest::PRIORITY_NORMAL;
                    $prefillDepartment = match ($requisition->department) {
                        'restaurant'                => 'restaurant',
                        'hebergement', 'housekeeping' => 'housekeeping',
                        'technique'                 => 'maintenance',
                        default                     => 'economat',
                    };
                    $motifRequisition = $requisition->purpose ? " — {$requisition->purpose}" : '';
                    $prefillPurpose = Str::limit(
                        "Approvisionnement suite à la demande économat {$requisition->number} ({$requisition->departmentLabel()}){$motifRequisition}",
                        950,
                        '…'
                    );
                }
            }
        }

        return view('economat.purchase_requests.create', [
            'items'              => $items,
            'suppliers'          => $suppliers,
            'departments'        => PurchaseRequest::DEPARTMENTS,
            'priorities'         => PurchaseRequest::PRIORITIES,
            'choisitDepartement' => self::choisitLeDepartement(),
            'sourceRequisition'  => $sourceRequisition,
            'prefillLines'       => $prefillLines,
            'prefillPurpose'     => $prefillPurpose,
            'prefillPriority'    => $prefillPriority,
            'prefillDepartment'  => $prefillDepartment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! self::choisitLeDepartement()) {
            $request->merge(['department' => 'economat']);
        }

        // Le demandeur exprime un besoin : articles et quantités. Le fournisseur
        // se choisit aux achats, au moment de passer la demande en bons.
        $validated = $request->validate([
            'department'                    => ['required', 'string', 'max:50'],
            'priority'                      => ['required', 'in:low,normal,urgent'],
            'purpose'                       => ['nullable', 'string', 'max:1000'],
            'lines'                         => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id'         => ['required', 'exists:stock_items,id'],
            'lines.*.quantity_requested'    => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes'                 => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Veuillez ajouter au moins un article à la demande d\'achat.',
        ]);

        try {
            $purchaseRequest = $this->requestService->create($validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.purchase_requests.show', $purchaseRequest)
            ->with('success', $purchaseRequest->status === PurchaseRequest::STATUS_PENDING
                ? "Demande d'achat {$purchaseRequest->number} envoyée au responsable des achats, qui la vérifie."
                : "Demande d'achat {$purchaseRequest->number} créée.");
    }

    /**
     * Les fournisseurs d'un article, pour la carte de conversion d'une
     * demande en bons : le prix et la date de la dernière livraison de
     * chacun, son tarif, et ses prix livrés sur la période choisie.
     */
    public function fournisseurs(Request $request, StockItem $item): JsonResponse
    {
        $periode = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);

        $du = ! empty($periode['du']) ? Carbon::parse($periode['du']) : null;
        $au = ! empty($periode['au']) ? Carbon::parse($periode['au']) : ($du ? now() : null);

        return response()->json([
            'article' => ['id' => $item->id, 'nom' => $item->name, 'unite' => $item->shortUnit()],
            'periode' => $du ? ['du' => $du->toDateString(), 'au' => $au->toDateString()] : null,
            'fournisseurs' => PrixFournisseurs::comparer($item, $du, $au),
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        abort_unless($purchaseRequest->canBeEdited(), 403, "Cette demande d'achat ne peut plus être modifiée.");
        abort_unless(
            app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.modifier')
            || app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.convert'),
            403
        );

        $purchaseRequest->load(['lines.item.suppliers', 'supplier']);

        $items = StockItem::query()
            ->active()
            ->with('suppliers')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('economat.purchase_requests.edit', [
            'request'            => $purchaseRequest,
            'items'              => $items,
            'suppliers'          => $suppliers,
            'departments'        => PurchaseRequest::DEPARTMENTS,
            'priorities'         => PurchaseRequest::PRIORITIES,
            'choisitDepartement' => self::choisitLeDepartement(),
            'peutVoirPrix'       => self::peutVoirPrix($purchaseRequest),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->canBeEdited(), 403, "Cette demande d'achat ne peut plus être modifiée.");
        abort_unless(
            app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.modifier')
            || app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.convert'),
            403
        );

        $validated = $request->validate([
            // Sans champ (économe, responsable des achats), la demande garde son département.
            'department'                   => ['nullable', 'string', 'max:50'],
            'priority'                     => ['required', 'in:low,normal,urgent'],
            'purpose'                      => ['nullable', 'string', 'max:1000'],
            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.id'                   => ['nullable', 'integer'],
            'lines.*.stock_item_id'        => ['required', 'exists:stock_items,id'],
            'lines.*.quantity_requested'   => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes'                => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Veuillez conserver au moins un article dans la demande d\'achat.',
        ]);

        try {
            $this->requestService->update($purchaseRequest, $validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.purchase_requests.show', $purchaseRequest)
            ->with('success', "Demande d'achat {$purchaseRequest->number} mise à jour."
                . ((int) $purchaseRequest->requested_by !== (int) Auth::id() ? ' Le demandeur est averti des changements.' : ''));
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load(['supplier', 'requestedBy', 'reviewedBy', 'lines.item.suppliers', 'purchaseOrders.supplier']);

        $suppliers = Supplier::query()->active()->orderBy('name')->get();
        $peutConvertir = app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.convert');

        // Pour la conversion en bons : les fournisseurs de chaque article,
        // leur dernière livraison et leur tarif, déjà comparés.
        $comparaisons = $peutConvertir && $purchaseRequest->canBeConverted()
            ? $purchaseRequest->lines
                ->filter(fn ($l) => $l->quantiteRetenue() > 0 && $l->item)
                ->mapWithKeys(fn ($l) => [$l->id => PrixFournisseurs::comparer($l->item)])
                ->all()
            : [];

        return view('economat.purchase_requests.show', [
            'request'   => $purchaseRequest,
            'suppliers' => $suppliers,
            'comparaisons' => $comparaisons,
            // Les boutons posent la même question que la route et le service :
            // le droit de décider, et jamais sur sa propre demande.
            'canReview' => app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.approve')
                && !PurchaseRequestService::estSaPropreDemande($purchaseRequest, Auth::user()),
            'peutAnnuler' => app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.cancel'),
            'canManage' => $peutConvertir,
            'canEdit'   => (app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.modifier')
                || app(PermissionResolver::class)->allows(Auth::user(), 'economat.purchase_requests.convert'))
                && $purchaseRequest->canBeEdited(),
            'peutVoirPrix' => self::peutVoirPrix($purchaseRequest),
        ]);
    }

    /** Impression de la demande, pour le classement ou la signature. */
    public function print(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load(['requestedBy', 'reviewedBy', 'lines.item', 'purchaseOrders.supplier', 'purchaseOrders.createdBy']);

        return view('economat.purchase_requests.print', [
            'request'      => $purchaseRequest,
            'tenant'       => \App\Models\Tenant::first(),
            'peutVoirPrix' => self::peutVoirPrix($purchaseRequest),
        ]);
    }

    /** Approbation, avec les quantités que l'approbateur retient ligne par ligne. */
    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'notes'        => ['nullable', 'string', 'max:500'],
            'quantities'   => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'quantities.*.min' => 'Une quantité approuvée ne peut pas être négative.',
        ]);

        try {
            $approuvee = $this->requestService->approve($purchaseRequest, Auth::user(), $validated['notes'] ?? null, $validated['quantities'] ?? []);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', "Demande d'achat {$purchaseRequest->number} approuvée"
            . ($approuvee->aEteAjustee() ? ' avec des quantités ajustées — l\'économat en est averti' : '')
            . '. Elle passe au responsable des achats.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
        ]);

        try {
            $this->requestService->reject($purchaseRequest, Auth::user(), $validated['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Demande d'achat {$purchaseRequest->number} refusée.");
    }

    public function convert(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'line_suppliers'   => ['required', 'array'],
            'line_suppliers.*' => ['required', 'exists:suppliers,id'],
        ], [
            'line_suppliers.required' => 'Choisissez le fournisseur de chaque article.',
        ]);

        // Chaque article à commander a son fournisseur, choisi ici : aucun
        // n'est attribué d'office.
        $sansFournisseur = $purchaseRequest->lines
            ->filter(fn ($l) => $l->quantiteRetenue() > 0 && empty($validated['line_suppliers'][$l->id]))
            ->map(fn ($l) => $l->item?->name ?? 'Un article');
        if ($sansFournisseur->isNotEmpty()) {
            return back()->with('error', 'Choisissez le fournisseur de chaque article : ' . $sansFournisseur->implode(', ') . '.');
        }

        try {
            $orders = $this->requestService->convertToOrders(
                $purchaseRequest,
                Auth::user(),
                null,
                $validated['line_suppliers']
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($orders->count() === 1) {
            $firstOrder = $orders->first();
            return redirect()
                ->route('economat.orders.show', $firstOrder)
                ->with('success', "Bon de commande {$firstOrder->number} généré à partir de la demande {$purchaseRequest->number}.");
        }

        return redirect()
            ->route('economat.orders.index')
            ->with('success', "{$orders->count()} bons de commande ont été générés à partir de la demande {$purchaseRequest->number}.");
    }

    public function cancel(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        try {
            $this->requestService->cancel($purchaseRequest, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.purchase_requests.index')
            ->with('success', "Demande d'achat {$purchaseRequest->number} annulée.");
    }
}
