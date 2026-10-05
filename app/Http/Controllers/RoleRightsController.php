<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionMatrix;
use App\Services\PermissionResolver;
use App\Support\PermissionCatalog;
use App\Support\PermissionLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Rôles & droits » de l'établissement, réglés par son administrateur.
 *
 * La même grille que celle de la console d'orchestration, limitée à la
 * couche de l'hôtel : le modèle et la couche de la console s'y lisent sans se
 * modifier. S'y ajoutent les exceptions nominatives — un droit accordé ou
 * refusé à une personne précise, pour un motif, jusqu'à une date.
 *
 * Un refus, quelle que soit sa couche, l'emporte toujours.
 */
class RoleRightsController extends Controller
{
    private const ORIGINE = PermissionGrant::ORIGINE_ETABLISSEMENT;

    public function __construct(private readonly PermissionMatrix $matrice) {}

    public function index(Request $request): View
    {
        return view('administration.droits', [
            'matrice' => $this->matrice->donnees(),
            'empreinte' => $this->matrice->empreinte(self::ORIGINE),
            'onglet' => in_array($request->query('onglet'), ['matrice', 'exceptions', 'alertes'], true)
                ? $request->query('onglet') : 'matrice',
            'personnel' => User::query()->with('roles')->where('is_active', true)->orderBy('name')->get()
                ->reject(fn (User $u) => $u->isAdmin() || $u->isSupport() || $u->rolesDetenus() === ['customer_guest'])
                ->values(),
        ]);
    }

    /** Ce que changerait la couche de l'hôtel, sans rien enregistrer. */
    public function apercu(Request $request): JsonResponse
    {
        $lot = $this->lot($request);

        if ($refus = $this->matrice->refusDuLot($lot)) {
            return response()->json(['ok' => false] + $refus, 422);
        }

        return response()->json(['ok' => true, 'apercu' => $this->matrice->apercu($lot, self::ORIGINE)]);
    }

    /** Remplace la couche de l'hôtel, et elle seule. */
    public function update(Request $request): RedirectResponse
    {
        if (! is_array($request->input('ecarts'))) {
            // Un formulaire n'envoie pas de tableau vide : sans écart, la
            // couche de l'hôtel est vidée — une décision comme une autre.
            $request->merge(['ecarts' => []]);
        }

        $lot = $this->lot($request, [
            'motif' => ['required', 'string', 'max:255'],
            'derogation' => ['nullable', 'boolean'],
            'empreinte' => ['required', 'string', 'max:64'],
        ]);

        if ($refus = $this->matrice->refusDuLot($lot)) {
            return back()->with('error', $refus['message'].' '.implode(', ', array_merge(
                $refus['inconnus'] ?? [], $refus['roles'] ?? [], $refus['doublons'] ?? []
            )));
        }

        if (! hash_equals($this->matrice->empreinte(self::ORIGINE), (string) $request->input('empreinte'))) {
            return back()->with('error', "Les droits de l'hôtel ont changé depuis l'ouverture de l'écran : rien n'a été enregistré. Rechargez la page, puis refaites vos changements.");
        }

        $cumuls = $this->matrice->cumulsDuLot($lot);

        if ($cumuls !== [] && ! $request->boolean('derogation')) {
            return back()->with('error', 'Ce lot ouvre des cumuls de fonctions incompatibles : accordez la dérogation, avec un motif, ou retirez-les.');
        }

        DB::transaction(fn () => $this->matrice->remplacerCouche($lot, self::ORIGINE));
        app(PermissionResolver::class)->forget();

        AuditLog::record(Auth::id(),
            $cumuls === [] ? 'permission_matrix_etablissement' : 'duty_segregation_override',
            "Droits de l'hôtel réglés : ".count($lot)." écart(s) — motif : {$request->string('motif')}"
                .($cumuls === [] ? '' : ' — dérogation : '.implode(' ; ', array_map(
                    static fn (array $c): string => "{$c['role']} reçoit {$c['permission']} ({$c['motif']})",
                    $cumuls
                ))),
            'security', ['ecarts' => $lot, 'cumuls' => $cumuls]);

        return redirect()->route('droits.index')->with('success', count($lot)." écart(s) de l'hôtel en vigueur.");
    }

    /**
     * Exception nominative : un droit accordé ou refusé à une personne, pour
     * un motif, éventuellement jusqu'à une date.
     */
    /**
     * Exception nominative : un ou plusieurs droits accordés ou retirés à une
     * seule personne, l'emportant sur ses rôles. Depuis la fiche, on coche
     * les accès à retirer et l'on donne un motif commun.
     */
    public function storeException(Request $request): RedirectResponse
    {
        $catalogue = array_keys(PermissionCatalog::all());

        $valide = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required_without:permissions', 'nullable', 'string', Rule::in($catalogue)],
            'permissions' => ['required_without:permission', 'nullable', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in($catalogue)],
            'effect' => ['required', Rule::in([PermissionGrant::EFFET_ALLOW, PermissionGrant::EFFET_DENY])],
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'derogation' => ['nullable', 'boolean'],
            'retour' => ['nullable', 'in:fiche'],
        ], [
            'reason.required' => 'Une exception se motive.',
            'permission.required_without' => 'Choisissez au moins un accès.',
            'permissions.required_without' => 'Choisissez au moins un accès.',
            'permission.in' => "Ce droit n'existe pas dans le catalogue.",
            'permissions.*.in' => "Un des droits choisis n'existe pas dans le catalogue.",
            'expires_at.after' => "L'échéance doit être à venir.",
        ]);

        $personne = User::findOrFail($valide['user_id']);
        $droits = array_values(array_unique(array_filter([$valide['permission'] ?? null, ...($valide['permissions'] ?? [])])));

        // L'administrateur et le support ont leurs droits par construction :
        // aucune exception ne les étend ni ne les borne.
        if ($personne->isAdmin() || $personne->isSupport()) {
            return back()->with('error', "Les droits de l'administrateur et du support ne se règlent pas par exception.");
        }

        if ($valide['effect'] === PermissionGrant::EFFET_ALLOW && ! $request->boolean('derogation')) {
            $cumuls = collect($droits)->flatMap(fn (string $droit) => $this->matrice->cumulsDUneException($personne, $droit));

            if ($cumuls->isNotEmpty()) {
                return back()->withInput()->with('error',
                    "Cette exception ferait cumuler à {$personne->name} des fonctions incompatibles : "
                    .$cumuls->pluck('motif')->unique()->implode(' ; ')
                    .' Cochez la dérogation pour l\'accorder malgré tout.');
            }
        }

        DB::transaction(function () use ($droits, $personne, $valide) {
            foreach ($droits as $droit) {
                PermissionGrant::updateOrCreate(
                    [
                        'subject_type' => PermissionGrant::SUJET_USER,
                        'subject_id' => (string) $personne->id,
                        'permission' => $droit,
                        'origin' => self::ORIGINE,
                    ],
                    [
                        'effect' => $valide['effect'],
                        'reason' => $valide['reason'],
                        'expires_at' => $valide['expires_at'] ?? null,
                    ]
                );
            }
        });
        app(PermissionResolver::class)->forget($personne);

        AuditLog::record(Auth::id(), 'permission_exception',
            "Exception pour {$personne->name} : ".($valide['effect'] === 'deny' ? 'refus' : 'autorisation')
                .' de '.implode(', ', $droits).(! empty($valide['expires_at']) ? " jusqu'au {$valide['expires_at']}" : '')
                ." — motif : {$valide['reason']}",
            'security', ['target_user_id' => $personne->id, 'permissions' => $droits,
                'effect' => $valide['effect'], 'derogation' => $request->boolean('derogation')]);

        $message = $valide['effect'] === PermissionGrant::EFFET_DENY
            ? (count($droits) > 1 ? count($droits).' accès retirés.' : 'Accès retiré.')
            : (count($droits) > 1 ? count($droits).' accès accordés.' : 'Accès accordé.');

        return ($valide['retour'] ?? null) === 'fiche'
            ? redirect()->route('users.show', $personne)->with('success', $message)
            : redirect()->route('droits.index', ['onglet' => 'exceptions'])->with('success', $message);
    }

    public function destroyException(Request $request, PermissionGrant $grant): RedirectResponse
    {
        // Seules les exceptions de l'hôtel se retirent ici.
        abort_unless($grant->subject_type === PermissionGrant::SUJET_USER && $grant->origin === self::ORIGINE, 404);

        $personne = User::find((int) $grant->subject_id);
        $grant->delete();
        app(PermissionResolver::class)->forget();

        AuditLog::record(Auth::id(), 'permission_exception',
            'Exception retirée pour '.($personne?->name ?? "le compte #{$grant->subject_id}")." : {$grant->permission}",
            'security', ['target_user_id' => $grant->subject_id, 'permission' => $grant->permission]);

        return $request->input('retour') === 'fiche' && $personne
            ? redirect()->route('users.show', $personne)->with('success', 'Exception levée : '.PermissionLabels::complet($grant->permission).'.')
            : redirect()->route('droits.index', ['onglet' => 'exceptions'])->with('success', 'Exception retirée.');
    }

    /**
     * Lot d'écarts de rôle validé.
     *
     * @param  array<string, list<string>>  $reglesEnPlus
     * @return list<array<string, mixed>>
     */
    private function lot(Request $request, array $reglesEnPlus = []): array
    {
        $valide = $request->validate([
            'ecarts' => ['present', 'array'],
            'ecarts.*.role' => ['required', 'string', 'max:64'],
            'ecarts.*.permission' => ['required', 'string', 'max:128'],
            'ecarts.*.effect' => ['required', 'in:allow,deny'],
            'ecarts.*.scope' => ['nullable', 'in:propre,departement,etablissement'],
            'ecarts.*.reason' => ['nullable', 'string', 'max:255'],
        ] + $reglesEnPlus, [
            'motif.required' => 'Indiquez le motif de la modification.',
        ]);

        return array_values($valide['ecarts']);
    }
}
