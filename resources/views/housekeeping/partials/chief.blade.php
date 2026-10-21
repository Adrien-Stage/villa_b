@php
    $priorityBadge = [
        'Critique' => 'bg-red-50 text-red-700 border-red-200',
        'Haute'    => 'bg-orange-50 text-orange-700 border-orange-200',
        'Elevee'   => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'Moyenne'  => 'bg-blue-50 text-blue-700 border-blue-200',
        'Normale'  => 'bg-secondary/10 text-primary/70 border-secondary/20',
    ];
    $activeRooms = $pipeline->flatten(1);

    // Mêmes codes couleur que l'écran des agents : le chef bascule d'un écran
    // à l'autre, les statuts doivent se lire pareil des deux côtés.
    $statusStyles = [
        'dirty'     => ['label' => 'À nettoyer',    'chip' => 'bg-red-100 text-red-800',        'card' => 'border-red-200 bg-red-50'],
        'cleaning'  => ['label' => 'En nettoyage',  'chip' => 'bg-yellow-100 text-yellow-900',  'card' => 'border-yellow-300 bg-yellow-50'],
        'clean'     => ['label' => 'À contrôler',   'chip' => 'bg-purple-100 text-purple-800',  'card' => 'border-purple-200 bg-purple-50'],
        'inspected' => ['label' => 'Contrôlée',     'chip' => 'bg-emerald-100 text-emerald-800','card' => 'border-emerald-200 bg-emerald-50'],
    ];

    // Préparation de la liste unifiée des chambres affectables (départs + recouches)
    $assignableRooms = collect();

    foreach ($dirtyRooms as $r) {
        $prio = $priorityRooms->firstWhere('room.id', $r->id);
        $activeAssignment = $r->activeHousekeepingAssignment;
        $assignableRooms->push([
            'id'              => $r->id,
            'number'          => $r->number,
            'floor'           => $r->floor,
            'room_type'       => $r->roomType?->name ?? 'Chambre standard',
            'kind'            => 'depart',
            'kind_label'      => 'Ménage de départ',
            'status_label'    => 'À nettoyer',
            'badge_class'     => 'bg-red-100 text-red-800 border-red-200',
            'priority_label'  => $prio['priority_label'] ?? 'Normale',
            'priority_reason' => $prio['priority_reason'] ?? null,
            'assigned_team'   => $activeAssignment?->team?->name,
            'is_assigned'     => (bool) $activeAssignment,
        ]);
    }

    foreach ($occupiedRooms as $r) {
        $activeAssignment = $r->activeHousekeepingAssignment;
        $derniere = $r->latestHousekeepingAssignment;
        $isDoneToday = $derniere?->isRecouche() && $derniere->status === 'completed' && $derniere->completed_at?->isToday();
        $assignableRooms->push([
            'id'              => $r->id,
            'number'          => $r->number,
            'floor'           => $r->floor,
            'room_type'       => $r->roomType?->name ?? 'Chambre standard',
            'kind'            => 'recouche',
            'kind_label'      => 'Recouche séjour',
            'status_label'    => 'Occupée',
            'badge_class'     => 'bg-blue-100 text-blue-800 border-blue-200',
            'priority_label'  => 'Normale',
            'priority_reason' => $isDoneToday ? 'Recouche déjà faite aujourd\'hui (' . $derniere->completed_at->format('H:i') . ')' : null,
            'assigned_team'   => $activeAssignment?->team?->name,
            'is_assigned'     => (bool) $activeAssignment,
        ]);
    }

    $assignableRooms = $assignableRooms->sortBy(fn($item) => [
        $item['floor'] ?? 0,
        $item['number']
    ])->values();

    $unassignedCount = $assignableRooms->where('is_assigned', false)->count();
    $distinctFloors = $assignableRooms->pluck('floor')->filter(fn($f) => $f !== null)->unique()->sort()->values();
    $peutCreerEquipe = auth()->check() && app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'housekeeping.teams.creer');
@endphp

{{-- ═══ Les chambres que le chef traite lui-même ═══ --}}
@if($myRooms->isNotEmpty())
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="font-heading font-semibold text-primary text-lg">Mes chambres</h2>
                <p class="text-xs text-primary/50">Chambres confiées aux équipes dont vous faites partie — vous pouvez les traiter vous-même.</p>
            </div>
            <span class="text-xs text-primary/40">{{ $myRooms->count() }} chambre{{ $myRooms->count() > 1 ? 's' : '' }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($myRooms as $room)
                {{-- Le chef de service contrôle et libère sans restriction. --}}
                @include('housekeeping.partials.room-card', [
                    'room'         => $room,
                    'statusStyles' => $statusStyles,
                    'canValidate'  => true,
                ])
            @endforeach
        </div>
    </div>
@endif

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex flex-col gap-2">
        <h1 class="font-heading text-2xl font-semibold text-primary">Housekeeping</h1>
        <p class="text-sm text-primary/50">Pilotage du nettoyage : priorisation, affectation centralisée des équipes et suivi du cycle des chambres.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @droit('housekeeping.minibar.voir')
            <a href="{{ route('housekeeping.minibar.index') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="wine" class="w-4 h-4"></i> Mini-bars
            </a>
        @enddroit
        @droit('housekeeping.journal.voir')
            <a href="{{ route('housekeeping.journal.index') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="list-checks" class="w-4 h-4"></i> Suivi des chambres
            </a>
        @enddroit
    </div>
</div>

{{-- Cartes statistiques globales --}}
<div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-4 mb-6">
    <x-stat-card label="À nettoyer" :value="$stats['dirty_rooms']" subtitle="départs à faire" color="red" />
    <x-stat-card label="En nettoyage" :value="$stats['cleaning']" subtitle="en cours" color="yellow" />
    <x-stat-card label="À contrôler" :value="$stats['to_inspect']" subtitle="nettoyées" color="purple" />
    <x-stat-card label="Contrôlées" :value="$stats['inspected']" subtitle="à libérer" color="emerald" />
    <x-stat-card label="Recouches" :value="$stats['stayovers']" subtitle="séjour en cours" color="blue" />
    <x-stat-card label="En maintenance" :value="$stats['maintenance'] ?? 0" subtitle="bloquées tech" color="orange" />
    <x-stat-card label="Équipes" :value="$stats['teams']" subtitle="actives" color="blue" />
    <x-stat-card label="Terminées" :value="$stats['completed_today']" subtitle="aujourd'hui" color="emerald" />
</div>

{{-- ═══ Système d'onglets Alpine.js ═══ --}}
<div x-data="{
    activeTab: (new URLSearchParams(window.location.search).get('tab')) || localStorage.getItem('hk_active_tab') || 'pipeline',
    affectFilter: 'all',
    floorFilter: 'all',
    searchRoom: '',
    selectedCount: 0,
    setTab(t, filter = null) {
        this.activeTab = t;
        localStorage.setItem('hk_active_tab', t);
        const u = new URL(window.location);
        u.searchParams.set('tab', t);
        window.history.replaceState({}, '', u);
        if (filter) {
            this.affectFilter = filter;
        }
        this.$nextTick(() => {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        });
    },
    updateSelectedCount() {
        this.selectedCount = document.querySelectorAll('.assign-room-checkbox:checked').length;
    },
    toggleAll(checked) {
        document.querySelectorAll('.assign-room-checkbox').forEach(cb => {
            const row = cb.closest('[data-assign-row]');
            if (row && row.style.display !== 'none') {
                cb.checked = checked;
            }
        });
        this.updateSelectedCount();
    },
    matchesFilters(kind, isAssigned, floor, number) {
        if (this.affectFilter === 'depart' && kind !== 'depart') return false;
        if (this.affectFilter === 'recouche' && kind !== 'recouche') return false;
        if (this.affectFilter === 'unassigned' && isAssigned) return false;
        if (this.floorFilter !== 'all' && String(floor) !== String(this.floorFilter)) return false;
        if (this.searchRoom && !String(number).toLowerCase().includes(this.searchRoom.toLowerCase())) return false;
        return true;
    }
}" x-init="updateSelectedCount()" class="space-y-6">

    {{-- Barre de navigation par onglets --}}
    <div class="border-b border-secondary/20">
        <nav class="flex space-x-2 sm:space-x-3 overflow-x-auto pb-1" aria-label="Sections du housekeeping">
            <button type="button"
                @click="setTab('pipeline')"
                :class="activeTab === 'pipeline' ? 'border-primary text-primary font-bold bg-primary/5' : 'border-transparent text-primary/60 hover:text-primary hover:border-secondary/30'"
                class="flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm whitespace-nowrap rounded-t-lg transition-all cursor-pointer">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
                <span>Pipeline du cycle</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                    {{ $activeRooms->count() }}
                </span>
            </button>

            <button type="button"
                @click="setTab('affectation')"
                :class="activeTab === 'affectation' ? 'border-primary text-primary font-bold bg-primary/5' : 'border-transparent text-primary/60 hover:text-primary hover:border-secondary/30'"
                class="flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm whitespace-nowrap rounded-t-lg transition-all cursor-pointer">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span>Centre d'affectation</span>
                @if($unassignedCount > 0)
                    <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        {{ $unassignedCount }} à affecter
                    </span>
                @else
                    <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        À jour
                    </span>
                @endif
            </button>

            <button type="button"
                @click="setTab('recouches')"
                :class="activeTab === 'recouches' ? 'border-primary text-primary font-bold bg-primary/5' : 'border-transparent text-primary/60 hover:text-primary hover:border-secondary/30'"
                class="flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm whitespace-nowrap rounded-t-lg transition-all cursor-pointer">
                <i data-lucide="bed-double" class="w-4 h-4"></i>
                <span>Recouches</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                    {{ $occupiedRooms->count() }}
                </span>
            </button>

            <button type="button"
                @click="setTab('equipes')"
                :class="activeTab === 'equipes' ? 'border-primary text-primary font-bold bg-primary/5' : 'border-transparent text-primary/60 hover:text-primary hover:border-secondary/30'"
                class="flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm whitespace-nowrap rounded-t-lg transition-all cursor-pointer">
                <i data-lucide="users" class="w-4 h-4"></i>
                <span>Équipes & Effectifs</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-secondary/10 text-primary/70">
                    {{ $teams->count() }}
                </span>
            </button>

            <button type="button"
                @click="setTab('problemes')"
                :class="activeTab === 'problemes' ? 'border-primary text-primary font-bold bg-primary/5' : 'border-transparent text-primary/60 hover:text-primary hover:border-secondary/30'"
                class="flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm whitespace-nowrap rounded-t-lg transition-all cursor-pointer">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                <span>Signalements & Maintenance</span>
                @php($totalIncidents = $blockedAssignments->count() + (isset($maintenanceRooms) ? $maintenanceRooms->count() : 0))
                @if($totalIncidents > 0)
                    <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        {{ $totalIncidents }}
                    </span>
                @endif
            </button>
        </nav>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ONGLET 1 : PIPELINE DU CYCLE (Suivi visuel des 4 statuts)
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'pipeline'" x-cloak class="space-y-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="font-heading font-semibold text-primary text-lg">Chambres en cours de traitement</h2>
                <p class="text-xs text-primary/50">Suivi visuel de l'avancement : du signalement de départ jusqu'à la remise en vente.</p>
            </div>
            <div class="flex items-center gap-3 text-xs flex-wrap">
                <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-red-100 border border-red-300"></span> À nettoyer</span>
                <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-yellow-100 border border-yellow-300"></span> En nettoyage</span>
                <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-purple-100 border border-purple-300"></span> À contrôler</span>
                <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-emerald-100 border border-emerald-300"></span> Contrôlée</span>
            </div>
        </div>

        @if($activeRooms->isEmpty())
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-primary/40 border border-dashed border-secondary/30">
                <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-emerald-500"></i>
                <p class="font-medium text-primary">Toutes les chambres de départ sont nettoyées et remises en vente.</p>
                <p class="text-xs text-primary/50 mt-1">Aucune chambre ne requiert d'attention particulière pour le moment.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @foreach($activeRooms as $room)
                    @php $status = $room->status->value; @endphp

                    @if($status === 'dirty')
                        {{-- À NETTOYER --}}
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-red-900 text-lg">Chambre {{ $room->number }}</h3>
                                        <p class="text-xs text-red-700 font-medium">{{ $room->roomType->name }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-200 text-red-800 uppercase tracking-widest">À nettoyer</span>
                                </div>
                                @php $prioInfo = $priorityRooms->firstWhere('room.id', $room->id); @endphp
                                @if($prioInfo)
                                    <div class="mb-4 text-xs text-red-800/80 bg-red-100/50 p-2 rounded-lg">
                                        <span class="font-semibold">{{ $prioInfo['priority_label'] }} :</span> {{ $prioInfo['priority_reason'] }}
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 pt-3 border-t border-red-200/50">
                                @if($room->activeHousekeepingAssignment)
                                    <div class="px-3 py-2 bg-white/70 rounded-lg border border-red-200 text-xs text-red-800 mb-2 flex items-center justify-between">
                                        <span>Assignée à :</span>
                                        <span class="font-bold">{{ $room->activeHousekeepingAssignment->team?->name ?? 'Équipe non assignée' }}</span>
                                    </div>
                                    <form method="POST" action="{{ route('housekeeping.clean', $room) }}">
                                        @csrf
                                        <button type="submit" class="w-full py-2 bg-yellow-600 text-white rounded-lg text-xs font-semibold hover:bg-yellow-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                            <i data-lucide="play" class="w-4 h-4"></i> Commencer le nettoyage
                                        </button>
                                    </form>
                                @else
                                    <div class="px-3 py-2 bg-white/70 rounded-lg border border-red-200 text-xs text-red-800 mb-2 flex items-center justify-between">
                                        <span class="italic text-primary/60">Non encore assignée</span>
                                        <button type="button" @click="setTab('affectation', 'depart')"
                                            class="px-2.5 py-1 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 transition flex items-center gap-1 cursor-pointer">
                                            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Affecter
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                    @elseif($status === 'cleaning')
                        {{-- EN NETTOYAGE --}}
                        <div class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-yellow-900 text-lg">Chambre {{ $room->number }}</h3>
                                        <p class="text-xs text-yellow-800 font-medium">{{ $room->roomType->name }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-yellow-200 text-yellow-900 uppercase tracking-widest flex items-center gap-1">
                                        <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> En cours
                                    </span>
                                </div>
                                @if($room->activeHousekeepingAssignment)
                                    @php $enCours = $room->activeHousekeepingAssignment; @endphp
                                    <div class="mb-4 text-xs text-yellow-900/80">
                                        En charge : <span class="font-semibold px-2 py-0.5 bg-yellow-200/50 rounded-md">{{ $enCours->team?->name ?? 'Équipe non assignée' }}</span>
                                        @if($enCours->started_at)
                                            <span class="block mt-1">Commencée à {{ $enCours->started_at->format('H:i') }}@if($enCours->startedBy) par {{ $enCours->startedBy->name }}@endif</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 pt-3 border-t border-yellow-200 flex gap-2">
                                @if(\App\Services\MinibarService::releveDuAuMenage($room))
                                    <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}"
                                       class="flex-1 py-2 bg-amber-600 text-white rounded-lg text-xs font-semibold hover:bg-amber-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                        <i data-lucide="wine" class="w-4 h-4"></i> Relevé du mini-bar
                                    </a>
                                @else
                                <form method="POST" action="{{ route('housekeeping.ready', $room) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                        <i data-lucide="check" class="w-4 h-4"></i> Marquer nettoyée
                                    </button>
                                </form>
                                @endif
                                <button type="button"
                                        onclick="window.openHousekeepingIssueModal({{ $room->id }}, '{{ addslashes($room->number) }}', '{{ route('housekeeping.issue', $room) }}')"
                                        @click="$dispatch('open-issue-modal', { roomId: {{ $room->id }}, roomNumber: '{{ $room->number }}', actionUrl: '{{ route('housekeeping.issue', $room) }}' })"
                                        class="px-3 py-2 bg-white border border-yellow-300 text-yellow-800 rounded-lg text-xs hover:bg-yellow-100 transition cursor-pointer"
                                        title="Signaler un problème">
                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                    @elseif($status === 'clean')
                        {{-- À CONTRÔLER --}}
                        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-purple-900 text-lg">Chambre {{ $room->number }}</h3>
                                        <p class="text-xs text-purple-700 font-medium">{{ $room->roomType->name }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-200 text-purple-800 uppercase tracking-widest">À contrôler</span>
                                </div>
                                @php
                                    $fait = $room->latestHousekeepingAssignment;
                                    $quand = trim(($fait?->completed_at ? ' à ' . $fait->completed_at->format('H:i') : '')
                                        . ($fait?->completedBy ? ' par ' . $fait->completedBy->name : ''));
                                @endphp
                                <div class="mb-4 text-xs text-purple-800/80">
                                    Nettoyage terminé{{ $quand !== '' ? ' ' . $quand : '' }}, en attente du visa de contrôle.
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-purple-200 flex gap-2">
                                @if($room->aUnMiniBar() && !$room->miniBarComplet())
                                    <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}"
                                       class="flex-1 py-2 bg-amber-600 text-white rounded-lg text-xs font-semibold hover:bg-amber-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                        <i data-lucide="package-plus" class="w-4 h-4"></i> Recharger le mini-bar
                                    </a>
                                @else
                                <form method="POST" action="{{ route('housekeeping.inspect', $room) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i> Valider (conforme)
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('housekeeping.reject', $room) }}" class="w-10">
                                    @csrf
                                    <input type="hidden" name="reason" value="Contrôle non conforme">
                                    <button type="submit" class="w-full h-full bg-white border border-purple-200 text-red-600 rounded-lg hover:bg-red-50 hover:border-red-200 transition flex items-center justify-center cursor-pointer" title="Refuser (renvoyer au nettoyage)">
                                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>

                    @elseif($status === 'inspected')
                        {{-- CONTRÔLÉE : prête à être remise à la vente --}}
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-emerald-900 text-lg">Chambre {{ $room->number }}</h3>
                                        <p class="text-xs text-emerald-700 font-medium">{{ $room->roomType->name }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase tracking-widest">Contrôlée</span>
                                </div>
                                <p class="text-xs text-emerald-800/80 mb-4">
                                    Chambre contrôlée conforme par la gouvernante, prête pour la réception.
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-emerald-200">
                                <form method="POST" action="{{ route('housekeeping.available', $room) }}">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition flex items-center justify-center gap-1.5 shadow-xs">
                                        <i data-lucide="door-open" class="w-4 h-4"></i> Rendre disponible
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ONGLET 2 : CENTRE D'AFFECTATION UNIQUE (Départs & Recouches)
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'affectation'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-secondary/20 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-secondary/15">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-lg flex items-center gap-2">
                        <i data-lucide="user-check" class="w-5 h-5 text-primary"></i>
                        Centre d'affectation des chambres
                    </h2>
                    <p class="text-xs text-primary/60 mt-0.5">
                        Sélectionnez les chambres à traiter (ménages de départ ou recouches de séjour) et confiez-les à une équipe en une seule action.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full bg-red-50 text-red-700 border border-red-200 font-medium">
                        {{ $dirtyRooms->count() }} départ{{ $dirtyRooms->count() > 1 ? 's' : '' }}
                    </span>
                    <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-medium">
                        {{ $occupiedRooms->count() }} recouche{{ $occupiedRooms->count() > 1 ? 's' : '' }}
                    </span>
                </div>
            </div>

            @if($assignableRooms->isEmpty())
                <div class="rounded-xl border border-dashed border-secondary/30 px-6 py-12 text-sm text-primary/40 text-center">
                    <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-emerald-500"></i>
                    <p class="font-medium text-primary">Toutes les chambres sont propres ou déjà libérées.</p>
                    <p class="text-xs text-primary/50 mt-1">Aucune chambre à nettoyer ni recouche en attente actuellement.</p>
                </div>
            @elseif($teams->isEmpty())
                <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50/50 px-6 py-10 text-sm text-amber-800 text-center">
                    <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2 text-amber-600"></i>
                    <p class="font-semibold">Aucune équipe de nettoyage active.</p>
                    <p class="text-xs mt-1 text-amber-700">Vous devez créer au moins une équipe pour pouvoir lui confier des chambres.</p>
                    @droit('housekeeping.teams.creer')
                        <button type="button" @click="setTab('equipes')" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-surface-dark transition inline-flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="users" class="w-4 h-4"></i> Aller à la création des équipes
                        </button>
                    @enddroit
                </div>
            @else
                <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="space-y-6">
                    @csrf

                    {{-- Configuration de la cible d'affectation --}}
                    <div class="bg-gray-50/70 border border-secondary/20 rounded-xl p-4 sm:p-5">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-primary mb-1.5">
                                    Équipe destinataire <span class="text-red-500">*</span>
                                </label>
                                <select name="housekeeping_team_id" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary shadow-xs">
                                    <option value="">Choisir une équipe…</option>
                                    @foreach($teams as $team)
                                        <option value="{{ $team->id }}">
                                            {{ $team->name }}{{ $team->leader ? ' (Chef : ' . $team->leader->name . ')' : '' }} — {{ $team->activeAssignments->count() }} ch. en cours
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-primary mb-1.5">
                                    Consignes & instructions particulières (optionnel)
                                </label>
                                <input type="text" name="notes" placeholder="Ex: Priorité arrivée VIP 14h, draps changés, lit bébé..."
                                    class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary shadow-xs">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs font-bold uppercase tracking-wider bg-primary text-white hover:bg-surface-dark transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i data-lucide="send" class="w-4 h-4"></i>
                                    <span>Affecter</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Barre de filtres et d'actions rapides --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                            <span class="text-xs font-semibold text-primary/70 mr-1">Filtrer :</span>
                            <button type="button" @click="affectFilter = 'all'"
                                :class="affectFilter === 'all' ? 'bg-primary text-white font-semibold' : 'bg-gray-100 text-primary/70 hover:bg-gray-200'"
                                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                Toutes ({{ $assignableRooms->count() }})
                            </button>
                            <button type="button" @click="affectFilter = 'depart'"
                                :class="affectFilter === 'depart' ? 'bg-red-600 text-white font-semibold' : 'bg-red-50 text-red-700 hover:bg-red-100'"
                                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                Départs ({{ $dirtyRooms->count() }})
                            </button>
                            <button type="button" @click="affectFilter = 'recouche'"
                                :class="affectFilter === 'recouche' ? 'bg-blue-600 text-white font-semibold' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                Recouches ({{ $occupiedRooms->count() }})
                            </button>
                            <button type="button" @click="affectFilter = 'unassigned'"
                                :class="affectFilter === 'unassigned' ? 'bg-amber-600 text-white font-semibold' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'"
                                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                Non affectées ({{ $unassignedCount }})
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            @if($distinctFloors->count() > 1)
                                <select x-model="floorFilter" class="px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                                    <option value="all">Tous les étages</option>
                                    @foreach($distinctFloors as $fl)
                                        <option value="{{ $fl }}">Étage {{ $fl }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <input type="text" x-model="searchRoom" placeholder="N° chambre..."
                                class="px-3 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary w-28 focus:outline-none focus:border-secondary">

                            <button type="button" @click="toggleAll(true)" class="px-2.5 py-1.5 text-xs text-secondary hover:text-primary font-medium transition cursor-pointer">
                                Tout cocher
                            </button>
                            <span class="text-secondary/30">|</span>
                            <button type="button" @click="toggleAll(false)" class="px-2.5 py-1.5 text-xs text-primary/50 hover:text-primary transition cursor-pointer">
                                Décocher
                            </button>
                        </div>
                    </div>

                    {{-- Liste de sélection ergonomique des chambres --}}
                    <div class="overflow-hidden rounded-xl border border-secondary/20 shadow-xs">
                        <div class="max-h-96 overflow-y-auto divide-y divide-secondary/10">
                            @foreach($assignableRooms as $item)
                                <label data-assign-row
                                    x-show="matchesFilters('{{ $item['kind'] }}', {{ $item['is_assigned'] ? 'true' : 'false' }}, '{{ $item['floor'] ?? '' }}', '{{ $item['number'] }}')"
                                    class="flex items-center gap-3.5 px-4 py-3 text-xs text-primary hover:bg-accent/5 cursor-pointer transition select-none">
                                    <input type="checkbox" name="room_ids[]" value="{{ $item['id'] }}"
                                        @change="updateSelectedCount()"
                                        class="assign-room-checkbox dirty-room-checkbox rounded border-secondary/40 text-primary focus:ring-primary w-4 h-4">
                                    <div class="w-16">
                                        <span class="font-heading font-bold text-sm text-primary">Ch. {{ $item['number'] }}</span>
                                    </div>
                                    <div class="w-32 hidden sm:block text-primary/60 truncate">
                                        {{ $item['room_type'] }}
                                    </div>
                                    @if($item['floor'] !== null)
                                        <div class="w-16 hidden md:block text-primary/50 text-[11px]">
                                            Étage {{ $item['floor'] }}
                                        </div>
                                    @endif
                                    <div class="w-36">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $item['badge_class'] }}">
                                            {{ $item['kind_label'] }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        @if($item['priority_label'] && $item['priority_label'] !== 'Normale')
                                            <span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold {{ $priorityBadge[$item['priority_label']] ?? $priorityBadge['Normale'] }}">
                                                {{ $item['priority_label'] }}
                                            </span>
                                        @endif
                                        @if($item['priority_reason'])
                                            <span class="text-primary/50 text-[11px] ml-1.5 truncate">{{ $item['priority_reason'] }}</span>
                                        @endif
                                    </div>
                                    <div class="w-44 text-right">
                                        @if($item['is_assigned'])
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                                <i data-lucide="users" class="w-3 h-3"></i> {{ $item['assigned_team'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] text-gray-500 bg-gray-100">
                                                Non affectée
                                            </span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Barre de validation inférieure avec compteur dynamique --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-secondary/15">
                        <div class="text-xs text-primary/70">
                            <span x-text="selectedCount" class="font-bold text-primary text-sm">0</span> chambre(s) sélectionnée(s)
                        </div>
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors shadow-xs flex items-center gap-2 cursor-pointer">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                            <span>Confier les chambres sélectionnées à l'équipe</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ONGLET 3 : RECOUCHES (Suivi des chambres occupées)
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'recouches'" x-cloak class="space-y-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="font-heading font-semibold text-primary text-lg">Recouches</h2>
                <p class="text-xs text-primary/50">Chambres occupées : le client garde sa chambre, l'équipe la refait pendant le séjour.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-primary/50">{{ $occupiedRooms->count() }} chambre{{ $occupiedRooms->count() > 1 ? 's' : '' }} occupée{{ $occupiedRooms->count() > 1 ? 's' : '' }}</span>
                <button type="button" @click="setTab('affectation', 'recouche')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition cursor-pointer">
                    <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Affecter des recouches
                </button>
            </div>
        </div>

        @if($occupiedRooms->isEmpty())
            <div class="bg-white rounded-xl shadow-sm p-8 text-center text-sm text-primary/40 border border-dashed border-secondary/30">
                <i data-lucide="bed-double" class="w-8 h-8 mx-auto mb-2 text-primary/30"></i>
                <p>Aucune chambre occupée pour le moment.</p>
            </div>
        @else
            @php
                $aFaire = $occupiedRooms->filter(fn ($r) => $r->activeHousekeepingAssignment);
                $libres = $occupiedRooms->reject(fn ($r) => $r->activeHousekeepingAssignment);
            @endphp

            {{-- Recouches en cours ou affectées --}}
            @if($aFaire->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/60 mb-3">Recouches confiées aux équipes ({{ $aFaire->count() }})</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
                        @foreach($aFaire as $room)
                            @include('housekeeping.partials.stayover-card', ['room' => $room])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recouches en attente d'affectation --}}
            @if($libres->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-secondary/20 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-heading font-semibold text-primary text-sm">Chambres occupées en attente d'affectation</h3>
                            <p class="text-xs text-primary/50">Ces chambres sont occupées et n'ont pas encore été confiées à une équipe pour la recouche du jour.</p>
                        </div>
                        <button type="button" @click="setTab('affectation', 'recouche')" class="px-4 py-2 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-surface-dark transition flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="user-plus" class="w-4 h-4"></i> Affecter ces {{ $libres->count() }} recouches
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($libres as $room)
                            @php $derniere = $room->latestHousekeepingAssignment; @endphp
                            <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-secondary/20 bg-gray-50/70 text-xs text-primary">
                                <span class="font-bold text-sm">Ch. {{ $room->number }}</span>
                                <span class="text-primary/50">{{ $room->roomType->name }}</span>
                                @if($derniere?->isRecouche() && $derniere->status === 'completed' && $derniere->completed_at?->isToday())
                                    <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded text-[11px] font-medium border border-emerald-200">
                                        Faite à {{ $derniere->completed_at->format('H:i') }}@if($derniere->completedBy) par {{ $derniere->completedBy->name }}@endif
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ONGLET 4 : ÉQUIPES & EFFECTIFS
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'equipes'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Création d'équipe : le responsable des étages compose les équipes --}}
        @droit('housekeeping.teams.creer')
        <div class="bg-white rounded-xl shadow-sm p-5 border border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Créer une équipe</h2>
            <form method="POST" action="{{ route('housekeeping.teams.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-primary/50 mb-1.5">Nom</label>
                        <input type="text" id="create-team-name" name="name" required class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs text-primary/50 mb-1.5">Code</label>
                        <input type="text" id="create-team-code" name="code" placeholder="HK-1" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-primary/50 mb-1.5">Chef d'équipe</label>
                    <select name="leader_id" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                        <option value="">Aucun chef désigné</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-primary/50 mb-2">Membres</label>
                    <div class="max-h-40 overflow-y-auto rounded-lg border border-secondary/20 divide-y divide-secondary/10">
                        @forelse($staff as $member)
                            <label class="flex items-center gap-3 px-3 py-2 text-xs text-primary hover:bg-accent/5 cursor-pointer">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="rounded border-secondary/40 text-primary focus:ring-primary">
                                <span class="flex-1">{{ $member->name }}</span>
                            </label>
                        @empty
                            <div class="px-3 py-4 text-xs text-primary/40">Aucun agent housekeeping disponible.</div>
                        @endforelse
                    </div>
                </div>
                <textarea name="notes" rows="2" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary resize-none" placeholder="Zone, étage, spécialité…"></textarea>
                <button type="submit" class="w-full py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors cursor-pointer">Enregistrer l'équipe</button>
            </form>
        </div>
        @enddroit

        {{-- Équipes existantes --}}
        <div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden {{ $peutCreerEquipe ? 'xl:col-span-2' : 'xl:col-span-3' }}">
            <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm">Équipes en activité</h2>
                    <p class="text-xs text-primary/50">Équipes constituées et effectifs assignés.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-secondary/10 text-primary">{{ $teams->count() }} équipe{{ $teams->count() > 1 ? 's' : '' }}</span>
            </div>
            @if($teams->isEmpty())
                <div class="px-5 py-10 text-sm text-primary/40 text-center">Aucune équipe créée pour l'instant.</div>
            @else
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($teams as $team)
                        <div class="rounded-xl border border-secondary/20 p-4 bg-accent/10 flex flex-col justify-between">
                            <div class="mb-3">
                                <div class="flex flex-wrap items-start justify-between gap-2 mb-1.5">
                                    <h3 class="font-heading font-semibold text-primary text-base">{{ $team->name }}</h3>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-white border border-secondary/20 text-primary/80">
                                        {{ $team->activeAssignments->count() }} ch. en cours
                                    </span>
                                </div>
                                <p class="text-xs text-primary/60">
                                    Chef d'équipe : <span class="font-medium text-primary">{{ $team->leader?->name ?? 'Non défini' }}</span>
                                </p>
                            </div>
                            <div class="pt-2 border-t border-secondary/10">
                                <p class="text-[11px] text-primary/50 mb-1.5">Membres ({{ $team->members->count() }}) :</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($team->members as $member)
                                        <span class="px-2 py-0.5 rounded-full bg-white border border-secondary/20 text-[11px] text-primary/70">
                                            {{ $member->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-primary/40 italic">Aucun membre</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ONGLET 5 : PROBLÈMES & HISTORIQUE DU JOUR
         ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'problemes'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Chambres immobilisées en maintenance technique --}}
        @if(isset($maintenanceRooms) && $maintenanceRooms->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden lg:col-span-2">
                <div class="px-5 py-4 bg-red-50/70 border-b border-red-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-heading font-semibold text-red-950 text-sm flex items-center gap-2">
                            <i data-lucide="wrench" class="w-4 h-4 text-red-700"></i>
                            Chambres en maintenance technique ({{ $maintenanceRooms->count() }})
                        </h2>
                        <p class="text-xs text-red-900/60">Immobilisées pour intervention technique — elles redeviendront « À nettoyer » dès clôture des travaux.</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                        {{ $maintenanceRooms->count() }} en panne
                    </span>
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($maintenanceRooms as $room)
                        @php($techReq = \App\Services\MaintenanceService::actif() ? \App\Models\MaintenanceRequest::where('room_id', $room->id)->active()->latest('id')->first() : null)
                        <div class="p-3.5 rounded-xl border border-red-200 bg-red-50/30 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <div>
                                        <span class="font-bold text-sm text-red-950">Chambre {{ $room->number }}</span>
                                        <p class="text-[11px] text-primary/60">{{ $room->roomType->name }} · Étage {{ $room->floor ?? 'RDC' }}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700">
                                        En réparation
                                    </span>
                                </div>
                                @if($room->latestHousekeepingAssignment?->issue_notes)
                                    <p class="text-xs text-red-950/80 italic mt-1 line-clamp-2">
                                        « {{ $room->latestHousekeepingAssignment->issue_notes }} »
                                    </p>
                                @endif
                            </div>
                            <div class="mt-3 pt-2.5 border-t border-red-100 flex items-center justify-between text-[11px]">
                                @if($techReq)
                                    <a href="{{ route('technique.demandes.show', $techReq) }}" class="inline-flex items-center gap-1 font-semibold text-orange-700 hover:text-orange-900 transition">
                                        <i data-lucide="wrench" class="w-3.5 h-3.5"></i>
                                        <span>{{ $techReq->reference }} ({{ $techReq->statusLabel() }})</span>
                                    </a>
                                @else
                                    <span class="text-red-700/70 font-medium">Intervention en cours</span>
                                @endif
                                <span class="text-primary/40 text-[10px]">Non disponible ménage</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Problèmes signalés & transmission technique --}}
        <div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                        Chambres avec problème signalé
                    </h2>
                    <p class="text-xs text-primary/50">Incidents relevés pendant le nettoyage.</p>
                </div>
                @if($blockedAssignments->count() > 0)
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        {{ $blockedAssignments->count() }}
                    </span>
                @endif
            </div>
            <div class="divide-y divide-secondary/10 max-h-96 overflow-y-auto">
                @forelse($blockedAssignments as $assignment)
                    <div class="px-5 py-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-1.5">
                            <div>
                                <p class="text-sm font-semibold text-primary">Chambre {{ $assignment->room->number }} — {{ $assignment->team?->name }}</p>
                                @if($assignment->reportedBy)
                                    <p class="text-[10px] text-primary/50">Signalé par {{ $assignment->reportedBy->name }}@if($assignment->reported_at) à {{ $assignment->reported_at->format('H:i') }}@endif</p>
                                @endif
                            </div>
                            @if($assignment->isIssueTechnique() || $assignment->room->status->value === 'maintenance')
                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[11px] font-semibold border border-red-200 inline-flex items-center gap-1">
                                    <i data-lucide="wrench" class="w-3 h-3"></i> Problème technique
                                </span>
                            @elseif($assignment->isIssueInterne())
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[11px] font-semibold border border-amber-300 inline-flex items-center gap-1">
                                    <i data-lucide="package-search" class="w-3 h-3"></i> Besoin interne étages
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px] font-medium border border-red-200">Incident</span>
                            @endif
                        </div>

                        <p class="text-xs text-primary/80 bg-gray-50 p-2.5 rounded-lg border border-secondary/20 mt-1 mb-2.5">
                            {{ $assignment->issue_notes }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Action A : Résolution pour besoin interne (savon, linge, réassort) --}}
                            @if($assignment->isIssueInterne())
                                <form method="POST" action="{{ route('housekeeping.resolve_issue', $assignment->room) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-xs cursor-pointer">
                                        <i data-lucide="check" class="w-3.5 h-3.5" aria-hidden="true"></i> Marquer résolu / Réapprovisionné
                                    </button>
                                </form>
                            @endif

                            {{-- Action B : Suivi technique ou transmission --}}
                            @php($intervention = \App\Services\MaintenanceService::actif() ? \App\Models\MaintenanceRequest::where('housekeeping_assignment_id', $assignment->id)->latest('id')->first() : null)
                            @if($intervention)
                                <a href="{{ route('technique.demandes.show', $intervention) }}" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg bg-orange-50 text-orange-800 border border-orange-200 hover:bg-orange-100 transition">
                                    <i data-lucide="wrench" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                    {{ $intervention->reference }} · {{ $intervention->statusLabel() }}{{ $intervention->technician ? ' · ' . $intervention->technician->name : '' }}
                                </a>
                            @elseif(\App\Services\MaintenanceService::actif())
                                <form method="POST" action="{{ route('housekeeping.transmettre_technique', $assignment->room) }}" class="inline-flex items-center">
                                    @csrf
                                    <input type="hidden" name="notes" value="{{ $assignment->issue_notes }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer {{ $assignment->isIssueInterne() ? 'bg-secondary/15 hover:bg-secondary/25 text-primary/80' : 'bg-orange-600 text-white hover:bg-orange-700' }}">
                                        <i data-lucide="wrench" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                        <span>{{ $assignment->isIssueInterne() ? 'Requalifier en panne technique' : 'Transmettre au service technique' }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-xs text-primary/40 text-center">Aucun problème signalé.</div>
                @endforelse
            </div>
        </div>

        {{-- Nettoyages terminés aujourd'hui --}}
        <div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                        Nettoyages terminés aujourd'hui
                    </h2>
                    <p class="text-xs text-primary/50">Historique des chambres faites dans la journée.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    {{ $completedToday->count() }}
                </span>
            </div>
            <div class="divide-y divide-secondary/10 max-h-96 overflow-y-auto">
                @forelse($completedToday as $assignment)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-primary">Chambre {{ $assignment->room->number }} — {{ $assignment->team?->name }}</p>
                            <span class="px-2 py-0.5 rounded-full {{ $assignment->isRecouche() ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }} text-[11px] font-medium border {{ $assignment->isRecouche() ? 'border-blue-200' : 'border-emerald-200' }}">
                                {{ $assignment->isRecouche() ? 'Recouche' : 'Nettoyée' }}
                            </span>
                        </div>
                        <p class="text-xs text-primary/50 mt-0.5">
                            {{ optional($assignment->completed_at)->format('H:i') }}@if($assignment->completedBy) · par {{ $assignment->completedBy->name }}@endif
                        </p>
                    </div>
                @empty
                    <div class="px-5 py-8 text-xs text-primary/40 text-center">Aucun nettoyage terminé aujourd'hui.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
