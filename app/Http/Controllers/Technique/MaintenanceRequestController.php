<?php

namespace App\Http\Controllers\Technique;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\ServiceStore;
use App\Models\ServiceStoreStock;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Demandes d'intervention technique : le tableau du service, la liste, la
 * demande faite par n'importe quel service, et chaque étape de son cycle.
 */
class MaintenanceRequestController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly PermissionResolver $droits,
    ) {
    }

    /** Le tableau du service technique : ce qui attend, ce qui est en cours, ce qui m'est confié. */
    public function index(): View
    {
        $user = Auth::user();
        $actives = MaintenanceRequest::query()->active()->with('room', 'technician', 'requester')->parPriorite()->get();
        $mesSpecialites = $user->technicianSpecialties()->pluck('specialty')->all();

        return view('technique.index', [
            'aPrendre'     => $actives->where('status', MaintenanceRequest::STATUS_OPEN)
                ->sortBy(fn ($d) => in_array($d->specialty, $mesSpecialites, true) ? 0 : 1)->values(),
            'miennes'      => $actives->where('assigned_to', $user->id)->values(),
            'enCours'      => $actives->where('status', '!=', MaintenanceRequest::STATUS_OPEN)->values(),
            'terminees'    => MaintenanceRequest::query()->where('status', MaintenanceRequest::STATUS_DONE)
                ->where('completed_at', '>=', now()->subDays(7))->with('room', 'technician')->latest('completed_at')->take(10)->get(),
            'compteurs'    => [
                'ouvertes'  => $actives->where('status', MaintenanceRequest::STATUS_OPEN)->count(),
                'urgentes'  => $actives->where('priority', MaintenanceRequest::PRIORITY_URGENT)->count(),
                'en_cours'  => $actives->where('status', MaintenanceRequest::STATUS_IN_PROGRESS)->count(),
                'terminees' => MaintenanceRequest::query()->where('status', MaintenanceRequest::STATUS_DONE)->where('completed_at', '>=', now()->startOfDay())->count(),
            ],
            'mesSpecialites' => $mesSpecialites,
            'peutPrendre'    => $this->droits->allows($user, 'technique.demandes.take'),
        ]);
    }

    public function list(Request $request): View
    {
        $user = Auth::user();
        $statut = $request->query('statut', 'actives');

        $demandes = MaintenanceService::visibles($user, $this->toutVoir($user))
            ->with('room', 'technician', 'requester')
            ->when($statut === 'actives', fn ($q) => $q->active())
            ->when(array_key_exists($statut, MaintenanceRequest::STATUSES), fn ($q) => $q->where('status', $statut))
            ->when($request->query('specialite'), fn ($q, $s) => $q->where('specialty', $s))
            ->when($request->query('priorite'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('service'), fn ($q, $s) => $q->where('department', $s))
            ->when(trim((string) $request->query('q')), fn ($q, $texte) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($texte) . '%'])
                ->orWhereRaw('LOWER(reference) LIKE ?', ['%' . mb_strtolower($texte) . '%'])
                ->orWhereHas('room', fn ($r) => $r->where('number', $texte))))
            ->parPriorite()
            ->paginate(25)
            ->withQueryString();

        return view('technique.demandes.index', [
            'demandes' => $demandes,
            'statut'   => $statut,
            'toutVoir' => $this->toutVoir($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = Auth::user();

        return view('technique.demandes.create', [
            'services' => MaintenanceService::servicesDe($user),
            'rooms'    => Room::query()->orderBy('number')->get(['id', 'number', 'status']),
            'choisie'  => $request->integer('room') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $services = MaintenanceService::servicesDe($user);

        $donnees = $request->validate([
            'title'           => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:3000'],
            'specialty'       => ['required', Rule::in(array_keys(MaintenanceRequest::SPECIALTIES))],
            'priority'        => ['required', Rule::in(array_keys(MaintenanceRequest::PRIORITIES))],
            'department'      => ['required', Rule::in(array_keys($services))],
            'room_id'         => ['nullable', 'integer', 'exists:rooms,id'],
            'location'        => ['nullable', 'string', 'max:150', 'required_without:room_id'],
            'bloquer_chambre' => ['nullable', 'boolean'],
        ], [
            'title.required'             => 'Décrivez le problème en quelques mots.',
            'location.required_without' => 'Indiquez la chambre ou le lieu de l\'intervention.',
        ]);

        $demande = $this->maintenance->signaler($donnees, $user);

        return redirect()->route('technique.demandes.show', $demande)
            ->with('success', "Demande {$demande->reference} envoyée au service technique.");
    }

    public function show(MaintenanceRequest $demande): View
    {
        $user = Auth::user();
        abort_unless($this->voit($user, $demande), 403);

        $demande->load(['room', 'requester', 'technician', 'assigner', 'completer', 'events.user', 'parts.item']);

        $depots = MaintenanceService::depotsTechniques();
        $stocks = ServiceStoreStock::query()->whereIn('service_store_id', $depots->pluck('id'))
            ->where('current_stock', '>', 0)->with('item:id,name,unit,reference')->get()
            ->filter(fn ($s) => $s->item !== null)
            ->groupBy('service_store_id');

        return view('technique.demandes.show', [
            'demande'      => $demande,
            'techniciens'  => User::query()->active()->havingRole(MaintenanceService::TECHNICIENS)
                ->with('technicianSpecialties')->orderBy('name')->get(),
            'depots'       => $depots,
            'stocks'       => $stocks,
            'peut'         => [
                'affecter'  => $demande->isActive() && $this->droits->allows($user, 'technique.demandes.assign'),
                'prendre'   => $demande->status === MaintenanceRequest::STATUS_OPEN && $demande->assigned_to === null
                    && $this->droits->allows($user, 'technique.demandes.take') && $user->exerce(MaintenanceService::TECHNICIENS),
                'commencer' => $demande->status === MaintenanceRequest::STATUS_ASSIGNED && $this->intervient($user, $demande, 'technique.demandes.start'),
                'pieces'    => $demande->isActive() && $demande->assigned_to !== null && $this->intervient($user, $demande, 'technique.demandes.parts'),
                'terminer'  => in_array($demande->status, [MaintenanceRequest::STATUS_ASSIGNED, MaintenanceRequest::STATUS_IN_PROGRESS], true)
                    && $this->intervient($user, $demande, 'technique.demandes.complete'),
                'rouvrir'   => $demande->peutEtreRouverte() && $this->peutRouvrir($user, $demande),
                'annuler'   => $demande->peutEtreAnnulee() && $this->peutAnnuler($user, $demande),
            ],
        ]);
    }

    public function assign(Request $request, MaintenanceRequest $demande): RedirectResponse
    {
        $donnees = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'specialty'     => ['nullable', Rule::in(array_keys(MaintenanceRequest::SPECIALTIES))],
            'priority'      => ['nullable', Rule::in(array_keys(MaintenanceRequest::PRIORITIES))],
        ]);

        return $this->etape($demande, fn () => $this->maintenance->affecter($demande, User::findOrFail($donnees['technician_id']), Auth::user(),
            $donnees['specialty'] ?? null, $donnees['priority'] ?? null), 'Intervention affectée.');
    }

    public function take(MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless(Auth::user()->exerce(MaintenanceService::TECHNICIENS), 403);

        return $this->etape($demande, fn () => $this->maintenance->prendre($demande, Auth::user()), 'Vous avez pris cette intervention.');
    }

    public function start(MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless($this->intervient(Auth::user(), $demande, 'technique.demandes.start'), 403);

        return $this->etape($demande, fn () => $this->maintenance->commencer($demande, Auth::user()), 'Intervention commencée.');
    }

    public function parts(Request $request, MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless($this->intervient(Auth::user(), $demande, 'technique.demandes.parts'), 403);

        $donnees = $request->validate([
            'depot'      => ['required', 'integer', 'exists:service_stores,id'],
            'pieces'     => ['required', 'array'],
            'pieces.*'   => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ], ['pieces.required' => 'Indiquez au moins une pièce et sa quantité.']);

        return $this->etape($demande, fn () => $this->maintenance->employerPieces($demande, ServiceStore::findOrFail($donnees['depot']),
            array_map('floatval', array_filter($donnees['pieces'], fn ($q) => $q !== null && $q !== '')), Auth::user()), 'Pièces enregistrées sur l\'intervention.');
    }

    public function complete(Request $request, MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless($this->intervient(Auth::user(), $demande, 'technique.demandes.complete'), 403);

        $donnees = $request->validate(['resolution' => ['required', 'string', 'max:3000']], [
            'resolution.required' => 'Dites ce qui a été fait : le demandeur le lira.',
        ]);

        return $this->etape($demande, fn () => $this->maintenance->terminer($demande, $donnees['resolution'], Auth::user()),
            'Intervention terminée : le demandeur est prévenu.');
    }

    public function reopen(Request $request, MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless($this->peutRouvrir(Auth::user(), $demande), 403);

        $donnees = $request->validate(['motif' => ['required', 'string', 'max:1000']], [
            'motif.required' => 'Dites ce qui ne va toujours pas.',
        ]);

        return $this->etape($demande, fn () => $this->maintenance->rouvrir($demande, $donnees['motif'], Auth::user()),
            'Demande rouverte : le service technique est prévenu.');
    }

    public function cancel(Request $request, MaintenanceRequest $demande): RedirectResponse
    {
        abort_unless($this->peutAnnuler(Auth::user(), $demande), 403);

        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        return $this->etape($demande, fn () => $this->maintenance->annuler($demande, $donnees['motif'], Auth::user()), 'Demande annulée.');
    }

    // ── Garde ────────────────────────────────────────────────────────────────

    private function etape(MaintenanceRequest $demande, callable $action, string $succes): RedirectResponse
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return redirect()->route('technique.demandes.show', $demande)->with('error', $e->getMessage());
        }

        return redirect()->route('technique.demandes.show', $demande)->with('success', $succes);
    }

    /** Le service technique et la direction voient toutes les demandes. */
    private function toutVoir(User $user): bool
    {
        return $this->droits->allows($user, 'technique.voir');
    }

    private function voit(User $user, MaintenanceRequest $demande): bool
    {
        return $this->toutVoir($user)
            || $demande->requested_by === $user->id
            || in_array($demande->department, StockRequisition::servicesDirigesPar($user), true);
    }

    /** Le technicien affecté, ou le responsable technique (et la direction). */
    private function intervient(User $user, MaintenanceRequest $demande, string $droit): bool
    {
        return $this->droits->allows($user, $droit)
            && ($demande->assigned_to === $user->id || $user->isAdmin() || $user->exerce([...MaintenanceService::RESPONSABLES, 'manager']));
    }

    private function peutRouvrir(User $user, MaintenanceRequest $demande): bool
    {
        return $this->droits->allows($user, 'technique.demandes.reopen')
            && ($demande->requested_by === $user->id || $user->isAdmin()
                || $user->exerce([...MaintenanceService::RESPONSABLES, 'manager'])
                || in_array($demande->department, StockRequisition::servicesDirigesPar($user), true));
    }

    private function peutAnnuler(User $user, MaintenanceRequest $demande): bool
    {
        return $this->droits->allows($user, 'technique.demandes.cancel')
            && ($demande->requested_by === $user->id || $user->isAdmin() || $user->exerce([...MaintenanceService::RESPONSABLES, 'manager']));
    }
}
