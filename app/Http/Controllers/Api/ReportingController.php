<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\BusinessReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de reporting business (lecture seule) consommée par la console
 * business de pms. Chaque endpoint renvoie les données de CET établissement ;
 * l'agrégation entre établissements et les tendances sont calculées côté pms.
 *
 * Protégée par le middleware reporting.token (secret de service partagé).
 */
class ReportingController extends Controller
{
    public function __construct(private BusinessReportingService $reporting)
    {
    }

    private function period(Request $request): string
    {
        $p = (string) $request->query('period', 'month');
        return in_array($p, ['today', 'week', 'month', 'year'], true) ? $p : 'month';
    }

    /** Résumé 360° pour la page d'accueil business. */
    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->reporting->summary($this->period($request)));
    }

    /** Séries d'évolution du revenu (graphes). */
    public function revenue(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        return response()->json([
            'period' => $period,
            'totals' => $this->reporting->revenue($start, $end),
            'series' => $this->reporting->revenueSeries($period, $start, $end),
        ]);
    }

    /** Audit de caisse : sessions avec écart déclaré vs théorique. */
    public function cashAudit(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        return response()->json([
            'period'   => $period,
            'summary'  => $this->reporting->cashSummary($start, $end),
            'sessions' => $this->reporting->cashSessions($start, $end),
        ]);
    }

    /** Dépenses / décaissements. */
    public function expenses(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        return response()->json([
            'period'   => $period,
            'expenses' => $this->reporting->expenses($start, $end),
        ]);
    }

    /** Personnel de l'établissement. */
    public function staff(): JsonResponse
    {
        return response()->json($this->reporting->staff());
    }

    /** Analytique de la clientèle : valeur, classements, RFM, marchés émetteurs. */
    public function customers(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        return response()->json(array_merge(
            ['period' => $period],
            $this->reporting->customers($start, $end)
        ));
    }

    /** Factures (rapports financiers formels). */
    public function invoices(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        $invoices = Invoice::whereBetween('invoice_date', [$start, $end])->get();

        return response()->json([
            'period'  => $period,
            'summary' => [
                'count'          => $invoices->count(),
                'total_invoiced' => (int) $invoices->sum('total_amount'),
                'total_paid'     => (int) $invoices->sum('paid_amount'),
                'total_due'      => (int) $invoices->sum('balance_due'),
                'by_status'      => $invoices->groupBy('status')->map->count()->toArray(),
            ],
            'items' => $invoices->sortByDesc('invoice_date')->values()->map(fn (Invoice $i) => [
                'number'   => $i->invoice_number,
                'date'     => $i->invoice_date?->format('d/m/Y'),
                'total'    => (int) $i->total_amount,
                'paid'     => (int) $i->paid_amount,
                'due'      => (int) $i->balance_due,
                'status'   => $i->status,
                'has_pdf'  => !empty($i->pdf_path),
            ])->all(),
        ]);
    }

    /** Rapport financier complet (page Rapport / audit + exports). */
    public function finance(Request $request): JsonResponse
    {
        return response()->json($this->reporting->financeReport($this->period($request)));
    }

    /** Alertes/anomalies détectées localement. */
    public function alerts(Request $request): JsonResponse
    {
        $period = $this->period($request);
        [$start, $end] = $this->reporting->periodRange($period);

        return response()->json([
            'period' => $period,
            'alerts' => $this->reporting->localAlerts($start, $end),
        ]);
    }
}
