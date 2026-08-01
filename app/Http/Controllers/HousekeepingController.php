<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingTeam;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\HousekeepingIssueReported;
use App\Notifications\HousekeepingRoomsAssigned;
use App\Notifications\HousekeepingRoomToInspect;
use App\Services\Notifier;
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

    /** Rôles pilotant tout le service (vue chef complète). */
    private const CHIEF_ROLES = ['manager', 'housekeeping_leader'];

    /** Statuts couverts par le cycle de nettoyage. */
    private const CYCLE_STATUSES = [
        RoomStatus::DIRTY,
        RoomStatus::CLEANING,
        RoomStatus::CLEAN,
        RoomStatus::INSPECTED,
    ];

    public function index()
    {
        // Le chef de service voit tout le pilotage ; un agent de terrain ne voit
        // que son équipe et ses chambres — deux écrans distincts.
        return $this->isChief()
            ? $this->chiefDashboard()
            : $this->memberDashboard();
    }

    /** Tableau de bord du chef de service : pipeline, affectation, équipes. */
    private function chiefDashboard()
    {
        $teams = HousekeepingTeam::with(['leader', 'members', 'activeAssignments.room'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $staff = User::query()
            ->whereIn('role', ['housekeeping_leader', 'housekeeping_staff', 'housekeeping'])
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

        $blockedAssignments = HousekeepingAssignment::with(['room.roomType', 'team'])
            ->where('status', 'blocked')
            ->latest('reported_at')
            ->get();

        $completedToday = HousekeepingAssignment::with(['room.roomType', 'team'])
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->latest('completed_at')
            ->get();

        $pipeline = Room::with(['roomType', 'activeHousekeepingAssignment.team'])
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
            : Room::with(['roomType', 'latestHousekeepingAssignment.team'])
                ->whereIn('id', HousekeepingAssignment::whereIn('housekeeping_team_id', $myTeamIds)
                    ->pluck('room_id')->unique())
                ->whereIn('status', self::CYCLE_STATUSES)
                ->orderByRaw("CASE status
                    WHEN 'dirty' THEN 1 WHEN 'cleaning' THEN 2
                    WHEN 'clean' THEN 3 WHEN 'inspected' THEN 4 ELSE 5 END")
                ->orderBy('number')
                ->get();

        $stats = [
            'dirty_rooms'  => $dirtyRooms->count(),
            'cleaning'     => ($pipeline['cleaning'] ?? collect())->count(),
            'to_inspect'   => ($pipeline['clean'] ?? collect())->count(),
            'inspected'    => ($pipeline['inspected'] ?? collect())->count(),
            'teams'        => $teams->count(),
            'blocked'      => $blockedAssignments->count(),
            'completed_today' => $completedToday->count(),
        ];

        return view('housekeeping.index', [
            'isChief'            => true,
            'teams'              => $teams,
            'staff'              => $staff,
            'dirtyRooms'         => $dirtyRooms,
            'priorityRooms'      => $priorityRooms,
            'blockedAssignments' => $blockedAssignments,
            'completedToday'     => $completedToday,
            'pipeline'           => $pipeline,
            'stats'              => $stats,
            'myRooms'            => $myRooms,
        ]);
    }

    /** Écran simple d'un agent de terrain : son équipe et ses chambres. */
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

        // Chambres dont mes équipes ont la charge, encore dans le cycle.
        $roomIds = HousekeepingAssignment::whereIn('housekeeping_team_id', $myTeamIds)
            ->pluck('room_id')->unique();

        $myRooms = Room::with(['roomType', 'latestHousekeepingAssignment.team'])
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
            'isChief'    => false,
            'myTeams'    => $myTeams,
            'ledTeamIds' => $ledTeamIds,
            'myRooms'    => $myRooms,
            'presence'   => $presence,
        ]);
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
            ?? Tenant::where('slug', 'villa-boutanga')->value('id');

        $memberIds = collect($validated['member_ids'])->map(fn ($id) => (int) $id);

        if (!empty($validated['leader_id']) && !$memberIds->contains((int) $validated['leader_id'])) {
            $memberIds->push((int) $validated['leader_id']);
        }

        $allowedStaffIds = User::query()
            
            ->whereIn('role', ['housekeeping_leader', 'housekeeping_staff', 'housekeeping'])
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

        if ($rooms->contains(fn ($room) => $room->status !== RoomStatus::DIRTY)) {
            return back()->withErrors([
                'assignment' => 'Seules les chambres à nettoyer peuvent être affectées à une équipe.',
            ]);
        }

        DB::transaction(function () use ($validated, $team, $rooms) {
            foreach ($rooms as $room) {
                $existing = $room->housekeepingAssignments()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'housekeeping_team_id' => $team->id,
                        'assigned_by' => Auth::id(),
                        'assigned_at' => now(),
                        'notes' => $validated['notes'] ?? $existing->notes,
                        'status' => 'pending',
                        'started_at' => null,
                        'completed_at' => null,
                    ]);

                    continue;
                }

                HousekeepingAssignment::create([
                    'housekeeping_team_id' => $team->id,
                    'room_id' => $room->id,
                    'assigned_by' => Auth::id(),
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                    'assigned_at' => now(),
                ]);
            }
        });

        // L'équipe est prévenue de sa feuille de route (hors auteur de l'action).
        $this->notifier->send(
            $team->members()->where('users.id', '!=', Auth::id())->get(),
            new HousekeepingRoomsAssigned($team, $rooms->pluck('number')->all())
        );

        return redirect()->route('housekeeping.index')->with('success', 'Chambres affectées à l\'équipe.');
    }

    public function reportIssue(Request $request, Room $room)
    {
        $validated = $request->validate([
            'issue_notes' => ['required', 'string', 'max:1000'],
            'mark_as_maintenance' => ['nullable', 'boolean'],
        ]);

        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Aucune affectation active trouvee pour cette chambre.']);
        }

        $this->ensureCanClean($room);

        DB::transaction(function () use ($room, $assignment, $validated) {
            $assignment->update([
                'status' => 'blocked',
                'issue_notes' => $validated['issue_notes'],
                'reported_by' => Auth::id(),
                'reported_at' => now(),
                'started_at' => $assignment->started_at ?? now(),
            ]);

            if ($validated['mark_as_maintenance'] ?? false) {
                $room->updateStatus(RoomStatus::MAINTENANCE, 'Probleme signale par housekeeping', Auth::id());
            }
        });

        // La chambre est immobilisée : le chef de service doit arbitrer vite.
        $this->notifier->toRoles(
            self::CHIEF_ROLES,
            new HousekeepingIssueReported($room, $validated['issue_notes'], Auth::user()?->name),
            Auth::id()
        );

        return redirect()->route('housekeeping.index')->with('success', 'Le problème a été signalé au chef de service.');
    }

    public function markCleaning(Request $request, Room $room)
    {
        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Cette chambre n\'a pas encore ete affectee a une equipe.']);
        }

        $this->ensureCanClean($room);

        if (!$room->status->canTransitionTo(RoomStatus::CLEANING)) {
            return back()->withErrors(['status' => 'Cette chambre ne peut pas etre mise en nettoyage.']);
        }

        DB::transaction(function () use ($room, $assignment) {
            $room->updateStatus(RoomStatus::CLEANING, 'Nettoyage demarre', Auth::id());

            $assignment->update([
                'status' => 'in_progress',
                'started_at' => $assignment->started_at ?? now(),
            ]);
        });

        return back()->with('success', "Chambre {$room->number} en cours de nettoyage.");
    }

    public function markReady(Request $request, Room $room)
    {
        $assignment = $room->activeHousekeepingAssignment;

        if (!$assignment) {
            return back()->withErrors(['status' => 'Aucune affectation active trouvee pour cette chambre.']);
        }

        $this->ensureCanClean($room);

        if (!$room->status->canTransitionTo(RoomStatus::CLEAN)) {
            return back()->withErrors(['status' => 'Cette chambre ne peut pas etre marquee propre.']);
        }

        DB::transaction(function () use ($room, $assignment) {
            $room->updateStatus(RoomStatus::CLEAN, 'Nettoyage termine', Auth::id());

            $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        // Le contrôle conditionne la remise à la vente : on prévient ceux qui
        // peuvent valider — chef d'équipe de la chambre et chef de service.
        $team = $this->roomTeam($room);
        $this->notifier->send(
            collect([$team?->leader])->filter(),
            new HousekeepingRoomToInspect($room, $team?->name)
        );
        $this->notifier->toRoles(
            self::CHIEF_ROLES,
            new HousekeepingRoomToInspect($room, $team?->name),
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

        $room->updateStatus(RoomStatus::INSPECTED, 'Contrôle qualité validé', Auth::id());

        return back()->with('success', "Chambre {$room->number} contrôlée.");
    }

    /** Libération : Contrôlée → Disponible. Chef de service ou chef d'équipe. */
    public function markAvailable(Request $request, Room $room)
    {
        $this->ensureCanValidate($room);

        if (!$room->status->canTransitionTo(RoomStatus::AVAILABLE)) {
            return back()->withErrors(['status' => 'Seule une chambre contrôlée peut être remise à disposition.']);
        }

        $room->updateStatus(RoomStatus::AVAILABLE, 'Chambre remise à disposition par le housekeeping', Auth::id());

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
            $room->latestHousekeepingAssignment?->update([
                'status'       => 'pending',
                'started_at'   => null,
                'completed_at' => null,
                'notes'        => $reason,
            ]);
        });

        return back()->with('success', "Chambre {$room->number} renvoyée au nettoyage.");
    }

    private function isChief(): bool
    {
        return Auth::user()->hasAnyRole(self::CHIEF_ROLES);
    }

    /** Équipe responsable de la chambre (affectation active, sinon la dernière). */
    private function roomTeam(Room $room): ?HousekeepingTeam
    {
        return $room->activeHousekeepingAssignment?->team
            ?? $room->latestHousekeepingAssignment?->team;
    }

    /** Actions de terrain (nettoyage) : chef de service ou membre de l'équipe. */
    private function ensureCanClean(Room $room): void
    {
        if ($this->isChief()) {
            return;
        }

        $team = $this->roomTeam($room);
        $isMember = $team && $team->members()->where('users.id', Auth::id())->exists();

        if (!$isMember) {
            abort(403, "Cette chambre est confiée à une autre équipe.");
        }
    }

    /** Actions de validation (contrôle, libération) : chef de service ou chef d'équipe. */
    private function ensureCanValidate(Room $room): void
    {
        if ($this->isChief()) {
            return;
        }

        $team = $this->roomTeam($room);
        if (!$team || $team->leader_id !== Auth::id()) {
            abort(403, "Seul le chef d'équipe ou le chef de service peut valider cette chambre.");
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
