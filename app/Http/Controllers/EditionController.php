<?php

namespace App\Http\Controllers;

use App\Editions\Catalogue;
use App\Editions\Edition;
use App\Editions\Pieces;
use App\Models\AuditLog;
use App\Services\DocumentExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * La rubrique Éditions : le point où l'on retrouve tout ce qui s'imprime.
 *
 * Elle produit ses registres et situations à partir des filtres choisis,
 * les montre à l'écran, les imprime ou les exporte ; elle mène aux documents
 * qui ont déjà leur écran ; elle retrouve une pièce par son numéro.
 */
class EditionController extends Controller
{
    /** Lignes montrées à l'écran ; l'impression et les exports prennent tout. */
    private const APERCU = 300;

    public function __construct(private readonly Catalogue $catalogue) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $recherche = trim((string) $request->query('q', ''));

        return view('editions.index', [
            'familles' => $this->catalogue->pour($user)->groupBy(fn (Edition $e) => $e->famille()),
            'specialisees' => $this->catalogue->specialiseesPour($user)->groupBy('famille'),
            'recherche' => $recherche,
            'pieces' => $recherche !== '' ? app(Pieces::class)->chercher($recherche, $user) : collect(),
        ]);
    }

    public function show(Request $request, string $edition): View
    {
        $user = Auth::user();
        $edition = $this->edition($edition);
        $valeurs = $edition->valeurs($request->query(), $user);
        $document = $edition->document($valeurs, $user);

        return view('editions.show', [
            'edition' => $edition,
            'valeurs' => $valeurs,
            'document' => $document,
            'apercu' => self::APERCU,
            'parametres' => $this->parametres($request),
            'devise' => $document->enTeteEtablissement()['devise'] ?? 'FCFA',
        ]);
    }

    /** Aperçu imprimable, qui lance l'impression à l'ouverture. */
    public function print(Request $request, string $edition, DocumentExporter $exporteur): Response
    {
        return $this->rendre($request, $edition, DocumentExporter::FORMAT_IMPRESSION, $exporteur);
    }

    /** PDF, Excel ou Word : une extraction, sous son propre droit. */
    public function export(Request $request, string $edition, DocumentExporter $exporteur): Response
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_PDF);
        abort_unless(DocumentExporter::formatValide($format) && $format !== DocumentExporter::FORMAT_IMPRESSION, 404);

        return $this->rendre($request, $edition, $format, $exporteur);
    }

    private function rendre(Request $request, string $cle, string $format, DocumentExporter $exporteur): Response
    {
        $user = Auth::user();
        $edition = $this->edition($cle);
        $valeurs = $edition->valeurs($request->query(), $user);
        $document = $edition->document($valeurs, $user);

        AuditLog::record($user->id, 'export', "Édition « {$edition->titre()} » ({$format}) — {$document->lesLignes()->count()} ligne(s)", 'editions', [
            'edition' => $edition->cle(),
            'format' => $format,
            'filtres' => $edition->filtresImprimes($valeurs, $user),
        ]);

        return $exporteur->rendre($document, $format);
    }

    /** L'édition demandée, à condition que la personne puisse la lire. */
    private function edition(string $cle): Edition
    {
        $edition = $this->catalogue->trouver($cle);
        abort_if($edition === null, 404);
        abort_unless($edition->accessiblePour(Auth::user()), 403, "Cette édition relève d'un autre service.");

        return $edition;
    }

    /** @return array<string, string> filtres de la requête, à reporter sur les liens d'impression */
    private function parametres(Request $request): array
    {
        return collect($request->query())->except(['format'])
            ->filter(fn ($v) => is_string($v))->all();
    }
}
