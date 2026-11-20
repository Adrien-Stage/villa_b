<?php

namespace App\Http\Controllers;

use App\Editions\DossierJournalier;
use App\Models\AuditLog;
use App\Models\NightAudit;
use App\Services\DocumentExporter;
use App\Services\PermissionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DossierJournalierController extends Controller
{
    public function __construct(private readonly DossierJournalier $dossier) {}

    public function show(Request $request): View
    {
        [$jour, , $documents, $feuille] = $this->charger($request);

        return view('editions.dossier', compact('jour', 'documents', 'feuille') + [
            'derniereCloture' => NightAudit::lastClosedDate(),
        ]);
    }

    public function print(Request $request, DocumentExporter $exporteur): Response
    {
        return $this->rendre($request, DocumentExporter::FORMAT_IMPRESSION, $exporteur);
    }

    public function export(Request $request, DocumentExporter $exporteur): Response
    {
        $format = $request->query('format', DocumentExporter::FORMAT_PDF);
        abort_unless(in_array($format, [DocumentExporter::FORMAT_PDF, DocumentExporter::FORMAT_EXCEL], true), 404);

        return $this->rendre($request, $format, $exporteur);
    }

    private function rendre(Request $request, string $format, DocumentExporter $exporteur): Response
    {
        [$jour, $audit, $documents] = $this->charger($request);
        $reponse = $exporteur->rendreDossier(DossierJournalier::TITRE, array_values($documents), $format);

        AuditLog::record($request->user()->id, 'export', DossierJournalier::TITRE, 'editions', [
            'format' => $format,
            'jour' => $jour->toDateString(),
            'cloture' => $audit?->id,
            'feuilles' => array_keys($documents),
        ]);

        return $reponse;
    }

    private function charger(Request $request): array
    {
        $user = $request->user();
        abort_unless(app(PermissionResolver::class)->allows($user, 'editions.voir'), 403);
        $cles = $this->dossier->accessibles($user);
        abort_if($cles === [], 403);

        $saisie = $request->validate([
            'jour' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'feuille' => ['sometimes', 'required', 'string'],
        ]);
        $jour = isset($saisie['jour']) ? CarbonImmutable::parse($saisie['jour']) : CarbonImmutable::today();
        $feuille = $saisie['feuille'] ?? null;

        if ($feuille !== null) {
            abort_unless(in_array($feuille, $cles, true), 403);
            $cles = [$feuille];
        }

        $audit = NightAudit::query()->whereDate('audit_date', $jour->toDateString())->first();
        $documents = $this->dossier->lire($jour, $audit, $cles, $user);
        abort_if($documents === [], 404);

        return [$jour, $audit, $documents, $feuille];
    }
}
