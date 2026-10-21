@php
    $me = auth()->id();
    $statusStyles = [
        'dirty'     => ['label' => 'À nettoyer',   'chip' => 'bg-red-100 text-red-800',      'card' => 'border-red-200 bg-red-50'],
        'cleaning'  => ['label' => 'En nettoyage',  'chip' => 'bg-yellow-100 text-yellow-900', 'card' => 'border-yellow-300 bg-yellow-50'],
        'clean'     => ['label' => 'À contrôler',   'chip' => 'bg-purple-100 text-purple-800', 'card' => 'border-purple-200 bg-purple-50'],
        'inspected' => ['label' => 'Contrôlée',     'chip' => 'bg-emerald-100 text-emerald-800','card' => 'border-emerald-200 bg-emerald-50'],
    ];
@endphp

<div x-data class="space-y-6">
<div class="flex items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-xl sm:text-2xl font-semibold text-primary">Mon espace housekeeping</h1>
        <p class="text-xs sm:text-sm text-primary/50">Mon équipe et les chambres qui me sont confiées.</p>
    </div>
    <button type="button" onclick="window.location.reload()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-3.5 sm:py-2 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition shadow-2xs"
            title="Actualiser les chambres">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
        <span class="hidden sm:inline">Actualiser</span>
    </button>
</div>

@if($myTeams->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center border border-dashed border-secondary/30">
        <i data-lucide="users" class="w-8 h-8 mx-auto text-primary/20 mb-3"></i>
        <p class="text-sm text-primary/50">Vous n'êtes pas encore rattaché à une équipe de nettoyage.</p>
        <p class="text-xs text-primary/40 mt-1">Rapprochez-vous du responsable des étages.</p>
    </div>
@else
    {{-- ═══ Mon / mes équipe(s) ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        @foreach($myTeams as $team)
            @php $iLead = $team->leader_id === $me; @endphp
            <div class="bg-white rounded-xl shadow-sm p-5 border border-secondary/20">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
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

    {{-- ═══ Mes recouches : chambres occupées à refaire ═══ --}}
    @if($myStayovers->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
                <h2 class="font-heading font-semibold text-primary text-sm">Recouches — chambres occupées</h2>
                <span class="text-xs text-primary/40">{{ $myStayovers->count() }} chambre{{ $myStayovers->count() > 1 ? 's' : '' }}</span>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($myStayovers as $room)
                    @include('housekeeping.partials.stayover-card', ['room' => $room])
                @endforeach
            </div>
        </div>
    @endif


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
                        // Chef d'équipe de CETTE chambre : accès aux actions de contrôle/libération.
                        $iLeadRoom = $room->latestHousekeepingAssignment
                            && $ledTeamIds->contains($room->latestHousekeepingAssignment->housekeeping_team_id);
                    @endphp
                    @include('housekeeping.partials.room-card', [
                        'room'         => $room,
                        'statusStyles' => $statusStyles,
                        'canValidate'  => $iLeadRoom,
                    ])
                @endforeach
            </div>
        @endif
    </div>
@endif
</div>

