<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\DocumentExporter;
use App\Services\GoodsReceiptService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public const MAX_EXPORT = 1000;

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
