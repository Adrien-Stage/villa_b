<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderUpdated;
use App\Services\DocumentExporter;
use App\Services\GoodsReceiptService;
use App\Services\Notifier;
use App\Services\PermissionResolver;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public const MAX_EXPORT = 1000;

    /** Prévenus d'une réception directe : la marchandise est là, la facture suivra. */
    private const WATCHERS = ['manager', 'accountant'];

    public function __construct(private GoodsReceiptService $receiptService)
    {
    }

    public function index(Request $request): View
    {
        $receipts = $this->filtrer($request)
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        $statsQuery = GoodsReceipt::query();
        if ($debut = $this->date($request->query('du') ?: $request->query('date_from'))) {
            $statsQuery->whereDate('received_at', '>=', $debut);
        }
        if ($fin = $this->date($request->query('au') ?: $request->query('date_to'))) {
            $statsQuery->whereDate('received_at', '<=', $fin);
        }

        $stats = [
            'total_receipts' => (clone $statsQuery)->count(),
            'conforme'       => (clone $statsQuery)->whereDoesntHave('lines', fn ($l) => $l->where('quantity_rejected', '>', 0))->count(),
            'avec_litige'    => (clone $statsQuery)->whereHas('lines', fn ($l) => $l->where('quantity_rejected', '>', 0))->count(),
            'total_amount'   => (int) (clone $statsQuery)->where('status', GoodsReceipt::STATUS_RECEIVED)->sum('total_amount'),
        ];

        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('economat.receipts.index', [
            'receipts'  => $receipts,
            'suppliers' => $suppliers,
            'stats'     => $stats,
            'filtres'   => $this->filtresAppliques($request),
        ]);
    }

    /**
     * Export et impression structurée de la liste des bons d'entrée en stock.
     */
    public function export(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);

        abort_unless(DocumentExporter::formatValide($format), 404);

        $lignes = $this->filtrer($request)->limit(self::MAX_EXPORT)->get();

        AuditLog::record(Auth::id(), 'export', 'Export des bons d\'entrée en stock / réceptions (' . $format . ') — '
            . $lignes->count() . ' ligne(s)', 'economat', ['format' => $format, 'filtres' => $request->query()]);

        $document = Document::intitule('Bons d\'entrée en stock & Réceptions')
            ->sousTitre('Pointage contradictoire des livraisons fournisseurs et entrées en stock magasin')
            ->filtres($this->filtresAppliques($request))
            ->colonnes([
                Colonne::texte('number', 'N° Bon d\'entrée'),
                Colonne::dateHeure('received_at', 'Date réception'),
                Colonne::texte('purchaseOrder.number', 'N° Bon commande'),
                Colonne::texte('supplier.name', 'Fournisseur'),
                Colonne::texte('delivery_note_number', 'N° BL Livreur'),
                Colonne::nombre('lines_count', 'Articles'),
                Colonne::montant('total_amount', 'Valeur admise'),
                Colonne::texte('receiver_signature', 'Économe signataire'),
                Colonne::texte('status_label', 'Statut'),
            ])
            ->lignes($lignes);

        if ($lignes->count() >= self::MAX_EXPORT) {
            $document->note('Export limité aux ' . self::MAX_EXPORT . ' réceptions les plus récentes. Affinez les filtres pour le reste.');
        }

        return $exporteur->rendre($document, $format);
    }

    public function create(PurchaseOrder $order): View|RedirectResponse
    {
        if (!$order->canBeReceived()) {
            return redirect()
                ->route('economat.orders.show', $order)
                ->with('error', "Ce bon de commande ne peut pas être réceptionné dans son état actuel.");
        }

        $order->load(['supplier', 'lines.item']);

        return view('economat.receipts.create', [
            'order'   => $order,
            'reasons' => GoodsReceiptLine::REASONS,
        ]);
    }

    public function store(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_note_number'       => ['nullable', 'string', 'max:80'],
            'received_at'                => ['nullable', 'date'],
            'notes'                      => ['nullable', 'string', 'max:1000'],
            'lines'                      => ['required', 'array'],
            'lines.*.quantity_delivered' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_accepted'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_rejected'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejection_reason'   => ['nullable', 'string', 'max:100'],
            'lines.*.notes'              => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $receipt = $this->receiptService->receive($order, $validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.receipts.show', $receipt)
            ->with('success', "Bon d'entrée en stock {$receipt->number} validé et signé. Les marchandises acceptées sont intégrées au stock.");
    }

    /**
     * Réception directe : la marchandise est arrivée sans bon de commande.
     * Le fournisseur et les articles absents du magasin se créent au passage.
     */
    public function createDirect(PermissionResolver $resolver): View
    {
        $user = Auth::user();

        return view('economat.receipts.direct', [
            'suppliers'     => Supplier::active()->orderBy('name')->get(['id', 'name', 'phone']),
            'items'         => StockItem::active()->orderBy('name')->get(['id', 'name', 'unit', 'average_cost', 'last_purchase_price']),
            'categories'    => StockCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'unites'        => StockUnit::choix(),
            'motifs'        => PurchaseOrder::MOTIFS_REGULARISATION,
            'reasons'       => GoodsReceiptLine::REASONS,
            'peutCreerFournisseur' => $resolver->allows($user, 'economat.suppliers.creer'),
            'peutCreerArticle'     => $resolver->allows($user, 'economat.items.creer'),
        ]);
    }

    public function storeDirect(Request $request, PermissionResolver $resolver, Notifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'               => ['nullable', 'integer', 'exists:suppliers,id', 'required_without:nouveau_fournisseur.name'],
            'nouveau_fournisseur.name'  => ['nullable', 'string', 'max:160', 'required_without:supplier_id'],
            'nouveau_fournisseur.phone' => ['nullable', 'string', 'max:30'],
            'motif'                     => ['required', Rule::in(array_keys(PurchaseOrder::MOTIFS_REGULARISATION))],
            'delivery_note_number'      => ['nullable', 'string', 'max:80'],
            'received_at'               => ['nullable', 'date', 'before_or_equal:now'],
            'notes'                     => ['nullable', 'string', 'max:1000'],
            'lines'                     => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.stock_item_id'     => ['nullable', 'integer', 'exists:stock_items,id', 'required_without:lines.*.nouvel_article.name'],
            'lines.*.nouvel_article.name'              => ['nullable', 'string', 'max:160', 'required_without:lines.*.stock_item_id'],
            'lines.*.nouvel_article.unit'              => ['nullable', 'required_with:lines.*.nouvel_article.name', Rule::in(StockUnit::choix())],
            'lines.*.nouvel_article.stock_category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            'lines.*.quantity_delivered' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.quantity_rejected'  => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.rejection_reason'   => ['nullable', Rule::in(array_keys(GoodsReceiptLine::REASONS))],
            // Prix unitaire en FCFA : sans lui, le coût moyen du stock serait faussé.
            'lines.*.unit_price'         => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.notes'              => ['nullable', 'string', 'max:255'],
        ], [
            'supplier_id.required_without'              => 'Choisissez le fournisseur, ou donnez le nom du nouveau.',
            'nouveau_fournisseur.name.required_without' => 'Choisissez le fournisseur, ou donnez le nom du nouveau.',
            'motif.required'                            => 'Indiquez pourquoi la marchandise arrive sans bon de commande.',
            'lines.required'                            => 'Ajoutez au moins un article reçu.',
            'lines.*.stock_item_id.required_without'    => 'Chaque ligne nomme un article : choisissez-le, ou donnez le nom du nouveau.',
            'lines.*.nouvel_article.name.required_without' => 'Chaque ligne nomme un article : choisissez-le, ou donnez le nom du nouveau.',
            'lines.*.nouvel_article.unit.required_with'    => "Choisissez l'unité du nouvel article.",
            'lines.*.nouvel_article.unit.in'               => "Cette unité n'est pas dans la liste : ajoutez-la dans Paramètres › Économat.",
            'lines.*.quantity_delivered.gt'             => 'La quantité livrée doit être positive.',
            'lines.*.unit_price.required'               => 'Le prix unitaire est obligatoire : il valorise le stock.',
            'lines.*.unit_price.gt'                     => 'Le prix unitaire est obligatoire : il valorise le stock.',
            'received_at.before_or_equal'               => 'Une réception ne se date pas dans le futur.',
        ]);

        $user = Auth::user();

        // Créer un fournisseur ou un article reste un droit à part : la
        // réception directe ne le contourne pas.
        if (empty($validated['supplier_id']) && !$resolver->allows($user, 'economat.suppliers.creer')) {
            return back()->withInput()->withErrors(['nouveau_fournisseur.name' => "Vous ne pouvez pas créer de fournisseur : choisissez-en un dans la liste."]);
        }
        if (collect($validated['lines'])->contains(fn ($l) => empty($l['stock_item_id'])) && !$resolver->allows($user, 'economat.items.creer')) {
            return back()->withInput()->withErrors(['lines' => "Vous ne pouvez pas créer d'article : choisissez chaque article dans la liste."]);
        }

        try {
            $receipt = $this->receiptService->receiveDirect($validated, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $order = $receipt->purchaseOrder;
        $montant = number_format($receipt->total_amount / 100, 0, ',', ' ') . ' FCFA';

        AuditLog::record($user->id, 'reception_directe', "Réception directe {$receipt->number} sans bon de commande — "
            . "{$receipt->supplier?->name}, {$montant}, régularisée par le bon {$order->number}", 'economat', [
                'goods_receipt_id'  => $receipt->id,
                'purchase_order_id' => $order->id,
                'motif'             => $validated['motif'],
            ]);

        $notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), $user->id);

        return redirect()
            ->route('economat.receipts.show', $receipt)
            ->with('success', "Bon d'entrée {$receipt->number} validé : {$montant} entrés en stock. "
                . "Le bon de régularisation {$order->number} recevra la facture du fournisseur.");
    }

    public function show(GoodsReceipt $receipt): View
    {
        $receipt->load(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item.category']);

        return view('economat.receipts.show', [
            'receipt' => $receipt,
        ]);
    }

    /**
     * Bordereau officiel de réception contradictoire imprimable (BR).
     * Conforme aux normes d'audit et sans éléments d'interface web polluants.
     */
    public function print(GoodsReceipt $receipt): View
    {
        $receipt->load(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item.category']);

        return view('economat.receipts.print', [
            'receipt' => $receipt,
        ]);
    }

    public function cancel(GoodsReceipt $receipt): RedirectResponse
    {
        try {
            $this->receiptService->cancel($receipt, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.receipts.index')
            ->with('success', "Bon d'entrée {$receipt->number} annulé et sorties de stock régularisées.");
    }

    /**
     * Requête filtrée partagée par la liste et par l'export.
     */
    private function filtrer(Request $request): Builder
    {
        $query = GoodsReceipt::with(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item'])
            ->withCount('lines')
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        if ($supplierId = $request->query('fournisseur') ?: $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($debut = $this->date($request->query('du') ?: $request->query('date_from'))) {
            $query->whereDate('received_at', '>=', $debut);
        }

        if ($fin = $this->date($request->query('au') ?: $request->query('date_to'))) {
            $query->whereDate('received_at', '<=', $fin);
        }

        if ($litige = $request->query('litige')) {
            if ($litige === 'avec') {
                $query->whereHas('lines', fn ($l) => $l->where('quantity_rejected', '>', 0));
            } elseif ($litige === 'sans') {
                $query->whereDoesntHave('lines', fn ($l) => $l->where('quantity_rejected', '>', 0));
            }
        }

        if ($recherche = trim((string) $request->query('recherche'))) {
            $query->where(function ($q) use ($recherche) {
                $q->where('number', 'like', '%' . $recherche . '%')
                    ->orWhere('delivery_note_number', 'like', '%' . $recherche . '%')
                    ->orWhere('notes', 'like', '%' . $recherche . '%')
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('number', 'like', '%' . $recherche . '%'))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%' . $recherche . '%')
                        ->orWhere('code', 'like', '%' . $recherche . '%'));
            });
        }

        return $query;
    }

    private function filtresAppliques(Request $request): array
    {
        $filtres = [];

        if ($supplierId = $request->query('fournisseur') ?: $request->query('supplier_id')) {
            $supplier = Supplier::find($supplierId);
            $filtres['Fournisseur'] = $supplier ? $supplier->name : "#{$supplierId}";
        }

        if ($litige = $request->query('litige')) {
            $filtres['Conformité'] = $litige === 'avec' ? 'Avec litiges / avaries' : ($litige === 'sans' ? '100% Conforme' : 'Toutes');
        }

        $debut = $this->date($request->query('du') ?: $request->query('date_from'));
        $fin   = $this->date($request->query('au') ?: $request->query('date_to'));

        if ($debut && $fin) {
            $filtres['Période'] = 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
        } elseif ($debut) {
            $filtres['Depuis le'] = $debut->format('d/m/Y');
        } elseif ($fin) {
            $filtres['Jusqu\'au'] = $fin->format('d/m/Y');
        }

        if ($recherche = trim((string) $request->query('recherche'))) {
            $filtres['Recherche'] = $recherche;
        }

        return $filtres;
    }

    private function date(?string $valeur): ?Carbon
    {
        if (empty($valeur)) {
            return null;
        }

        try {
            return Carbon::parse($valeur)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
