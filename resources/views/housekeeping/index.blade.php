@extends('layouts.hotel')

@section('title', 'Housekeeping')

@section('content')

@php
    $pipelineLabels = [
        'dirty' => 'Sales',
        'cleaning' => 'En nettoyage',
        'clean' => 'Propres',
        'inspected' => 'Controlees',
    ];

    $priorityBadge = [
        'Critique' => 'bg-red-50 text-red-700 border-red-200',
        'Haute' => 'bg-orange-50 text-orange-700 border-orange-200',
        'Elevee' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'Moyenne' => 'bg-blue-50 text-blue-700 border-blue-200',
        'Normale' => 'bg-secondary/10 text-primary/70 border-secondary/20',
    ];
@endphp

<div class="flex flex-col gap-2 mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Housekeeping</h1>
    <p class="text-sm text-primary/50">Pilotage nettoyage avec priorisation et assignation des equipes.</p>
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    {{ $errors->first() }}
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
    <x-stat-card label="Sales" :value="$stats['dirty_rooms']" subtitle="a affecter" color="red" />
    <x-stat-card label="Equipes" :value="$stats['teams']" subtitle="actives" color="blue" />
    <x-stat-card label="En attente" :value="$stats['pending_assignments']" subtitle="assignations" color="orange" />
    <x-stat-card label="En cours" :value="$stats['in_progress_assignments']" subtitle="nettoyages" color="purple" />
    <x-stat-card label="Bloquees" :value="$stats['blocked_assignments']" subtitle="problemes" color="orange" />
    <x-stat-card label="Terminees" :value="$stats['completed_today']" subtitle="aujourd'hui" color="emerald" />
</div>

<div class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading font-semibold text-primary text-xl">Centre d'actions rapides (Tableau de bord visuel)</h2>
        <div class="flex items-center gap-3 text-xs">
            <span class="flex items-center gap-1.5 text-primary/60"><div class="w-3 h-3 rounded-full bg-red-100 border border-red-300"></div> Sale</span>
            <span class="flex items-center gap-1.5 text-primary/60"><div class="w-3 h-3 rounded-full bg-yellow-100 border border-yellow-300"></div> En nettoyage</span>
            <span class="flex items-center gap-1.5 text-primary/60"><div class="w-3 h-3 rounded-full bg-purple-100 border border-purple-300"></div> À inspecter</span>
            <span class="flex items-center gap-1.5 text-primary/60"><div class="w-3 h-3 rounded-full bg-emerald-100 border border-emerald-300"></div> Contrôlée</span>
        </div>
    </div>

    @php
        // On regroupe toutes les chambres d'intérêt (Sale, Nettoyage, Propre, Inspectée) depuis la pipeline déjà chargée
        $allActiveRooms = $housekeepingPipeline->flatten(1);
    @endphp

    @if($allActiveRooms->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-primary/40 border border-dashed border-secondary/30">
            Aucune chambre ne requiert votre attention pour le moment.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($allActiveRooms as $room)
                @if($room->status->value === 'dirty')
                    {{-- CARTE ROUGE : SALE --}}
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm flex flex-col relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-red-100 rounded-bl-full -z-0"></div>
                        
                        <div class="flex items-start justify-between relative z-10 mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-red-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-red-700 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-200 text-red-800 uppercase tracking-widest">Sale</span>
                        </div>

                        @php
                            // Trouver la priorité si elle existe dans $priorityRooms
                            $prioInfo = $priorityRooms->firstWhere('room.id', $room->id);
                        @endphp
                        
                        @if($prioInfo)
                        <div class="mb-4 text-xs text-red-800/80 bg-red-100/50 p-2 rounded-lg">
                            <span class="font-semibold">{{ $prioInfo['priority_label'] }} :</span> {{ $prioInfo['priority_reason'] }}
                        </div>
                        @endif

                        <div class="mt-auto relative z-10">
                            @if($room->activeHousekeepingAssignment)
                                <div class="px-3 py-2 bg-white/60 rounded-lg border border-red-200 text-xs text-red-800">
                                    Déjà assignée à : <span class="font-bold">{{ $room->activeHousekeepingAssignment->team->name }}</span>
                                </div>
                            @elseif($teams->isEmpty())
                                <p class="text-xs text-red-600/70 italic">Créez une équipe pour l'assigner.</p>
                            @else
                                {{-- ACTION RAPIDE : ASSIGNER --}}
                                <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="room_ids[]" value="{{ $room->id }}">
                                    <select name="housekeeping_team_id" required class="flex-1 px-2 py-2 text-xs border border-red-200 rounded-lg bg-white text-red-900 focus:outline-none focus:border-red-400">
                                        <option value="">Sélectionner équipe...</option>
                                        @foreach($teams as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition">
                                        Go
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @elseif($room->status->value === 'cleaning')
                    {{-- CARTE JAUNE : EN NETTOYAGE --}}
                    <div class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 shadow-sm flex flex-col relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-yellow-200/50 rounded-bl-full -z-0"></div>
                        
                        <div class="flex items-start justify-between relative z-10 mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-yellow-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-yellow-800 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-yellow-200 text-yellow-900 uppercase tracking-widest flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> En cours
                            </span>
                        </div>

                        @if($room->activeHousekeepingAssignment)
                        <div class="mb-4 text-xs text-yellow-900/80">
                            En charge : <span class="font-semibold px-2 py-0.5 bg-yellow-200/50 rounded-md">{{ $room->activeHousekeepingAssignment->team->name }}</span>
                        </div>
                        @endif

                        <div class="mt-auto relative z-10">
                            {{-- ACTION RAPIDE : SIGNALER UN PROBLEME --}}
                            <form method="POST" action="{{ route('housekeeping.issue', $room) }}" class="flex gap-2">
                                @csrf
                                <input type="text" name="issue_notes" required placeholder="Ex: Fuite d'eau..." class="flex-1 px-2 py-2 text-xs border border-yellow-300 rounded-lg bg-white text-yellow-900 focus:outline-none">
                                <button type="submit" class="px-3 py-2 bg-yellow-600 text-white rounded-lg text-xs font-semibold hover:bg-yellow-700 transition" title="Signaler un problème">
                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif($room->status->value === 'clean')
                    {{-- CARTE VIOLETTE : À INSPECTER (PROPRE) --}}
                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 shadow-sm flex flex-col relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-purple-100 rounded-bl-full -z-0"></div>
                        
                        <div class="flex items-start justify-between relative z-10 mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-purple-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-purple-700 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-200 text-purple-800 uppercase tracking-widest">À Inspecter</span>
                        </div>

                        <div class="mb-4 text-xs text-purple-800/80">
                            Nettoyage terminé. En attente de validation par le chef d'équipe.
                        </div>

                        <div class="mt-auto relative z-10 flex gap-2">
                            {{-- ACTION RAPIDE : INSPECTER --}}
                            <form method="POST" action="{{ route('rooms.updateStatus', $room) }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="status" value="inspected">
                                <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i> Valider (Conforme)
                                </button>
                            </form>
                            
                            {{-- ACTION RAPIDE : REFUSER (REMETTRE EN SALE) --}}
                            <form method="POST" action="{{ route('rooms.updateStatus', $room) }}" class="w-10">
                                @csrf
                                <input type="hidden" name="status" value="dirty">
                                <input type="hidden" name="reason" value="Inspection échouée">
                                <button type="submit" class="w-full h-full bg-white border border-purple-200 text-red-600 rounded-lg text-xs hover:bg-red-50 hover:border-red-200 transition flex items-center justify-center" title="Refuser le nettoyage">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif($room->status->value === 'inspected')
                    {{-- CARTE VERTE : CONTRÔLÉE (ATTENTE RÉCEPTION) --}}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm flex flex-col relative overflow-hidden group opacity-80 hover:opacity-100 transition-opacity">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-100 rounded-bl-full -z-0"></div>
                        
                        <div class="flex items-start justify-between relative z-10 mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-emerald-900 text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-emerald-700 font-medium">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase tracking-widest">Contrôlée</span>
                        </div>

                        <div class="mt-auto relative z-10 text-xs text-emerald-800/80 bg-emerald-100/50 p-2 rounded-lg flex items-center gap-2">
                            <i data-lucide="info" class="w-4 h-4"></i> La réception doit la libérer pour location.
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
        <h2 class="font-heading font-semibold text-primary text-sm">Affectation multiple (Batch)</h2>
        <span class="text-xs text-primary/40">{{ $dirtyRooms->count() }} chambre(s) sale(s)</span>
    </div>
    <div class="p-5">
        @role('housekeeping_leader', 'manager')
        @if($dirtyRooms->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-sm text-primary/40 text-center">
            Aucune chambre sale à affecter en lot.
        </div>
        @elseif($teams->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-sm text-primary/40 text-center">
            Crée d'abord une équipe de nettoyage en bas de page.
        </div>
        @else
        <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="space-y-4 max-w-3xl mx-auto">
            @csrf
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-xs text-primary/50 mb-1.5">Sélectionnez une équipe</label>
                    <select name="housekeeping_team_id" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary">
                        <option value="">Choisir...</option>
                        @foreach($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}{{ $team->leader ? ' - ' . $team->leader->name : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-2">
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors h-[42px]">
                        Affecter les chambres cochées
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold text-primary">Cochez les chambres à assigner :</label>
                    <button type="button" onclick="toggleDirtyRooms(true)" class="text-xs text-secondary hover:text-primary transition-colors">Tout cocher</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($dirtyRooms as $room)
                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border {{ $room->activeHousekeepingAssignment ? 'border-orange-200 bg-orange-50' : 'border-secondary/20 bg-white hover:bg-accent/5' }} cursor-pointer transition-colors">
                        <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" class="dirty-room-checkbox rounded border-secondary/40 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-primary">{{ $room->number }}</span>
                        @if($room->activeHousekeepingAssignment)
                            <span class="text-[10px] text-orange-600 uppercase">Déjà assignée</span>
                        @endif
                    </label>
                    @endforeach
                </div>
            </div>
            
            <div>
                <textarea name="notes" rows="2" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-xl bg-white text-primary focus:outline-none focus:border-secondary resize-none" placeholder="Consignes (optionnel)..."></textarea>
            </div>
        </form>
        @endif
        @else
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-sm text-primary/40 text-center">
            L'affectation des chambres est réservée au chef de service housekeeping.
        </div>
        @endrole
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    @role('housekeeping_leader', 'manager')
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-primary text-sm">Creer une equipe</h2>
            <span class="text-xs text-primary/40 uppercase tracking-widest">Chef de service</span>
        </div>

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
                <label class="block text-xs text-primary/50 mb-1.5">Chef d'equipe</label>
                <select name="leader_id" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                    <option value="">Aucun chef designe</option>
                    @foreach($staff as $member)
                    <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->role }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-primary/50 mb-2">Membres terrain</label>
                <div class="max-h-48 overflow-y-auto rounded-lg border border-secondary/20 divide-y divide-secondary/10">
                    @forelse($staff as $member)
                    <label class="flex items-center gap-3 px-3 py-2 text-xs text-primary">
                        <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="rounded border-secondary/40 text-primary focus:ring-primary">
                        <span class="flex-1">{{ $member->name }}</span>
                        <span class="text-primary/40">{{ $member->role }}</span>
                    </label>
                    @empty
                    <div class="px-3 py-4 text-xs text-primary/40">Aucun agent housekeeping disponible.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <label class="block text-xs text-primary/50 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary resize-none" placeholder="Zone, etage, specialite..."></textarea>
            </div>

            <button type="submit" class="w-full py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                Enregistrer l'equipe
            </button>
        </form>
    </div>
    @endrole

    <div class="bg-white rounded-xl shadow-sm p-5 xl:col-span-2">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <div>
                <h2 class="font-heading font-semibold text-primary text-sm">Affecter les chambres sales</h2>
                <p class="text-xs text-primary/40 mt-1">Assignation en suivant la liste de priorites.</p>
            </div>
            <span class="text-xs text-primary/40">{{ $dirtyRooms->count() }} chambre{{ $dirtyRooms->count() > 1 ? 's' : '' }}</span>
        </div>

        @role('housekeeping_leader', 'manager')
        @if($dirtyRooms->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-10 text-sm text-primary/40 text-center">
            Aucune chambre sale a affecter.
        </div>
        @elseif($teams->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-10 text-sm text-primary/40 text-center">
            Cree d'abord une equipe de nettoyage.
        </div>
        @else
        <form method="POST" action="{{ route('housekeeping.assignments.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-primary/50 mb-1.5">Equipe</label>
                <select name="housekeeping_team_id" required class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                    <option value="">Selectionner une equipe</option>
                    @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}{{ $team->leader ? ' - ' . $team->leader->name : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs text-primary/50">Chambres sales (priorisees)</label>
                    <button type="button" onclick="toggleDirtyRooms(true)" class="text-[11px] text-primary/50 hover:text-primary">Tout cocher</button>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-lg border border-secondary/20 divide-y divide-secondary/10">
                    @foreach($priorityRooms as $item)
                    @php $room = $item['room']; @endphp
                    <label class="flex items-center gap-3 px-3 py-2 text-xs text-primary">
                        <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" class="dirty-room-checkbox rounded border-secondary/40 text-primary focus:ring-primary">
                        <span class="font-medium">{{ $room->number }}</span>
                        <span class="text-primary/40">{{ $room->roomType->name }}</span>
                        <span class="px-2 py-0.5 rounded-full border text-[10px] {{ $priorityBadge[$item['priority_label']] ?? $priorityBadge['Normale'] }}">{{ $item['priority_label'] }}</span>
                        @if($room->activeHousekeepingAssignment)
                        <span class="ml-auto text-[11px] text-orange-600">deja affectee</span>
                        @endif
                    </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs text-primary/50 mb-1.5">Consignes</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary resize-none" placeholder="Priorite, etage, consignes..."></textarea>
            </div>

            <button type="submit" class="w-full py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                Affecter la selection
            </button>
        </form>
        @endif
        @else
        <div class="rounded-xl border border-dashed border-secondary/30 px-4 py-10 text-sm text-primary/40 text-center">
            L'affectation des chambres est reservee au chef de service housekeeping.
        </div>
        @endrole
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Equipes de nettoyage</h2>
            <span class="text-xs text-primary/40">{{ $teams->count() }} equipe{{ $teams->count() > 1 ? 's' : '' }}</span>
        </div>

        @if($teams->isEmpty())
        <div class="px-5 py-10 text-sm text-primary/40 text-center">Aucune equipe creee pour le moment.</div>
        @else
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($teams as $team)
            <div class="rounded-xl border border-secondary/20 p-4 bg-accent/10">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-heading font-semibold text-primary">{{ $team->name }}</p>
                        <p class="text-xs text-primary/40">{{ $team->code ?: 'Sans code' }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-secondary/10 text-primary/70">
                        {{ $team->activeAssignments->count() }} chambre{{ $team->activeAssignments->count() > 1 ? 's' : '' }}
                    </span>
                </div>
                <p class="text-xs text-primary/60 mb-2">Chef : <span class="font-medium text-primary">{{ $team->leader?->name ?? 'Non defini' }}</span></p>
                <div class="flex flex-wrap gap-2">
                    @foreach($team->members as $member)
                    <span class="px-2 py-1 rounded-full bg-white border border-secondary/20 text-xs text-primary/70">{{ $member->name }}</span>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Problemes et nettoyages termines</h2>
            <span class="text-xs text-primary/40">{{ $completedToday->count() }} termine{{ $completedToday->count() > 1 ? 's' : '' }}</span>
        </div>

        <div class="divide-y divide-secondary/10">
            @forelse($activeAssignments->where('status', 'blocked') as $assignment)
            <div class="px-5 py-4">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <p class="text-sm font-medium text-primary">Chambre {{ $assignment->room->number }} - {{ $assignment->team->name }}</p>
                    <span class="px-2 py-1 rounded-full bg-red-50 text-red-700 text-xs font-medium">Probleme</span>
                </div>
                <p class="text-xs text-red-700">{{ $assignment->issue_notes }}</p>
            </div>
            @empty
            <div class="px-5 py-4 text-xs text-primary/35">Aucun probleme signale.</div>
            @endforelse

            @forelse($completedToday as $assignment)
            <div class="px-5 py-4">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <p class="text-sm font-medium text-primary">Chambre {{ $assignment->room->number }} - {{ $assignment->team->name }}</p>
                    <span class="px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">Propre</span>
                </div>
                <p class="text-xs text-primary/45">{{ optional($assignment->completed_at)->locale('fr')->diffForHumans() }}</p>
            </div>
            @empty
            <div class="px-5 py-4 text-xs text-primary/35">Aucun nettoyage termine aujourd'hui.</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function toggleDirtyRooms(checked) {
    document.querySelectorAll('.dirty-room-checkbox').forEach((checkbox) => {
        checkbox.checked = checked;
    });
}

// Actualisation automatique du dashboard toutes les 15 secondes
// S'arrête si l'utilisateur interagit avec un formulaire (focus ou case cochée)
setInterval(() => {
    const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    const hasCheckedBoxes = document.querySelectorAll('input[type="checkbox"]:checked').length > 0;
    
    if (!isTyping && !hasCheckedBoxes) {
        window.location.reload();
    }
}, 15000);
</script>

@push('scripts')
<script>
// Code de l'équipe suggéré depuis le nom, à la création (modifiable).
(function () {
    function wire() {
        window.wireAutoCode(
            document.getElementById('create-team-name'),
            document.getElementById('create-team-code')
        );
    }
    if (document.readyState !== 'loading') wire();
    else document.addEventListener('DOMContentLoaded', wire);
})();
</script>
@endpush

@endsection
