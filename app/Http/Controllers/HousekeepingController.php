<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use App\Models\HousekeepingTeam;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\HousekeepingIssueReported;
use App\Notifications\HousekeepingRoomCleaned;
use App\Notifications\HousekeepingRoomsAssigned;
use App\Notifications\HousekeepingRoomToInspect;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HousekeepingController extends Controller
{
    public function __construct(private Notifier $notifier)
    {
    }

    /**
     * L'encadrement : la direction, le responsable des étages et les
     * gouvernantes. Ils pilotent le service, affectent, contrôlent et
     * remettent en vente toutes les chambres.
     */
    private const ENCADREMENT = ['manager', 'housekeeping_leader', 'housekeeping_supervisor'];

    /** Le personnel du service, que l'on range dans les équipes. */
    private const PERSONNEL = ['housekeeping_leader', 'housekeeping_supervisor', 'housekeeping_staff'];

    /** Statuts couverts par le cycle de nettoyage. */
    private const CYCLE_STATUSES = [
        RoomStatus::DIRTY,
        RoomStatus::CLEANING,
        RoomStatus::CLEAN,
        RoomStatus::INSPECTED,
    ];

    public function index()
    {
        // L'encadrement voit tout le pilotage ; un agent de terrain ne voit
        // que son équipe et ses chambres — deux écrans distincts.
        $vue = $this->isEncadrement()
            ? $this->chiefDashboard()
            : $this->memberDashboard();

        return $vue->with('minibar', $this->alertesMinibar());
    }

    /**
     * Ce que les mini-bars attendent : relevés demandés par la réception au
     * départ, relevés dus après un départ sans relevé ; pour l'encadrement,
     * mini-bars vidés pendant le séjour et mini-bars à recharger.
     */
    private function alertesMinibar(): array
    {
        $demandes = \App\Models\Booking::query()
            ->with('room')
            ->where('status', \App\Enums\BookingStatus::CHECKED_IN)
            ->whereNotNull('minibar_check_requested_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('minibar_checks')
                ->whereColumn('minibar_checks.booking_id', 'bookings.id')
                ->where('minibar_checks.occasion', \App\Models\MinibarCheck::OCCASION_DEPART))
            ->orderBy('minibar_check_requested_at')
            ->get();

        $dus = Room::query()->with('roomType')->whereNotNull('minibar_due_booking_id')->orderBy('number')->get();

        $vides = collect();
        $aRecharger = collect();
        if ($this->isEncadrement()) {
            $equipees = Room::query()
                ->with(['roomType', 'minibarLines'])
                ->where('is_active', true)
                ->whereHas('minibarLines', fn ($q) => $q->where('standard_quantity', '>', 0))
                ->orderBy('number')
                ->get();

            $vides = $equipees->filter(fn (Room $r) => $r->status === RoomStatus::OCCUPIED && $r->miniBarVide())->values();
            $aRecharger = $equipees->filter(fn (Room $r) => $r->status !== RoomStatus::OCCUPIED && !$r->miniBarComplet())->values();
        }

        return compact('demandes', 'dus', 'vides', 'aRecharger');
    }

    /** Tableau de bord du chef de service : pipeline, affectation, équipes. */
    private function chiefDashboard()
    {
        $teams = HousekeepingTeam::with(['leader', 'members', 'activeAssignments.room'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $staff = User::query()
            ->havingRole(self::PERSONNEL)
            ->orderBy('name')
            ->get();

        $dirtyRooms = Room::with([
                'roomType',
                'activeHousekeepingAssignment.team',
                'bookings' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->whereDate('check_in', '<=', today()->addDay())
                    ->whereDate('check_out', '>=', today())
                    ->orderBy('check_in'),
            ])
            ->where('status', RoomStatus::DIRTY)
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        $priorityRooms = $this->buildPriorityRooms($dirtyRooms);

        $blockedAssignments = HousekeepingAssignment::with(['room.roomType', 'team', 'reportedBy'])
            ->where('status', 'blocked')
            ->latest('reported_at')
            ->get();

        $completedToday = HousekeepingAssignment::with(['room.roomType', 'team', 'completedBy'])
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->latest('completed_at')
            ->get();

        $pipeline = Room::with(['roomType', 'activeHousekeepingAssignment.team', 'activeHousekeepingAssignment.startedBy',
                'latestHousekeepingAssignment.completedBy', 'minibarLines'])
            ->whereIn('status', self::CYCLE_STATUSES)
            ->orderByRaw("CASE status
                WHEN 'dirty' THEN 1 WHEN 'cleaning' THEN 2
                WHEN 'clean' THEN 3 WHEN 'inspected' THEN 4 ELSE 5 END")
            ->orderBy('floor')
            ->orderBy('number')
            ->get()
            ->groupBy(fn ($room) => $room->status->value);

        // Le chef de service met aussi la main à la pâte : les chambres
        // confiées aux équipes dont il fait partie lui sont présentées avec
        // les mêmes actions de terrain qu'à un agent.
        $myTeamIds = $teams
            ->filter(fn (HousekeepingTeam $team) => $team->leader_id === Auth::id()
                || $team->members->contains('id', Auth::id()))
            ->pluck('id');

        $myRooms = $myTeamIds->isEmpty()
            ? collect()
            : Room::with(['roomType', 'latestHousekeepingAssignment.team', 'latestHousekeepingAssignment.startedBy', 'latestHousekeepingAssignment.completedBy', 'minibarLines'])
                ->whereIn('id', HousekeepingAssignment::whereIn('housekeeping_team_id', $myTeamIds)
                    ->where('kind', HousekeepingAssignment::KIND_DEPART)
                    ->pluck('room_id')->unique())
                ->whereIn('status', self::CYCLE_STATUSES)
                ->orderByRaw("CASE status
                    WHEN 'dirty' THEN 1 WHEN 'cleaning' THEN 2
                    WHEN 'clean' THEN 3 WHEN 'inspected' THEN 4 ELSE 5 END")
                ->orderBy('number')
                ->get();

        // Recouches : les chambres occupées, que l'équipe refait pendant le
        // séjour. Celles du jour déjà faites restent visibles avec leur auteur.
        $occupiedRooms = Room::with([
                'roomType',
                'activeHousekeepingAssignment.team',
                'activeHousekeepingAssignment.startedBy',
                'latestHousekeepingAssignment.completedBy',
                'latestHousekeepingAssignment.team',
                'activeHousekeepingAssignment.minibarChecks',
                'minibarLines',
            ])
            ->where('status', RoomStatus::OCCUPIED)
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        // Chambres immobilisées en maintenance technique : visibles par l'encadrement
        $maintenanceRooms = Room::with([
                'roomType',
                'latestHousekeepingAssignment.team',
                'latestHousekeepingAssignment.reportedBy',
            ])
            ->where('status', RoomStatus::MAINTENANCE)
            ->orderBy('number')
            ->get();

        $stats = [
            'dirty_rooms'     => $dirtyRooms->count(),
            'cleaning'        => ($pipeline['cleaning'] ?? collect())->count(),
            'to_inspect'      => ($pipeline['clean'] ?? collect())->count(),
            'inspected'       => ($pipeline['inspected'] ?? collect())->count(),
            'teams'           => $teams->count(),
            'blocked'         => $blockedAssignments->count(),
            'maintenance'     => $maintenanceRooms->count(),
            'completed_today' => $completedToday->count(),
            'stayovers'       => $occupiedRooms->filter(fn (Room $r) => $r->activeHousekeepingAssignment)->count(),
        ];

        return view('housekeeping.index', [
            'isChief'            => true,
            'teams'              => $teams,
            'staff'              => $staff,
            'dirtyRooms'         => $dirtyRooms,
            'priorityRooms'      => $priorityRooms,
            'blockedAssignments' => $blockedAssignments,
            'maintenanceRooms'   => $maintenanceRooms,
            'completedToday'     => $completedToday,
            'pipeline'           => $pipeline,
            'stats'              => $stats,
            'myRooms'            => $myRooms,
            'occupiedRooms'      => $occupiedRooms,
        ]);
    }

    /** Écran simple d'un agent de terrain : son équipe et ses chambres actives. */
    private function memberDashboard()
    {
        $user = Auth::user();

        $myTeams = HousekeepingTeam::with(['leader', 'members'])
            ->where('is_active', true)
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('name')
            ->get();

        $myTeamIds = $myTeams->pluck('id');
        // Équipes que l'utilisateur dirige : accès aux actions de contrôle/libération.
        $ledTeamIds = $myTeams->where('leader_id', $user->id)->pluck('id');

        // Chambres dont mes équipes ont la charge, encore dans le cycle actif.
        $roomIds = HousekeepingAssignment::whereIn('housekeeping_team_id', $myTeamIds)
            ->where('kind', HousekeepingAssignment::KIND_DEPART)
            ->pluck('room_id')->unique();

        $myRooms = Room::with(['roomType', 'latestHousekeepingAssignment.team', 'latestHousekeepingAssignment.startedBy', 'latestHousekeepingAssignment.completedBy', 'minibarLines'])
            ->whereIn('id', $roomIds)
            ->whereIn('status', self::CYCLE_STATUSES)
            ->orderByRaw("CASE status
                WHEN 'dirty' THEN 1 WHEN 'cleaning' THEN 2
                WHEN 'clean' THEN 3 WHEN 'inspected' THEN 4 ELSE 5 END")
            ->orderBy('number')
            ->get();

        // Présence des coéquipiers (cache online, 2 min), pour « qui est là ».
        $presence = [];
        foreach ($myTeams as $team) {
            foreach ($team->members as $member) {
                $presence[$member->id] = (bool) Cache::get('user-is-online-' . $member->id, false);
            }
        }

        return view('housekeeping.index', [
            'isChief'          => false,
            'myTeams'          => $myTeams,
            'ledTeamIds'       => $ledTeamIds,
            'myRooms'          => $myRooms,
            'maintenanceRooms' => collect(),
            'myStayovers'      => $this->recouchesDesEquipes($myTeamIds),
            'presence'         => $presence,
        ]);
    }

    /** Recouches à faire ou en cours, confiées à ces équipes. */
    private function recouchesDesEquipes(Collection $teamIds): Collection
    {
        return Room::with(['roomType', 'activeHousekeepingAssignment.team', 'activeHousekeepingAssignment.startedBy',
                'activeHousekeepingAssignment.minibarChecks', 'minibarLines'])
            ->where('status', RoomStatus::OCCUPIED)
            ->whereHas('activeHousekeepingAssignment', fn ($q) => $q
                ->where('kind', HousekeepingAssignment::KIND_RECOUCHE)
                ->whereIn('housekeeping_team_id', $teamIds))
            ->orderBy('number')
            ->get();
    }

    public function storeTeam(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30'],
            'leader_id' => ['nullable', 'exists:users,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenantId = Auth::user()->tenant_id
            ?? Tenant::current()?->id;

        $memberIds = collect($validated['member_ids'])->map(fn ($id) => (int) $id);

        if (!empty($validated['leader_id']) && !$memberIds->contains((int) $validated['leader_id'])) {
            $memberIds->push((int) $validated['leader_id']);
        }

        $allowedStaffIds = User::query()
            ->havingRole(self::PERSONNEL)
            ->pluck('id');

        if ($memberIds->diff($allowedStaffIds)->isNotEmpty()) {
            return back()->withErrors([
                'team' => 'Tous les membres de l\'equipe doivent appartenir au service housekeeping.',
            ]);
        }

        $team = HousekeepingTeam::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'leader_id' => $validated['leader_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        $team->members()->sync($memberIds->unique()->all());

        return redirect()->route('housekeeping.index')->with('success', 'Equipe de nettoyage creee.');
    }

    public function assignRooms(Request $request)
    {
        $validated = $request->validate([
            'housekeeping_team_id' => ['required', 'exists:housekeeping_teams,id'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $team = HousekeepingTeam::findOrFail($validated['housekeeping_team_id']);
        $tenantId = Auth::user()->tenant_id;

        if ($team->tenant_id !== $tenantId) {
            abort(403, 'Equipe housekeeping hors tenant.');
        }

        $rooms = Room::query()
            ->whereIn('id', $validated['room_ids'])
            ->get();

        if ($rooms->count() !== count($validated['room_ids'])) {
            abort(403, 'Une ou plusieurs chambres ne sont pas accessibles.');
        }

        // Une chambre à nettoyer part au ménage de départ ; une chambre occupée
        // à la recouche, que le client garde.
        if ($rooms->contains(function ($room) {
            $status = $room->status instanceof RoomStatus ? $room->status->value : (string) $room->status;
            return !in_array($status, [RoomStatus::DIRTY->value, RoomStatus::OCCUPIED->value], true);
        })) {
            return back()->withErrors([
                'assignment' => 'Seules les chambres à nettoyer et les chambres occupées (recouche) peuvent être affectées à une équipe.',
            ]);
        }

        DB::transaction(function () use ($validated, $team, $rooms) {
            foreach ($rooms as $room) {
                $status = $room->status instanceof RoomStatus ? $room->status->value : (string) $room->status;
                $kind = $status === RoomStatus::OCCUPIED->value
                    ? HousekeepingAssignment::KIND_RECOUCHE
                    : HousekeepingAssignment::KIND_DEPART;

                $existing = $room->housekeepingAssignments()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'housekeeping_team_id' => $team->id,
                        'kind' => $kind,
                        'assigned_by' => Auth::id(),
                        'assigned_at' => now(),
                        'notes' => $validated['notes'] ?? $existing->notes,
                        'status' => 'pending',
                        'started_at' => null,
                        'started_by' => null,
                        'completed_at' => null,
                        'completed_by' => null,
                    ]);
                    $assignment = $existing;
                } else {
                    $assignment = HousekeepingAssignment::create([
                        'housekeeping_team_id' => $team->id,
                        'room_id' => $room->id,
                        'kind' => $kind,
                        'assigned_by' => Auth::id(),
                        'status' => 'pending',
                        'notes' => $validated['notes'] ?? null,
                        'assigned_at' => now(),
                    ]);
                }

                HousekeepingLog::noter($room, HousekeepingLog::ASSIGNED,
                    ($kind === HousekeepingAssignment::KIND_RECOUCHE ? 'Recouche — ' : 'Ménage de départ — ') . $team->name,
                    $assignment);
            }
        });

        // L'équipe est prévenue de sa feuille de route (hors auteur de l'action).
        try {
            $destinataires = $team->members()
                ->when(Auth::id(), fn ($q) => $q->where('users.id', '!=', Auth::id()))
                ->get();

            if ($destinataires->isNotEmpty()) {
                $this->notifier->send(
                    $destinataires,
                    new HousekeepingRoomsAssigned($team, $rooms->pluck('number')->all())
                );
            }
        } catch (\Throwable $e) {
            // Un problème d'alerte ne bloque jamais l'affectation enregistrée
        }

        return redirect()->route('housekeeping.index')->with('success', 'Chambres affectées à l\'équipe.');
    }

    public function reportIssue(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'issue_type'  => ['required', 'in:technique,interne'],
            'issue_notes' => ['required', 'string', 'max:1000'],
            'specialty'   => ['nullable', 'string', 'max:50'],
            'priority'    => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Aucune affectation active trouvée pour cette chambre.']);
        }

        $this->ensureCanClean($room);

        $isTechnique = $validated['issue_type'] === HousekeepingAssignment::ISSUE_TECHNIQUE;
        $notes = trim($validated['issue_notes']);
        $demandeTechnique = null;

        DB::transaction(function () use ($room, $assignment, $isTechnique, $notes, $validated, &$demandeTechnique) {
            $assignment->update([
                'status'      => 'blocked',
                'issue_type'  => $validated['issue_type'],
                'issue_notes' => $notes,
                'reported_by' => Auth::id(),
                'reported_at' => now(),
                'started_at'  => $assignment->started_at ?? now(),
            ]);

            if ($isTechnique) {
                // Pour un problème technique : passage automatique et direct en maintenance
                if ($room->status !== RoomStatus::MAINTENANCE) {
                    $room->updateStatus(RoomStatus::MAINTENANCE, 'Problème technique signalé par housekeeping : ' . $notes, Auth::id());
                }

                // Si le service technique est actif, créer la demande d'intervention
                if (\App\Services\MaintenanceService::actif()) {
                    $demandeTechnique = app(\App\Services\MaintenanceService::class)->depuisHousekeeping(
                        $room->fresh(),
                        $notes,
                        $assignment->fresh(),
                        Auth::user(),
                        $validated['specialty'] ?? 'autre',
                        $validated['priority'] ?? \App\Models\MaintenanceRequest::PRIORITY_HIGH,
                        bloquerChambre: true
                    );
                }
            }

            $prefix = $isTechnique ? '[Technique] ' : '[Interne Housekeeping] ';
            HousekeepingLog::noter($room, HousekeepingLog::ISSUE, $prefix . $notes, $assignment);
        });

        // Alerte de l'encadrement (gouvernante, responsable des étages)
        $prefixLabel = $isTechnique ? 'Problème technique' : 'Besoin interne Housekeeping';
        $this->notifier->toRoles(
            self::ENCADREMENT,
            new HousekeepingIssueReported($room, "[{$prefixLabel}] " . $notes, Auth::user()?->name),
            Auth::id()
        );

        if ($isTechnique) {
            $msg = "Problème technique signalé. La chambre {$room->number} est passée automatiquement en maintenance";
            if ($demandeTechnique) {
                $msg .= " et l'intervention technique {$demandeTechnique->reference} a été créée pour le service technique.";
            } else {
                $msg .= ".";
            }
            return redirect()->route('housekeeping.index')->with('success', $msg);
        }

        return redirect()->route('housekeeping.index')->with(
            'success',
            "Besoin interne signalé à la gouvernante pour la chambre {$room->number} (ex : réassort savon/linge). La chambre reste dans son cycle normal et ne passe pas en maintenance."
        );
    }

    /** Lever un incident / besoin interne et permettre la reprise ou la fin du nettoyage. */
    public function resolveIssue(Request $request, Room $room): RedirectResponse
    {
        $assignment = $room->activeHousekeepingAssignment ?? $room->latestHousekeepingAssignment;

        if (!$assignment || $assignment->status !== 'blocked') {
            return back()->withErrors(['status' => 'Aucun incident bloquant à résoudre pour cette chambre.']);
        }

        $this->ensureCanClean($room);

        DB::transaction(function () use ($room, $assignment) {
            $repriseStatus = $room->status === RoomStatus::CLEANING ? 'in_progress' : 'pending';
            $oldNotes = $assignment->issue_notes;

            $assignment->update([
                'status'      => $repriseStatus,
                'issue_notes' => null,
                'issue_type'  => null,
                'reported_by' => null,
                'reported_at' => null,
            ]);

            HousekeepingLog::noter(
                $room,
                HousekeepingLog::STARTED,
                "Incident résolu (" . Auth::user()->name . ") : " . ($oldNotes ? mb_substr($oldNotes, 0, 150) : 'reprise du travail'),
                $assignment
            );
        });

        return back()->with('success', "L'incident sur la chambre {$room->number} a été résolu. Le travail peut reprendre.");
    }

    /** La gouvernante ou le responsable des étages qualifie et transmet le problème au service technique. */
    public function escalateToTechnique(Request $request, Room $room): RedirectResponse
    {
        $this->ensureCanValidate($room);

        $validated = $request->validate([
            'notes'           => ['required', 'string', 'max:1000'],
            'specialty'       => ['nullable', 'string'],
            'priority'        => ['nullable', 'string'],
            'bloquer_chambre' => ['nullable', 'boolean'],
        ]);

        $assignment = $room->activeHousekeepingAssignment ?? $room->latestHousekeepingAssignment;
        if (!$assignment) {
            return back()->withErrors(['status' => 'Aucune affectation trouvée pour cette chambre.']);
        }

        if (!\App\Services\MaintenanceService::actif()) {
            return back()->withErrors(['status' => 'Le service technique n\'est pas activé.']);
        }

        $bloquer = $validated['bloquer_chambre'] ?? true;

        $demande = app(\App\Services\MaintenanceService::class)->depuisHousekeeping(
            $room,
            $validated['notes'],
            $assignment,
            Auth::user(),
            $validated['specialty'] ?? null,
            $validated['priority'] ?? null,
            (bool) $bloquer
        );

        if ($room->status !== RoomStatus::MAINTENANCE) {
            $room->updateStatus(RoomStatus::MAINTENANCE, 'Transmis au service technique : ' . $validated['notes'], Auth::id());
        }

        $assignment->update([
            'status'      => 'blocked',
            'issue_type'  => HousekeepingAssignment::ISSUE_TECHNIQUE,
            'issue_notes' => $validated['notes'],
        ]);

        return back()->with('success', "Demande d'intervention {$demande->reference} transmise au service technique (la chambre a été mise en maintenance).");
    }

    public function markCleaning(Request $request, Room $room)
    {
        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Cette chambre n\'a pas encore ete affectee a une equipe.']);
        }

        $this->ensureCanClean($room);

        // Recouche : le client garde sa chambre, son statut ne bouge pas.
        if ($assignment->isRecouche()) {
            if ($room->status !== RoomStatus::OCCUPIED) {
                return back()->withErrors(['status' => "La chambre {$room->number} n'est plus occupée : la recouche n'a plus lieu d'être."]);
            }

            DB::transaction(function () use ($room, $assignment) {
                $assignment->update([
                    'status' => 'in_progress',
                    'started_at' => $assignment->started_at ?? now(),
                    'started_by' => $assignment->started_by ?? Auth::id(),
                ]);
                HousekeepingLog::noter($room, HousekeepingLog::STAYOVER_STARTED, null, $assignment, $this->sejourEnCours($room)?->id);
            });

            return back()->with('success', "Recouche de la chambre {$room->number} commencée.");
        }

        if (!$room->status->canTransitionTo(RoomStatus::CLEANING)) {
            return back()->withErrors(['status' => 'Cette chambre ne peut pas etre mise en nettoyage.']);
        }

        DB::transaction(function () use ($room, $assignment) {
            $room->updateStatus(RoomStatus::CLEANING, 'Nettoyage demarre', Auth::id());

            $assignment->update([
                'status' => 'in_progress',
                'started_at' => $assignment->started_at ?? now(),
                'started_by' => $assignment->started_by ?? Auth::id(),
            ]);
            HousekeepingLog::noter($room, HousekeepingLog::STARTED, null, $assignment);
        });

        return back()->with('success', "Chambre {$room->number} en cours de nettoyage.");
    }

    /** Recouche terminée : la chambre reste occupée, l'affectation se clôt. */
    public function markStayoverDone(Request $request, Room $room)
    {
        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment || !$assignment->isRecouche()) {
            return back()->withErrors(['status' => "Aucune recouche n'est en cours pour la chambre {$room->number}."]);
        }

        $this->ensureCanClean($room);

        if ($room->status !== RoomStatus::OCCUPIED) {
            return back()->withErrors(['status' => "La chambre {$room->number} n'est plus occupée : la recouche n'a plus lieu d'être."]);
        }

        // Le relevé du mini-bar fait partie de la recouche : ce que le client
        // a consommé part à son folio.
        if ($room->aUnMiniBar() && !\App\Models\MinibarCheck::query()->where('housekeeping_assignment_id', $assignment->id)->exists()) {
            return back()->withErrors(['status' => "Faites d'abord le relevé du mini-bar de la chambre {$room->number}."]);
        }

        DB::transaction(function () use ($room, $assignment) {
            $assignment->update([
                'status' => 'completed',
                'started_at' => $assignment->started_at ?? now(),
                'started_by' => $assignment->started_by ?? Auth::id(),
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);
            HousekeepingLog::noter($room, HousekeepingLog::STAYOVER_DONE, null, $assignment, $this->sejourEnCours($room)?->id);
        });

        return back()->with('success', "Recouche de la chambre {$room->number} terminée.");
    }

    public function markReady(Request $request, Room $room)
    {
        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Aucune affectation active trouvee pour cette chambre.']);
        }

        $this->ensureCanClean($room);

        if ($assignment->isRecouche()) {
            return back()->withErrors(['status' => "La chambre {$room->number} est en recouche : terminez la recouche."]);
        }

        // Le client est parti sans relevé de départ : sa consommation n'est
        // connue que par ce relevé, à faire avant que la chambre change de main.
        if (\App\Services\MinibarService::releveDuAuMenage($room)) {
            return back()->withErrors(['status' => "Faites d'abord le relevé du mini-bar de la chambre {$room->number} : le client est parti sans relevé."]);
        }

        if (!$room->status->canTransitionTo(RoomStatus::CLEAN)) {
            return back()->withErrors(['status' => 'Cette chambre ne peut pas etre marquee propre.']);
        }

        DB::transaction(function () use ($room, $assignment) {
            $room->updateStatus(RoomStatus::CLEAN, 'Nettoyage termine', Auth::id());

            $assignment->update([
                'status' => 'completed',
                'started_at' => $assignment->started_at ?? now(),
                'started_by' => $assignment->started_by ?? Auth::id(),
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);
            HousekeepingLog::noter($room, HousekeepingLog::CLEANED, null, $assignment);
        });

        // Le contrôle conditionne la remise à la vente : on prévient ceux qui
        // peuvent valider — le chef d'équipe de la chambre et les gouvernantes,
        // le responsable des étages avec elles. La réception, elle, sait que la
        // chambre sera bientôt vendable.
        $team = $this->roomTeam($room);
        $this->notifier->send(
            collect([$team?->leader])->filter(fn ($leader) => $leader->id !== Auth::id()),
            new HousekeepingRoomToInspect($room, $team?->name)
        );
        $this->notifier->toRoles(
            ['housekeeping_supervisor'],
            new HousekeepingRoomToInspect($room, $team?->name),
            Auth::id()
        );
        $this->notifier->toRoles(
            ['reception'],
            new HousekeepingRoomCleaned($room, Auth::user()?->name),
            Auth::id()
        );

        return back()->with('success', "Chambre {$room->number} marquée nettoyée, à contrôler.");
    }

    /** Contrôle qualité : Nettoyée → Contrôlée. Chef de service ou chef d'équipe. */
    public function markInspected(Request $request, Room $room)
    {
        $this->ensureCanValidate($room);

        if (!$room->status->canTransitionTo(RoomStatus::INSPECTED)) {
            return back()->withErrors(['status' => 'Cette chambre doit être nettoyée avant contrôle.']);
        }

        // Le prochain client trouve un mini-bar complet. Si le dépôt manque
        // d'un article, la gouvernante valide quand même en le motivant.
        $motif = trim((string) $request->input('motif_minibar'));
        if ($room->aUnMiniBar() && !$room->miniBarComplet() && $motif === '') {
            return back()->withErrors(['status' => "Rechargez d'abord le mini-bar de la chambre {$room->number}, ou validez en indiquant pourquoi il reste incomplet."]);
        }

        DB::transaction(function () use ($room, $motif) {
            $room->updateStatus(RoomStatus::INSPECTED, 'Contrôle qualité validé', Auth::id());

            $assignment = $room->latestHousekeepingAssignment;
            $assignment?->update(['inspected_by' => Auth::id(), 'inspected_at' => now()]);
            HousekeepingLog::noter($room, HousekeepingLog::INSPECTED,
                $motif !== '' ? 'Mini-bar incomplet : ' . $motif : null, $assignment);
        });

        return back()->with('success', "Chambre {$room->number} contrôlée.");
    }

    /** Libération : Contrôlée → Disponible. Chef de service ou chef d'équipe. */
    public function markAvailable(Request $request, Room $room)
    {
        $this->ensureCanValidate($room);

        if (!$room->status->canTransitionTo(RoomStatus::AVAILABLE)) {
            return back()->withErrors(['status' => 'Seule une chambre contrôlée peut être remise à disposition.']);
        }

        DB::transaction(function () use ($room) {
            $room->updateStatus(RoomStatus::AVAILABLE, 'Chambre remise à disposition par le housekeeping', Auth::id());

            $assignment = $room->latestHousekeepingAssignment;
            $assignment?->update(['released_by' => Auth::id(), 'released_at' => now()]);
            HousekeepingLog::noter($room, HousekeepingLog::RELEASED, null, $assignment);
        });

        return back()->with('success', "Chambre {$room->number} disponible.");
    }

    /** Contrôle refusé : Nettoyée → À nettoyer, et l'affectation repart. */
    public function rejectCleaning(Request $request, Room $room)
    {
        $this->ensureCanValidate($room);

        if ($room->status !== RoomStatus::CLEAN) {
            return back()->withErrors(['status' => 'Seule une chambre nettoyée peut être refusée au contrôle.']);
        }

        $reason = trim((string) $request->input('reason')) ?: 'Contrôle non conforme';

        DB::transaction(function () use ($room, $reason) {
            // updateStatus() ne bloque pas cette transition inverse (rare) : on
            // repasse volontairement la chambre à nettoyer.
            $room->updateStatus(RoomStatus::DIRTY, $reason, Auth::id());

            // On rouvre la dernière affectation pour que l'équipe la reprenne.
            // Le journal garde la trace du premier passage.
            $assignment = $room->latestHousekeepingAssignment;
            HousekeepingLog::noter($room, HousekeepingLog::REJECTED, $reason, $assignment);
            $assignment?->update([
                'status'       => 'pending',
                'started_at'   => null,
                'started_by'   => null,
                'completed_at' => null,
                'completed_by' => null,
                'notes'        => $reason,
            ]);
        });

        return back()->with('success', "Chambre {$room->number} renvoyée au nettoyage.");
    }

    /**
     * Suivi des chambres : qui a nettoyé, contrôlé, remis en vente ou rechargé
     * quelle chambre, et à quelle heure. Le responsable des étages et les
     * gouvernantes y lisent le travail de chacun.
     */
    public function journal(Request $request)
    {
        $filtres = $request->validate([
            'du'      => ['nullable', 'date'],
            'au'      => ['nullable', 'date'],
            'room_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $du = \Illuminate\Support\Carbon::parse($filtres['du'] ?? today())->startOfDay();
        $au = \Illuminate\Support\Carbon::parse($filtres['au'] ?? $du)->endOfDay();
        if ($au->lt($du)) {
            [$du, $au] = [$au->copy()->startOfDay(), $du->copy()->endOfDay()];
        }
        // Un mois au plus : au-delà, la page cesse d'être lisible.
        if ($du->diffInDays($au) > 31) {
            $au = $du->copy()->addDays(30)->endOfDay();
        }

        $personne = $filtres['user_id'] ?? null;

        // Une ligne par passage dans une chambre : ménage de départ ou recouche.
        $passages = HousekeepingAssignment::query()
            ->with(['room.roomType', 'team', 'startedBy', 'completedBy', 'inspectedBy', 'releasedBy', 'assignedBy'])
            ->where(fn ($q) => $q
                ->whereBetween('assigned_at', [$du, $au])
                ->orWhereBetween('started_at', [$du, $au])
                ->orWhereBetween('completed_at', [$du, $au])
                ->orWhereBetween('inspected_at', [$du, $au])
                ->orWhereBetween('released_at', [$du, $au]))
            ->when($filtres['room_id'] ?? null, fn ($q, $id) => $q->where('room_id', $id))
            ->when($filtres['team_id'] ?? null, fn ($q, $id) => $q->where('housekeeping_team_id', $id))
            ->when($personne, fn ($q, $id) => $q->where(fn ($w) => $w
                ->where('started_by', $id)->orWhere('completed_by', $id)
                ->orWhere('inspected_by', $id)->orWhere('released_by', $id)->orWhere('assigned_by', $id)))
            ->orderByRaw('COALESCE(completed_at, started_at, assigned_at) DESC')
            ->get();

        $journal = HousekeepingLog::query()
            ->with(['room', 'user', 'team'])
            ->whereBetween('occurred_at', [$du, $au])
            ->when($filtres['room_id'] ?? null, fn ($q, $id) => $q->where('room_id', $id))
            ->when($filtres['team_id'] ?? null, fn ($q, $id) => $q->where('housekeeping_team_id', $id))
            ->when($personne, fn ($q, $id) => $q->where('user_id', $id))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        // Le travail de chacun sur la période, tiré du journal.
        $parPersonne = $journal
            ->filter(fn (HousekeepingLog $l) => $l->user_id !== null)
            ->groupBy('user_id')
            ->map(fn (Collection $gestes) => [
                'user'       => $gestes->first()->user,
                'nettoyees'  => $gestes->where('action', HousekeepingLog::CLEANED)->count(),
                'recouches'  => $gestes->where('action', HousekeepingLog::STAYOVER_DONE)->count(),
                'controlees' => $gestes->where('action', HousekeepingLog::INSPECTED)->count(),
                'refusees'   => $gestes->where('action', HousekeepingLog::REJECTED)->count(),
                'releves'    => $gestes->where('action', HousekeepingLog::MINIBAR_CHECKED)->count(),
                'recharges'  => $gestes->where('action', HousekeepingLog::MINIBAR_RESTOCKED)->count(),
                'dernier'    => $gestes->max('occurred_at'),
            ])
            ->sortBy(fn ($ligne) => $ligne['user']?->name)
            ->values();

        return view('housekeeping.journal', [
            'du'          => $du,
            'au'          => $au,
            'filtres'     => $filtres,
            'passages'    => $passages,
            'journal'     => $journal,
            'parPersonne' => $parPersonne,
            'rooms'       => Room::query()->orderBy('number')->get(['id', 'number']),
            'teams'       => HousekeepingTeam::query()->orderBy('name')->get(['id', 'name']),
            'personnes'   => User::query()->havingRole(self::PERSONNEL)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Encadrement : il pilote tout le housekeeping. L'administrateur en a la
     * vue d'ensemble ; ses gestes de terrain restent refusés par leurs droits,
     * qu'il ne détient pas.
     */
    private function isEncadrement(): bool
    {
        $user = Auth::user();

        return $user->isAdmin() || $user->exerce(self::ENCADREMENT);
    }

    /** Séjour en cours dans la chambre, s'il y en a un. */
    private function sejourEnCours(Room $room): ?\App\Models\Booking
    {
        return $room->bookings()
            ->where('status', \App\Enums\BookingStatus::CHECKED_IN)
            ->latest('actual_check_in')
            ->first();
    }

    /** Équipe responsable de la chambre (affectation active, sinon la dernière). */
    private function roomTeam(Room $room): ?HousekeepingTeam
    {
        return $room->activeHousekeepingAssignment?->team
            ?? $room->latestHousekeepingAssignment?->team;
    }

    /** Actions de terrain (nettoyage) : encadrement ou membre de l'équipe. */
    private function ensureCanClean(Room $room): void
    {
        if ($room->status === RoomStatus::MAINTENANCE) {
            abort(403, "Cette chambre est actuellement en cours de maintenance technique.");
        }

        if ($this->isEncadrement()) {
            return;
        }

        $team = $this->roomTeam($room);
        $isMember = $team && $team->members()->where('users.id', Auth::id())->exists();

        if (!$isMember) {
            abort(403, "Cette chambre est confiée à une autre équipe.");
        }
    }

    /** Actions de validation (contrôle, libération) : encadrement ou chef d'équipe. */
    private function ensureCanValidate(Room $room): void
    {
        if ($this->isEncadrement()) {
            return;
        }

        $team = $this->roomTeam($room);
        if (!$team || $team->leader_id !== Auth::id()) {
            abort(403, "Seuls le chef d'équipe, la gouvernante ou le responsable des étages peuvent valider cette chambre.");
        }
    }

    private function buildPriorityRooms(Collection $dirtyRooms): Collection
    {
        return $dirtyRooms->map(function (Room $room) {
            $score = 20;
            $label = 'Normale';
            $reason = 'Cycle menage standard';
            $nextCheckIn = $room->bookings->first()?->check_in;

            if ($room->activeHousekeepingAssignment && $room->activeHousekeepingAssignment->status === 'blocked') {
                $score = 100;
                $label = 'Critique';
                $reason = 'Probleme signale a traiter en priorite';
            } elseif ($nextCheckIn && $nextCheckIn->isToday()) {
                $score = 90;
                $label = 'Haute';
                $reason = "Arrivee client aujourd'hui";
            } elseif ($nextCheckIn && $nextCheckIn->isTomorrow()) {
                $score = 70;
                $label = 'Elevee';
                $reason = 'Arrivee client demain';
            } elseif ($room->floor <= 1) {
                $score = 40;
                $label = 'Moyenne';
                $reason = 'Zone passage frequent';
            }

            return [
                'room' => $room,
                'priority_score' => $score,
                'priority_label' => $label,
                'priority_reason' => $reason,
                'next_check_in' => $nextCheckIn,
            ];
        })->sortByDesc('priority_score')->values();
    }
}
