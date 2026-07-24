@php
    $me = auth()->id();
    $statusStyles = [
        'dirty'     => ['label' => 'À nettoyer',   'chip' => 'bg-red-100 text-red-800',      'card' => 'border-red-200 bg-red-50'],
        'cleaning'  => ['label' => 'En nettoyage',  'chip' => 'bg-yellow-100 text-yellow-900', 'card' => 'border-yellow-300 bg-yellow-50'],
        'clean'     => ['label' => 'À contrôler',   'chip' => 'bg-purple-100 text-purple-800', 'card' => 'border-purple-200 bg-purple-50'],
        'inspected' => ['label' => 'Contrôlée',     'chip' => 'bg-emerald-100 text-emerald-800','card' => 'border-emerald-200 bg-emerald-50'],
    ];
@endphp

<div class="flex flex-col gap-2 mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Mon espace housekeeping</h1>
    <p class="text-sm text-primary/50">Mon équipe et les chambres qui me sont confiées.</p>
</div>

@if($myTeams->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center border border-dashed border-secondary/30">
        <i data-lucide="users" class="w-8 h-8 mx-auto text-primary/20 mb-3"></i>
        <p class="text-sm text-primary/50">Vous n'êtes pas encore rattaché à une équipe de nettoyage.</p>
        <p class="text-xs text-primary/40 mt-1">Rapprochez-vous du chef de service.</p>
    </div>
@else
    {{-- ═══ Mon / mes équipe(s) ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        @foreach($myTeams as $team)
            @php $iLead = $team->leader_id === $me; @endphp
            <div class="bg-white rounded-xl shadow-sm p-5 border border-secondary/20">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-heading font-semibold text-primary text-lg">{{ $team->name }}</p>
                        <p class="text-xs text-primary/50">Chef d'équipe : {{ $team->leader?->name ?? 'non défini' }}</p>
                    </div>
                    @if($iLead)
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-primary/10 text-primary uppercase tracking-widest">Chef d'équipe</span>
                    @endif
                </div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/40 mb-2">Coéquipiers</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($team->members as $member)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-accent/10 border border-secondary/20 text-xs text-primary/80">
                            <span class="w-2 h-2 rounded-full {{ ($presence[$member->id] ?? false) ? 'bg-green-500' : 'bg-gray-300' }}"
                                  title="{{ ($presence[$member->id] ?? false) ? 'En ligne' : 'Hors ligne' }}"></span>
                            {{ $member->name }}@if($member->id === $me) <span class="text-primary/40">(moi)</span>@endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══ Mes chambres ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Chambres qui me sont confiées</h2>
            <span class="text-xs text-primary/40">{{ $myRooms->count() }} chambre{{ $myRooms->count() > 1 ? 's' : '' }}</span>
        </div>

        @if($myRooms->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-primary/40">Aucune chambre à traiter pour le moment. Bon travail !</div>
        @else
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($myRooms as $room)
                    @php
                        $s = $room->status->value;
                        $style = $statusStyles[$s];
                        // Chef d'équipe de CETTE chambre : accès aux actions de contrôle/libération.
                        $iLeadRoom = $room->latestHousekeepingAssignment
                            && $ledTeamIds->contains($room->latestHousekeepingAssignment->housekeeping_team_id);
                    @endphp
                    <div class="rounded-xl border {{ $style['card'] }} p-4 flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-heading font-bold text-primary text-lg">Chambre {{ $room->number }}</h3>
                                <p class="text-xs text-primary/50">{{ $room->roomType->name }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest {{ $style['chip'] }}">{{ $style['label'] }}</span>
                        </div>

                        <div class="mt-auto">
                            @if($s === 'dirty')
                                <form method="POST" action="{{ route('housekeeping.clean', $room) }}">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-yellow-600 text-white rounded-lg text-xs font-semibold hover:bg-yellow-700 transition flex items-center justify-center gap-1.5">
                                        <i data-lucide="play" class="w-4 h-4"></i> Commencer le nettoyage
                                    </button>
                                </form>

                            @elseif($s === 'cleaning')
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('housekeeping.ready', $room) }}" class="flex-1">
                                        @csrf
                                        <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                                            <i data-lucide="check" class="w-4 h-4"></i> Marquer nettoyée
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('housekeeping.issue', $room) }}"
                                          onsubmit="const n = prompt('Décrire le problème :'); if(!n){return false;} this.issue_notes.value = n;">
                                        @csrf
                                        <input type="hidden" name="issue_notes">
                                        <button type="submit" class="px-3 py-2 bg-white border border-yellow-300 text-yellow-800 rounded-lg text-xs hover:bg-yellow-100 transition" title="Signaler un problème">
                                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>

                            @elseif($s === 'clean')
                                @if($iLeadRoom)
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('housekeeping.inspect', $room) }}" class="flex-1">
                                            @csrf
                                            <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i> Valider
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('housekeeping.reject', $room) }}" class="w-10">
                                            @csrf
                                            <input type="hidden" name="reason" value="Contrôle non conforme">
                                            <button type="submit" class="w-full h-full bg-white border border-purple-200 text-red-600 rounded-lg hover:bg-red-50 transition flex items-center justify-center" title="Refuser">
                                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <p class="text-xs text-purple-700/80 text-center py-2">En attente du contrôle du chef d'équipe.</p>
                                @endif

                            @elseif($s === 'inspected')
                                @if($iLeadRoom)
                                    <form method="POST" action="{{ route('housekeeping.available', $room) }}">
                                        @csrf
                                        <button type="submit" class="w-full py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition flex items-center justify-center gap-1.5">
                                            <i data-lucide="door-open" class="w-4 h-4"></i> Rendre disponible
                                        </button>
                                    </form>
                                @else
                                    <p class="text-xs text-emerald-700/80 text-center py-2">Contrôlée — prête à être libérée.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
