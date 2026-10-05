<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Notifications\StockRequisitionSubmitted;
use App\Notifications\StockRequisitionUpdated;
use App\Services\DocumentExporter;
use App\Services\Notifier;
use App\Services\StockRequisitionService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Demandes des départements à l'économat.
 *
 * L'économe (et le manager) voient toutes les demandes et les traitent ; un
 * responsable de département ne voit et ne crée que les siennes.
 */
class StockRequisitionController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    /**
     * Plafond d'export. Au-delà, la production du document devient un
     * traitement long : on borne, et le document le dit en pied de page
     * plutôt que de rendre une liste tronquée en silence.
     */
    private const MAX_EXPORT = 2000;

    public function __construct(private Notifier $notifier)
    {
    }

    public function index(Request $request): View
    {
        $requisitions = $this->filtrer($request)->paginate(self::PAR_PAGE)->withQueryString();

        $statsQuery = StockRequisition::query();
        if (!$this->isStoreKeeper()) {
            $statsQuery->where('requested_by', Auth::id());
        }
        if (($service = $request->query('service')) && array_key_exists($service, StockRequisition::DEPARTMENTS)) {
            $statsQuery->where('department', $service);
        }
        if ($debut = $this->date($request->query('du'))) {
            $statsQuery->whereDate('created_at', '>=', $debut);
        }
        if ($fin = $this->date($request->query('au'))) {
            $statsQuery->whereDate('created_at', '<=', $fin);
        }

        $stats = [
            'total'     => (clone $statsQuery)->count(),
            'pending'   => (clone $statsQuery)->where('status', StockRequisition::STATUS_PENDING)->count(),
            'approved'  => (clone $statsQuery)->where('status', StockRequisition::STATUS_APPROVED)->count(),
            'delivered' => (clone $statsQuery)->where('status', StockRequisition::STATUS_DELIVERED)->count(),
        ];

        return view('economat.requisitions.index', [
            'requisitions' => $requisitions,
            'isKeeper'     => $this->isStoreKeeper(),
            'filtres'      => $this->filtresAppliques($request),
            'stats'        => $stats,
        ]);
    }

    /**
     * Sort la liste en document imprimable ou exportable.
     *
     * Les mêmes filtres que l'écran, appliqués à la même requête : un export
     * qui ne rendrait pas ce que l'écran affiche serait pire qu'absent.
     * Pas de pagination ici — on exporte la sélection entière, pas la page.
     */
    public function export(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);

        abort_unless(DocumentExporter::formatValide($format), 404);

        $lignes = $this->filtrer($request)->limit(self::MAX_EXPORT)->get();

        AuditLog::record(Auth::id(), 'export', 'Export des demandes à l\'économat (' . $format . ') — '
            . $lignes->count() . ' ligne(s)', 'economat', ['format' => $format, 'filtres' => $request->query()]);

        $document = Document::intitule('Demandes à l\'économat')
            ->sousTitre($this->isStoreKeeper()
                ? 'Sollicitations des services auprès du magasin central'
                : 'Mes sollicitations auprès du magasin central')
            ->filtres($this->filtresAppliques($request))
            ->colonnes([
                Colonne::texte('number', 'N°'),
                Colonne::dateHeure('created_at', 'Demandée le'),
                Colonne::texte('department_label', 'Service'),
                Colonne::texte('requestedBy.name', 'Demandeur'),
                Colonne::nombre('lines_count', 'Articles'),
                Colonne::texte('status_label', 'Statut'),
                Colonne::texte('purpose', 'Motif'),
            ])
            ->lignes($lignes)
            ->note($lignes->count() >= self::MAX_EXPORT
                ? 'Export limité aux ' . self::MAX_EXPORT . ' demandes les plus récentes. Affinez les filtres pour le reste.'
                : null);

        return $exporteur->rendre($document, $format);
    }

    /**
     * Requête filtrée, partagée par l'écran et par l'export.
     *
     * Un seul endroit : deux requêtes distinctes finiraient par diverger, et
     * l'écart ne se verrait qu'au moment où quelqu'un compare le papier à
     * l'écran.
     */
    private function filtrer(Request $request): Builder
    {
        $query = StockRequisition::with('requestedBy', 'lines')->withCount('lines')->latest();

        // Un département ne voit que ses propres demandes.
        if (!$this->isStoreKeeper()) {
            $query->where('requested_by', Auth::id());
        }

        if (($statut = $request->query('statut')) && array_key_exists($statut, StockRequisition::STATUSES)) {
            $query->where('status', $statut);
        }

        if (($service = $request->query('service')) && array_key_exists($service, StockRequisition::DEPARTMENTS)) {
            $query->where('department', $service);
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
                    ->orWhere('purpose', 'like', '%' . $recherche . '%');
            });
        }

        return $query;
    }

    /** Filtres retenus, tels qu'ils s'impriment en en-tête du document. */
    private function filtresAppliques(Request $request): array
    {
        return array_filter([
            'Statut'     => StockRequisition::STATUSES[$request->query('statut')] ?? null,
            'Service'    => StockRequisition::DEPARTMENTS[$request->query('service')] ?? null,
            'Du'         => $this->date($request->query('du'))?->format('d/m/Y'),
            'Au'         => $this->date($request->query('au'))?->format('d/m/Y'),
            'Recherche'  => trim((string) $request->query('recherche')) ?: null,
        ]);
    }

    /** Une date invalide est ignorée plutôt que de faire échouer l'écran. */
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

    public function create(): View
    {
        $items = StockItem::active()->with('category')->orderBy('name')->get();

        // Départements que l'utilisateur est habilité à représenter : ceux
        // dont il exerce la fonction, directement ou comme chef.
        $departments = collect(StockRequisition::DEPARTMENT_ROLES)
            ->filter(fn ($roles) => Auth::user()->exerce($roles))
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => StockRequisition::DEPARTMENTS[$key]])
            ->all();

        // Un économe/manager sans département précis peut demander pour « autre ».
        if (empty($departments)) {
            $departments = ['autre' => StockRequisition::DEPARTMENTS['autre']];
        }

        // Dépôts que ces services alimentent : la livraison y entrera en stock.
        $stores = \App\Models\ServiceStore::active()
            ->whereIn('department', array_keys($departments))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'department']);

        // Restaurants dont la personne peut alimenter le garde-manger : ceux
        // qui tiennent un stock.
        $contexte = app(\App\Services\RestaurantContext::class);
        $restaurants = $contexte->offrant(Auth::user(), \App\Models\PointOfSale::SERVICE_STOCK);
        $restaurantParDefaut = $restaurants->firstWhere('id', $contexte->pourCreation(Auth::user())?->id) ?? $restaurants->first();

        return view('economat.requisitions.create', compact('items', 'departments', 'stores', 'restaurants', 'restaurantParDefaut'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department'  => ['required', 'in:' . implode(',', array_keys(StockRequisition::DEPARTMENTS))],
            // Le dépôt doit appartenir au service qui demande : le bar ne
            // reçoit pas ce que l'hébergement a demandé.
            'service_store_id' => ['nullable', \Illuminate\Validation\Rule::exists('service_stores', 'id')
                ->where('is_active', true)
                ->where('department', $request->input('department'))],
            'point_of_sale_id' => ['nullable', 'integer'],
            'purpose'     => ['nullable', 'string', 'max:500'],
            'lines'       => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'exists:stock_items,id'],
            'lines.*.quantity'      => ['required', 'numeric', 'min:0.001'],
        ], [
            'lines.required'          => 'Ajoutez au moins un article à votre demande.',
            'service_store_id.exists' => "Ce dépôt n'appartient pas au service émetteur.",
        ]);

        // Une livraison au restaurant désigne la cuisine qui la reçoit : un
        // restaurant que la personne peut voir.
        $restaurant = null;
        if ($validated['department'] === 'restaurant' && empty($validated['service_store_id'])) {
            $contexte = app(\App\Services\RestaurantContext::class);
            $restaurant = isset($validated['point_of_sale_id'])
                ? $contexte->accessibles(Auth::user())->firstWhere('id', (int) $validated['point_of_sale_id'])
                : $contexte->pourCreation(Auth::user());

            if (! $restaurant) {
                return back()->withInput()->withErrors(['point_of_sale_id' => "Ce restaurant n'existe pas ou ne vous est pas accessible."]);
            }

            // La livraison entrera dans son garde-manger : il doit en tenir un.
            $restaurant->exiger(\App\Models\PointOfSale::SERVICE_STOCK);
        }

        $requisition = DB::transaction(function () use ($validated, $restaurant) {
            $user = Auth::user();
            $signature = $user ? $user->signatureName() : null;

            $requisition = StockRequisition::create([
                'department'          => $validated['department'],
                'service_store_id'    => $validated['service_store_id'] ?? null,
                'point_of_sale_id'    => $restaurant?->id,
                'purpose'             => $validated['purpose'] ?? null,
                'requested_by'        => Auth::id(),
                'requester_signature' => $signature,
                'tenant_id'           => Auth::user()->tenant_id
                    ?? \App\Models\Tenant::current()?->id,
            ]);

            foreach ($validated['lines'] as $line) {
                StockRequisitionLine::create([
                    'stock_requisition_id' => $requisition->id,
                    'stock_item_id'        => $line['stock_item_id'],
                    'quantity_requested'   => $line['quantity'],
                ]);
            }

            return $requisition;
        });

        // L'economat doit savoir qu'une demande attend son arbitrage.
        $this->notifier->toRoles(['econome', 'manager'], new StockRequisitionSubmitted($requisition), Auth::id());

        return redirect()
            ->route('economat.requisitions.show', $requisition)
            ->with('success', "Demande {$requisition->number} transmise à l'économat.");
    }

    public function show(StockRequisition $requisition): View
    {
        $this->authorizeView($requisition);

        $requisition->load('lines.item.category', 'requestedBy', 'reviewedBy', 'serviceStore');

        return view('economat.requisitions.show', [
            'requisition' => $requisition,
            'isKeeper'    => $this->isStoreKeeper(),
        ]);
    }

    /**
     * Impression officielle du Bon de Réquisition / Bon de sortie magasin.
     */
    public function print(StockRequisition $requisition): View
    {
        $this->authorizeView($requisition);

        $requisition->load('lines.item.category', 'requestedBy', 'reviewedBy', 'serviceStore');

        return view('economat.requisitions.print', [
            'requisition' => $requisition,
            'tenant'      => $requisition->tenant_id ? \App\Models\Tenant::find($requisition->tenant_id) : \App\Models\Tenant::first(),
        ]);
    }

    public function approve(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper('economat.requisitions.approve');

        try {
            $service->approve($requisition, $request->input('review_notes'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->send($requisition->fresh()->requestedBy, new StockRequisitionUpdated($requisition->fresh()));

        return back()->with('success', "Demande {$requisition->number} validée. Vous pouvez procéder à la livraison.");
    }

    public function reject(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper('economat.requisitions.reject');

        try {
            $service->reject($requisition, $request->input('review_notes'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->send($requisition->fresh()->requestedBy, new StockRequisitionUpdated($requisition->fresh()));

        return back()->with('success', "Demande {$requisition->number} refusée.");
    }

    /** Livraison : déstocke les quantités réellement servies. */
    public function deliver(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper('economat.requisitions.deliver');

        $validated = $request->validate([
            'issued'   => ['nullable', 'array'],
            'issued.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->deliver($requisition, array_map('floatval', $validated['issued'] ?? []));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->send($requisition->fresh()->requestedBy, new StockRequisitionUpdated($requisition->fresh()));

        return back()->with('success', "Articles livrés au département — demande {$requisition->number} clôturée.");
    }

    public function cancel(StockRequisition $requisition): RedirectResponse
    {
        // Le demandeur peut annuler sa demande ; l'économe aussi.
        if (!$this->isStoreKeeper() && $requisition->requested_by !== Auth::id()) {
            abort(403);
        }
        if (!$requisition->canBeCancelled()) {
            return back()->with('error', 'Cette demande ne peut plus être annulée.');
        }

        $requisition->update(['status' => StockRequisition::STATUS_CANCELLED]);

        return back()->with('success', "Demande {$requisition->number} annulée.");
    }

    // ── Habilitations ────────────────────────────────────────────────────────

    /**
     * Vue globale sur les demandes : le magasin (chef économe et magasinier),
     * la direction, et le contrôle — contrôleur de gestion, auditeur
     * qualité, administrateur. Les autres ne voient que leurs demandes.
     */
    private function isStoreKeeper(): bool
    {
        $user = Auth::user();

        return $user->isAdmin()
            || $user->exerce(['storekeeper', 'manager', 'controller', 'quality_auditor']);
    }

    /** Traiter une demande pose le droit de son geste : approuver, refuser, servir. */
    private function authorizeKeeper(string $droit): void
    {
        if (!app(\App\Services\PermissionResolver::class)->allows(Auth::user(), $droit)) {
            abort(403, "Seul l'économat peut traiter cette demande.");
        }
    }

    private function authorizeView(StockRequisition $requisition): void
    {
        if (!$this->isStoreKeeper() && $requisition->requested_by !== Auth::id()) {
            abort(403);
        }
    }
}
