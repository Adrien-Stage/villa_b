<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderUpdated;
use App\Services\DocumentExporter;
use App\Services\Notifier;
use App\Services\PurchaseOrderService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    private const MAX_EXPORT = 2000;

    /** Direction et comptabilité suivent l'engagement puis la dette fournisseur. */
    private const WATCHERS = ['manager', 'accountant'];

    public function __construct(private Notifier $notifier)
    {
    }

    public function index(Request $request): View
    {
        $orders = $this->filtrer($request)->paginate(self::PAR_PAGE)->withQueryString();

        $statsQuery = PurchaseOrder::query();
        if ($supplierId = $request->query('fournisseur') ?: $request->query('supplier_id')) {
            $statsQuery->where('supplier_id', $supplierId);
        }
        if ($debut = $this->date($request->query('du'))) {
            $statsQuery->whereDate('created_at', '>=', $debut);
        }
        if ($fin = $this->date($request->query('au'))) {
            $statsQuery->whereDate('created_at', '<=', $fin);
        }

        $stats = [
            'total'        => (clone $statsQuery)->count(),
            'draft'        => (clone $statsQuery)->where('status', PurchaseOrder::STATUS_DRAFT)->count(),
            'sent'         => (clone $statsQuery)->where('status', PurchaseOrder::STATUS_SENT)->count(),
            'received'     => (clone $statsQuery)->whereIn('status', [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])->count(),
            'total_amount' => (int) (clone $statsQuery)->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)->sum('total_amount'),
        ];

        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('economat.orders.index', [
            'orders'    => $orders,
            'suppliers' => $suppliers,
            'stats'     => $stats,
            'filtres'   => $this->filtresAppliques($request),
        ]);
    }

    /**
     * Export et impression structurée de la liste des bons de commande selon une période/filtre.
     */
    public function export(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);

        abort_unless(DocumentExporter::formatValide($format), 404);

        $lignes = $this->filtrer($request)->limit(self::MAX_EXPORT)->get();

        AuditLog::record(Auth::id(), 'export', 'Export des bons de commande fournisseur (' . $format . ') — '
            . $lignes->count() . ' ligne(s)', 'economat', ['format' => $format, 'filtres' => $request->query()]);

        $document = Document::intitule('Bons de commande fournisseurs')
            ->sousTitre('Commandes de réapprovisionnement et engagements auprès des fournisseurs')
            ->filtres($this->filtresAppliques($request))
            ->colonnes([
                Colonne::texte('number', 'N° Bon'),
                Colonne::dateHeure('created_at', 'Date émission'),
                Colonne::texte('supplier.name', 'Fournisseur'),
                Colonne::texte('supplier.phone', 'Téléphone'),
                Colonne::nombre('lines_count', 'Articles'),
                Colonne::montant('total_amount', 'Montant TTC'),
                Colonne::texte('status_label', 'Statut'),
                Colonne::texte('issuer_signature', 'Signataire'),
            ])
            ->lignes($lignes)
            ->note($lignes->count() >= self::MAX_EXPORT
                ? 'Export limité aux ' . self::MAX_EXPORT . ' commandes les plus récentes. Affinez les filtres pour le reste.'
                : null);

        return $exporteur->rendre($document, $format);
    }

    /**
     * Requête filtrée partagée par la liste et par l'export.
     */
    private function filtrer(Request $request): Builder
    {
        $query = PurchaseOrder::with(['supplier', 'createdBy', 'lines.item.category'])
            ->withCount('lines')
            ->latest();

        if ($supplierId = $request->query('fournisseur') ?: $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($statut = $request->query('statut') ?: $request->query('status')) {
            if (array_key_exists($statut, PurchaseOrder::STATUSES)) {
                $query->where('status', $statut);
            }
        }

        if ($debut = $this->date($request->query('du'))) {
            $query->whereDate('created_at', '>=', $debut);
        }

        if ($fin = $this->date($request->query('au'))) {
            $query->whereDate('created_at', '<=', $fin);
        }

        if ($recherche = trim((string) $request->query('recherche'))) {
            $query->where(function ($q) use ($recherche) {
                $q->where('number', 'like', '%' . $recherche . '%')
                    ->orWhere('notes', 'like', '%' . $recherche . '%')
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%' . $recherche . '%')
                        ->orWhere('code', 'like', '%' . $recherche . '%'));
            });
        }

        return $query;
    }

    private function filtresAppliques(Request $request): array
    {
        $supplierName = null;
        if ($sId = $request->query('fournisseur') ?: $request->query('supplier_id')) {
            $supplierName = Supplier::find($sId)?->name;
        }

        return array_filter([
            'Fournisseur' => $supplierName,
            'Statut'      => PurchaseOrder::STATUSES[$request->query('statut') ?: $request->query('status')] ?? null,
            'Du'          => $this->date($request->query('du'))?->format('d/m/Y'),
            'Au'          => $this->date($request->query('au'))?->format('d/m/Y'),
            'Recherche'   => trim((string) $request->query('recherche')) ?: null,
        ]);
    }

    private function date(?string $valeur): ?Carbon
    {
        if (!$valeur) {
            return null;
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            return null;
        }
    }

    public function create(Request $request): View
    {
        $suppliers = Supplier::active()->orderBy('name')->withCount('stockItems')->get();
        $items     = StockItem::active()->with(['category', 'supplier'])->orderBy('name')->get();
        $selectedSupplierId = (int) ($request->query('supplier_id') ?: $request->query('fournisseur') ?: 0);

        return view('economat.orders.create', compact('suppliers', 'items', 'selectedSupplierId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'           => ['required', 'exists:suppliers,id'],
            'expected_at'           => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'exists:stock_items,id'],
            'lines.*.quantity'      => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price'    => ['required', 'numeric', 'min:0'],   // en FCFA
        ], [
            'lines.required'       => 'Sélectionnez au moins un article pour ce bon de commande.',
            'supplier_id.required' => 'Veuillez sélectionner un fournisseur.',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $user = Auth::user();
            $signature = $user ? $user->signatureName() : null;

            $order = PurchaseOrder::create([
                'supplier_id'      => $validated['supplier_id'],
                'expected_at'      => $validated['expected_at'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'created_by'       => Auth::id(),
                'issuer_signature' => $signature,
                'tenant_id'        => Auth::user()?->tenant_id
                    ?? \App\Models\Tenant::current()?->id,
            ]);

            foreach ($validated['lines'] as $line) {
                // Stocke en centimes FCFA
                $unitPriceCentimes = (int) round((float) $line['unit_price'] * 100);

                PurchaseOrderLine::create([
                    'purchase_order_id' => $order->id,
                    'stock_item_id'     => $line['stock_item_id'],
                    'quantity_ordered'  => $line['quantity'],
                    'unit_price'        => $unitPriceCentimes,
                ]);

                // Si l'article n'était rattaché à aucun fournisseur, on lui associe ce fournisseur
                $item = StockItem::find($line['stock_item_id']);
                if ($item && empty($item->supplier_id)) {
                    $item->update(['supplier_id' => $validated['supplier_id']]);
                }
            }

            $order->recalculateTotal();

            return $order;
        });

        return redirect()
            ->route('economat.orders.show', $order)
            ->with('success', "Bon de commande {$order->number} créé et signé numériquement.");
    }

    public function show(PurchaseOrder $order): View
    {
        $order->load(['supplier', 'lines.item.category', 'createdBy', 'receivedBy', 'purchaseRequest', 'receipts.receivedBy', 'invoices']);

        return view('economat.orders.show', compact('order'));
    }

    /**
     * Bon de commande officiel fournisseur imprimable.
     * Conforme aux normes d'audit et sans éléments d'interface web polluants.
     */
    public function print(PurchaseOrder $order): View
    {
        $order->load(['supplier', 'lines.item.category', 'createdBy', 'purchaseRequest']);

        return view('economat.orders.print', compact('order'));
    }

    /** Envoi du bon par email au fournisseur. */
    public function send(PurchaseOrder $order, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $sent = $service->send($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Le bon est marqué envoyé même si l'email échoue : l'engagement de
        // dépense existe, la direction doit le savoir dans les deux cas.
        $this->notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), auth()->id());

        return $sent
            ? back()->with('success', "Bon {$order->number} envoyé à {$order->supplier->email}.")
            : back()->with('error', "Le bon est marqué comme envoyé, mais l'email n'a pas pu partir. Vérifiez l'adresse et réessayez.");
    }

    /**
     * Le bon est parvenu au fournisseur sans email (main propre, téléphone,
     * WhatsApp) : il passe à « envoyé » et pourra être réceptionné.
     */
    public function transmit(Request $request, PurchaseOrder $order, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'moyen' => ['required', Rule::in(array_keys(PurchaseOrder::TRANSMISSIONS_MANUELLES))],
        ], [
            'moyen.required' => 'Indiquez comment le bon est parvenu au fournisseur.',
        ]);

        try {
            $service->markTransmitted($order, $validated['moyen']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), auth()->id());

        return back()->with('success', "Bon {$order->number} transmis au fournisseur ("
            . mb_strtolower(PurchaseOrder::TRANSMISSIONS_MANUELLES[$validated['moyen']])
            . ') : sa livraison peut maintenant être réceptionnée.');
    }

    /** Réception (totale ou partielle) : entrée en stock des quantités livrées. */
    public function receive(Request $request, PurchaseOrder $order, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'received'   => ['required', 'array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->receive($order, array_map('floatval', $validated['received']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // La marchandise est entrée en stock : la facture fournisseur suit.
        $this->notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), auth()->id());

        return back()->with('success', "Réception enregistrée pour le bon {$order->number}.");
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        if (!$order->canBeCancelled()) {
            return back()->with('error', 'Ce bon ne peut plus être annulé.');
        }

        $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        return back()->with('success', "Bon {$order->number} annulé.");
    }
}
