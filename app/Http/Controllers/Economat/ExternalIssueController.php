<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExternalIssue;
use App\Models\StockItem;
use App\Services\DocumentExporter;
use App\Services\ExternalIssueService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Sorties de matériel hors de l'établissement : prêt, réparation, don,
 * cession, transfert, restitution. L'économe les enregistre et les valide ;
 * l'économat, la direction et le contrôle les consultent, les filtrent par
 * période ou par date, et les impriment.
 */
class ExternalIssueController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public const MAX_EXPORT = 2000;

    public function __construct(private ExternalIssueService $service)
    {
    }

    public function index(Request $request): View
    {
        $requete = $this->filtrer($request);

        $sorties = (clone $requete)->with(['issuedBy', 'lines'])->withCount('lines')
            ->orderByDesc('issued_at')->orderByDesc('id')
            ->paginate(self::PAR_PAGE)->withQueryString();

        $validees = (clone $requete)->validees();

        return view('economat.external_issues.index', [
            'sorties' => $sorties,
            'filtres' => $this->filtresAppliques($request),
            'stats'   => [
                'bons'     => (clone $validees)->count(),
                'valeur'   => (int) (clone $validees)->sum('total_value'),
                'a_rendre' => (clone $validees)->whereNotNull('expected_return_at')->count(),
                'en_retard' => (clone $validees)->whereNotNull('expected_return_at')->whereDate('expected_return_at', '<', today())->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('economat.external_issues.create', [
            'articles' => StockItem::active()->with('packagings')->where('current_stock', '>', 0)->orderBy('name')
                ->get(['id', 'name', 'reference', 'unit', 'current_stock', 'average_cost']),
            'motifs'   => ExternalIssue::REASONS,
            'avecRetour' => ExternalIssue::REASONS_AVEC_RETOUR,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason'                   => ['required', Rule::in(array_keys(ExternalIssue::REASONS))],
            'issued_at'                => ['nullable', 'date', 'before_or_equal:now'],
            'beneficiary_name'         => ['required', 'string', 'max:160'],
            'beneficiary_organisation' => ['nullable', 'string', 'max:160'],
            'beneficiary_phone'        => ['nullable', 'string', 'max:40'],
            'beneficiary_id_document'  => ['nullable', 'string', 'max:80'],
            'expected_return_at'       => ['nullable', 'date', 'after_or_equal:today'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'lines'                    => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.stock_item_id'    => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.quantity'         => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.packaging'        => ['nullable', 'string', 'max:40'],
            'lines.*.notes'            => ['nullable', 'string', 'max:255'],
        ], [
            'reason.required'           => 'Indiquez pourquoi le matériel quitte l\'établissement.',
            'beneficiary_name.required' => 'Indiquez le nom de la personne qui emporte le matériel : il signe le bon.',
            'lines.required'            => 'Ajoutez au moins un article.',
            'lines.*.quantity.gt'       => 'La quantité doit être positive.',
            'issued_at.before_or_equal' => 'Une sortie ne se date pas dans le futur.',
        ]);

        try {
            $sortie = $this->service->create($data, Auth::user());
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'sortie_externe', "Sortie hors établissement {$sortie->number} — {$sortie->reasonLabel()} — "
            . "{$sortie->beneficiaire}, " . number_format($sortie->total_value / 100, 0, ',', ' ') . ' FCFA', 'economat',
            ['external_issue_id' => $sortie->id]);

        return redirect()->route('economat.external_issues.show', $sortie)
            ->with('success', "Bon de sortie {$sortie->number} validé : le matériel est sorti du stock.");
    }

    public function show(ExternalIssue $sortie): View
    {
        return view('economat.external_issues.show', [
            'sortie' => $sortie->load(['lines.item.packagings', 'issuedBy', 'cancelledBy']),
        ]);
    }

    /** Le bon de sortie imprimable, signé du nom de la personne qui emporte le matériel. */
    public function print(ExternalIssue $sortie): View
    {
        return view('economat.external_issues.print', [
            'sortie' => $sortie->load(['lines.item.packagings', 'issuedBy', 'cancelledBy']),
            'tenant' => \App\Models\Tenant::first(),
        ]);
    }

    public function cancel(Request $request, ExternalIssue $sortie): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Donnez le motif de l\'annulation.',
        ]);

        try {
            $sortie = $this->service->cancel($sortie, Auth::user(), trim($data['cancellation_reason']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'sortie_externe', "Annulation de la sortie {$sortie->number} : {$sortie->cancellation_reason}",
            'economat', ['external_issue_id' => $sortie->id]);

        return back()->with('success', "Bon {$sortie->number} annulé : le matériel est revenu en stock.");
    }

    public function export(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);
        abort_unless(DocumentExporter::formatValide($format), 404);

        $sorties = $this->filtrer($request)->with(['issuedBy'])->withCount('lines')
            ->orderBy('issued_at')->orderBy('id')->limit(self::MAX_EXPORT)->get();

        AuditLog::record(Auth::id(), 'export', 'Export des sorties hors établissement (' . $format . ') — '
            . $sorties->count() . ' ligne(s)', 'economat', ['format' => $format, 'filtres' => $request->query()]);

        [$du, $au] = $this->periode($request);

        $document = Document::intitule('Sorties hors établissement')
            ->sousTitre('Matériel sorti du magasin sans servir l\'établissement : prêts, réparations, dons, cessions, transferts, restitutions')
            ->periode($du, $au)
            ->filtres($this->filtresAppliques($request))
            ->colonnes([
                Colonne::texte('number', 'N° de bon'),
                Colonne::dateHeure('issued_at', 'Sortie le'),
                Colonne::texte('reason_label', 'Motif'),
                Colonne::texte('beneficiaire', 'Emporté par'),
                Colonne::texte('beneficiary_phone', 'Téléphone'),
                Colonne::nombre('lines_count', 'Articles'),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::date('expected_return_at', 'Retour prévu'),
                Colonne::texte('status_label', 'Statut'),
                Colonne::texte('issuedBy.name', 'Validé par'),
            ])
            ->lignes($sorties->map(function (ExternalIssue $s) {
                $s->setAttribute('reason_label', $s->reasonLabel());
                $s->setAttribute('status_label', $s->statusLabel());
                // Une sortie annulée ne compte pas dans le total.
                $s->setAttribute('valeur', $s->isCancelled() ? 0 : (int) $s->total_value);

                return $s;
            }));

        if ($sorties->count() >= self::MAX_EXPORT) {
            $document->note('Export limité aux ' . self::MAX_EXPORT . ' premiers bons. Affinez la période pour le reste.');
        }

        return $exporteur->rendre($document, $format);
    }

    /** Filtres de la liste et de l'export : une seule requête, pour qu'ils ne divergent pas. */
    private function filtrer(Request $request): Builder
    {
        [$du, $au] = $this->periode($request);

        return ExternalIssue::query()
            ->when($du, fn ($q) => $q->where('issued_at', '>=', $du->copy()->startOfDay()))
            ->when($au, fn ($q) => $q->where('issued_at', '<=', $au->copy()->endOfDay()))
            ->when(array_key_exists((string) $request->query('motif'), ExternalIssue::REASONS), fn ($q) => $q->where('reason', $request->query('motif')))
            ->when(array_key_exists((string) $request->query('statut'), ExternalIssue::STATUSES), fn ($q) => $q->where('status', $request->query('statut')))
            ->when($request->query('retour') === 'en_retard', fn ($q) => $q->validees()->whereNotNull('expected_return_at')->whereDate('expected_return_at', '<', today()))
            ->when((int) $request->query('article'), fn ($q, $id) => $q->whereHas('lines', fn ($l) => $l->where('stock_item_id', $id)))
            ->when(trim((string) $request->query('recherche')), function ($q, $texte) {
                // Sans tenir compte de la casse : LIKE la distingue sous PostgreSQL.
                $motif = '%' . mb_strtolower($texte) . '%';
                $q->where(function ($r) use ($motif) {
                    foreach (['number', 'beneficiary_name', 'beneficiary_organisation', 'beneficiary_phone', 'beneficiary_id_document'] as $colonne) {
                        $r->orWhereRaw("LOWER({$colonne}) LIKE ?", [$motif]);
                    }
                });
            });
    }

    /**
     * Une date précise, ou une période du … au … .
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function periode(Request $request): array
    {
        if ($jour = $this->date($request->query('date'))) {
            return [$jour, $jour->copy()];
        }

        return [$this->date($request->query('du')), $this->date($request->query('au'))];
    }

    private function filtresAppliques(Request $request): array
    {
        [$du, $au] = $this->periode($request);
        $article = (int) $request->query('article') ? StockItem::find((int) $request->query('article')) : null;

        return array_filter([
            'Date'      => $request->query('date') && $du ? $du->format('d/m/Y') : null,
            'Du'        => !$request->query('date') && $du ? $du->format('d/m/Y') : null,
            'Au'        => !$request->query('date') && $au ? $au->format('d/m/Y') : null,
            'Motif'     => ExternalIssue::REASONS[$request->query('motif')] ?? null,
            'Statut'    => ExternalIssue::STATUSES[$request->query('statut')] ?? null,
            'Retour'    => $request->query('retour') === 'en_retard' ? 'Retours en retard' : null,
            'Article'   => $article?->name,
            'Recherche' => trim((string) $request->query('recherche')) ?: null,
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
}
