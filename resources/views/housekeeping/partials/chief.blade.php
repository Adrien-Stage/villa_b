@php
    $priorityBadge = [
        'Critique' => 'bg-red-50 text-red-700 border-red-200',
        'Haute'    => 'bg-orange-50 text-orange-700 border-orange-200',
        'Elevee'   => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'Moyenne'  => 'bg-blue-50 text-blue-700 border-blue-200',
        'Normale'  => 'bg-secondary/10 text-primary/70 border-secondary/20',
    ];
    $activeRooms = $pipeline->flatten(1);
@endphp

<div class="flex flex-col gap-2 mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Housekeeping</h1>
    <p class="text-sm text-primary/50">Pilotage du nettoyage : priorisation, affectation des équipes et suivi du cycle des chambres.</p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <x-stat-card label="À nettoyer" :value="$stats['dirty_rooms']" subtitle="à affecter" color="red" />
    <x-stat-card label="En nettoyage" :value="$stats['cleaning']" subtitle="en cours" color="yellow" />
    <x-stat-card label="À contrôler" :value="$stats['to_inspect']" subtitle="nettoyées" color="purple" />
    <x-stat-card label="Contrôlées" :value="$stats['inspected']" subtitle="à libérer" color="emerald" />
    <x-stat-card label="Équipes" :value="$stats['teams']" subtitle="actives" color="blue" />
    <x-stat-card label="Terminées" :value="$stats['completed_today']" subtitle="aujourd'hui" color="emerald" />
</div>

{{-- ═══ Suivi visuel du cycle : chaque chambre avec son action ═══ --}}
<div class="mb-6">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <h2 class="font-heading font-semibold text-primary text-lg">Chambres en cours de traitement</h2>
        <div class="flex items-center gap-3 text-xs flex-wrap">
            <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-red-100 border border-red-300"></span> À nettoyer</span>
            <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-yellow-100 border border-yellow-300"></span> En nettoyage</span>
            <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-purple-100 border border-purple-300"></span> À contrôler</span>
            <span class="flex items-center gap-1.5 text-primary/60"><span class="w-3 h-3 rounded-full bg-emerald-100 border border-emerald-300"></span> Contrôlée</span>
        </div>
    </div>

    @if($activeRooms->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-primary/40 border border-dashed border-secondary/30">
            Aucune chambre ne requiert d'attention pour le moment.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($activeRooms as $room)
                @php $status = $room->status->value; @endphp

                @if($status === 'dirty')
                    {{-- À NETTOYER --}}
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between mb-3">
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
                        <div class="mt-auto">
                            @if($room->activeHousekeepingAssignment)
                                <div class="px-3 py-2 bg-white/60 rounded-lg border border-red-200 text-xs text-red-800">
                                    Assignée à : <span class="font-bold">{{ $room->activeHousekeepingAssignment->team->name }}</span>
                                </div>
                            @elseif($teams->isEmpty())
                                <p class="text-xs text-red-600/70 italic">Créez une équipe pour l'assigner.</p>
                            @else
                                <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="room_ids[]" value="{{ $room->id }}">
                                    <select name="housekeeping_team_id" required class="flex-1 px-2 py-2 text-xs border border-red-200 rounded-lg bg-white text-red-900 focus:outline-none focus:border-red-400">
                                        <option value="">Choisir une équipe…</option>
                                        @foreach($teams as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition">Affecter</button>
                                </form>
                            @endif
                        </div>
                    </div>

                @elseif($status === 'cleaning')
                    {{-- EN NETTOYAGE --}}
                    <div class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-yellow-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-yellow-800 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-yellow-200 text-yellow-900 uppercase tracking-widest flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> En cours
                            </span>
                        </div>
                        @if($room->activeHousekeepingAssignment)
                            <div class="mb-4 text-xs text-yellow-900/80">En charge : <span class="font-semibold px-2 py-0.5 bg-yellow-200/50 rounded-md">{{ $room->activeHousekeepingAssignment->team->name }}</span></div>
                        @endif
                        <div class="mt-auto flex gap-2">
                            <form method="POST" action="{{ route('housekeeping.ready', $room) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="check" class="w-4 h-4"></i> Marquer nettoyée
                                </button>
                            </form>
                            <form method="POST" action="{{ route('housekeeping.issue', $room) }}" class="flex gap-1"
                                  onsubmit="const n = prompt('Décrire le problème :'); if(!n){return false;} this.issue_notes.value = n;">
                                @csrf
                                <input type="hidden" name="issue_notes">
                                <button type="submit" class="px-3 py-2 bg-white border border-yellow-300 text-yellow-800 rounded-lg text-xs hover:bg-yellow-100 transition" title="Signaler un problème">
                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                @elseif($status === 'clean')
                    {{-- À CONTRÔLER --}}
                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-purple-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-purple-700 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-200 text-purple-800 uppercase tracking-widest">À contrôler</span>
                        </div>
                        <div class="mb-4 text-xs text-purple-800/80">Nettoyage terminé, en attente de contrôle qualité.</div>
                        <div class="mt-auto flex gap-2">
                            <form method="POST" action="{{ route('housekeeping.inspect', $room) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i> Valider (conforme)
                                </button>
                            </form>
                            <form method="POST" action="{{ route('housekeeping.reject', $room) }}" class="w-10">
                                @csrf
                                <input type="hidden" name="reason" value="Contrôle non conforme">
                                <button type="submit" class="w-full h-full bg-white border border-purple-200 text-red-600 rounded-lg hover:bg-red-50 hover:border-red-200 transition flex items-center justify-center" title="Refuser (renvoyer au nettoyage)">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                @elseif($status === 'inspected')
                    {{-- CONTRÔLÉE : le chef peut libérer la chambre --}}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-emerald-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-emerald-700 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase tracking-widest">Contrôlée</span>
                        </div>
                        <div class="mt-auto">
                            <form method="POST" action="{{ route('housekeeping.available', $room) }}">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition flex items-center justify-center gap-1.5">
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

{{-- ═══ Affectation en lot des chambres à nettoyer ═══ --}}
<div class="bg-white rounded-xl shadow-sm p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="font-heading font-semibold text-primary text-sm">Affecter les chambres à nettoyer</h2>
            <p class="text-xs text-primary/40 mt-0.5">Sélection multiple, suivant la liste de priorités.</p>
        </div>
        <span class="text-xs text-primary/40">{{ $dirtyRooms->count() }} chambre{{ $dirtyRooms->count() > 1 ? 's' : '' }}</span>
    </div>

    @if($dirtyRooms->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-sm text-primary/40 text-center">Aucune chambre à nettoyer à affecter.</div>
    @elseif($teams->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-sm text-primary/40 text-center">Créez d'abord une équipe de nettoyage ci-dessous.</div>
    @else
        <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="space-y-4 max-w-3xl">
            @csrf
            <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div class="flex-1">
                    <label class="block text-xs text-primary/50 mb-1.5">Équipe</label>
                    <select name="housekeeping_team_id" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary">
                        <option value="">Choisir…</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}{{ $team->leader ? ' — ' . $team->leader->name : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Affecter la sélection</button>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold text-primary">Chambres (priorisées)</label>
                    <button type="button" onclick="document.querySelectorAll('.dirty-room-checkbox').forEach(c => c.checked = true)" class="text-xs text-secondary hover:text-primary transition-colors">Tout cocher</button>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-lg border border-secondary/20 divide-y divide-secondary/10">
                    @foreach($priorityRooms as $item)
                        @php $room = $item['room']; @endphp
                        <label class="flex items-center gap-3 px-3 py-2 text-xs text-primary hover:bg-accent/5 cursor-pointer">
                            <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" class="dirty-room-checkbox rounded border-secondary/40 text-primary focus:ring-primary">
                            <span class="font-medium">{{ $room->number }}</span>
                            <span class="text-primary/40">{{ $room->roomType->name }}</span>
                            <span class="px-2 py-0.5 rounded-full border text-[10px] {{ $priorityBadge[$item['priority_label']] ?? $priorityBadge['Normale'] }}">{{ $item['priority_label'] }}</span>
                            @if($room->activeHousekeepingAssignment)
                                <span class="ml-auto text-[11px] text-orange-600">déjà affectée</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <textarea name="notes" rows="2" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary resize-none" placeholder="Consignes (optionnel)…"></textarea>
        </form>
    @endif
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- ═══ Création d'équipe ═══ --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-heading font-semibold text-primary text-sm mb-4">Créer une équipe</h2>
        <form method="POST" action="{{ route('housekeeping.teams.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
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
            <button type="submit" class="w-full py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Enregistrer l'équipe</button>
        </form>
    </div>

    {{-- ═══ Équipes existantes ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Équipes</h2>
            <span class="text-xs text-primary/40">{{ $teams->count() }}</span>
        </div>
        @if($teams->isEmpty())
            <div class="px-5 py-10 text-sm text-primary/40 text-center">Aucune équipe créée.</div>
        @else
            <div class="p-4 space-y-3">
                @foreach($teams as $team)
                    <div class="rounded-xl border border-secondary/20 p-4 bg-accent/10">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div>
                                <p class="font-heading font-semibold text-primary">{{ $team->name }}</p>
                                <p class="text-xs text-primary/50">Chef : {{ $team->leader?->name ?? 'non défini' }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-secondary/10 text-primary/70">{{ $team->activeAssignments->count() }} ch.</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($team->members as $member)
                                <span class="px-2 py-0.5 rounded-full bg-white border border-secondary/20 text-[11px] text-primary/70">{{ $member->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ Problèmes & terminées ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Problèmes & terminées</h2>
        </div>
        <div class="divide-y divide-secondary/10 max-h-96 overflow-y-auto">
            @forelse($blockedAssignments as $assignment)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <p class="text-sm font-medium text-primary">Chambre {{ $assignment->room->number }} — {{ $assignment->team?->name }}</p>
                        <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px] font-medium">Problème</span>
                    </div>
                    <p class="text-xs text-red-700">{{ $assignment->issue_notes }}</p>
                </div>
            @empty
                <div class="px-5 py-3 text-xs text-primary/35">Aucun problème signalé.</div>
            @endforelse

            @forelse($completedToday as $assignment)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-primary">Chambre {{ $assignment->room->number }} — {{ $assignment->team?->name }}</p>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">Nettoyée</span>
                    </div>
                    <p class="text-xs text-primary/45">{{ optional($assignment->completed_at)->locale('fr')->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-5 py-3 text-xs text-primary/35">Aucun nettoyage terminé aujourd'hui.</div>
            @endforelse
        </div>
    </div>
</div>
