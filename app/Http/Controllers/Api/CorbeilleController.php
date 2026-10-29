<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrashEntry;
use App\Services\Corbeille;
use App\Support\Corbeille\Registre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * La corbeille de l'établissement, exposée à la console d'orchestration.
 *
 * Personne dans l'hôtel ne la voit : la console, réservée à son
 * administrateur TECH, la consulte, restaure et purge par ici. La
 * restauration est faite par l'application, qui seule sait remettre ses
 * données et ses fichiers en place ; la console transmet le nom de qui agit.
 */
class CorbeilleController extends Controller
{
    private const PAR_PAGE = 50;

    public const ETATS = ['dans' => 'Dans la corbeille', 'restauree' => 'Restaurées', 'tout' => 'Toutes'];

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'type'   => ['nullable', 'string', 'max:80'],
            'module' => ['nullable', 'string', 'max:40'],
            'auteur' => ['nullable', 'string', 'max:120'],
            'du'     => ['nullable', 'date'],
            'au'     => ['nullable', 'date'],
            'q'      => ['nullable', 'string', 'max:120'],
            'etat'   => ['nullable', 'in:' . implode(',', array_keys(self::ETATS))],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);

        $etat = $filtres['etat'] ?? 'dans';
        $requete = TrashEntry::query()
            ->when($etat === 'dans', fn ($q) => $q->enCorbeille())
            ->when($etat === 'restauree', fn ($q) => $q->restaurees())
            ->when($filtres['type'] ?? null, fn ($q, $t) => $q->where('table_name', $t))
            ->when($filtres['module'] ?? null, fn ($q, $m) => $q->where('module', $m))
            ->when($filtres['auteur'] ?? null, fn ($q, $a) => $q->where('deleted_by_name', $a))
            ->when($filtres['du'] ?? null, fn ($q, $d) => $q->where('deleted_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtres['au'] ?? null, fn ($q, $d) => $q->where('deleted_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($filtres['q'] ?? null, fn ($q, $texte) => $q->whereRaw('LOWER(label) LIKE ?', ['%' . mb_strtolower($texte) . '%']))
            ->orderByDesc('deleted_at')->orderByDesc('id');

        $page = $requete->paginate(self::PAR_PAGE)->withQueryString();

        return response()->json([
            'entrees'    => collect($page->items())->map(fn (TrashEntry $e) => $this->presenter($e))->all(),
            'pagination' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total(), 'par_page' => $page->perPage()],
            // De quoi remplir les filtres de la console avec ce qui existe.
            'filtres'    => [
                'types'   => TrashEntry::query()->distinct()->orderBy('table_name')->pluck('table_name')
                    ->mapWithKeys(fn ($t) => [$t => Registre::type($t)])->all(),
                'modules' => array_intersect_key(Registre::MODULES, array_flip(TrashEntry::query()->distinct()->pluck('module')->all())),
                'auteurs' => TrashEntry::query()->whereNotNull('deleted_by_name')->distinct()->orderBy('deleted_by_name')->pluck('deleted_by_name')->all(),
                'etats'   => self::ETATS,
            ],
        ]);
    }

    public function restore(Request $request, TrashEntry $entree, Corbeille $corbeille): JsonResponse
    {
        $par = $this->auteur($request);

        try {
            $entree = $corbeille->restaurer($entree, $par);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['entree' => $this->presenter($entree)]);
    }

    public function destroy(Request $request, TrashEntry $entree, Corbeille $corbeille): JsonResponse
    {
        $id = $entree->id;
        $corbeille->purger($entree, $this->auteur($request));

        return response()->json(['purgee' => $id]);
    }

    private function auteur(Request $request): string
    {
        $valide = $request->validate(['auteur' => ['required', 'string', 'max:120']], [
            'auteur.required' => 'La console doit nommer qui agit.',
        ]);

        return $valide['auteur'];
    }

    private function presenter(TrashEntry $e): array
    {
        return [
            'id'          => $e->id,
            'type'        => $e->type,
            'table'       => $e->table_name,
            'module'      => $e->module,
            'module_nom'  => Registre::MODULES[$e->module] ?? $e->module,
            'libelle'     => $e->label,
            'supprime_par' => $e->deleted_by_name,
            'supprime_le' => $e->deleted_at?->toIso8601String(),
            'restaure_le' => $e->restored_at?->toIso8601String(),
            'restaure_par' => $e->restored_by,
            'contenu'     => $e->resume(),
        ];
    }
}
