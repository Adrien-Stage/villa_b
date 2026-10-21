{{--
    Carte d'une recouche : chambre occupée que l'équipe refait pendant le
    séjour. Le client garde la chambre, son statut ne change pas.

    Attend :
      $room  Room occupée, avec activeHousekeepingAssignment (recouche)
--}}
@php $a = $room->activeHousekeepingAssignment; @endphp

<div class="rounded-xl border border-blue-200 bg-blue-50 p-4 flex flex-col">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="font-heading font-bold text-blue-900 text-lg">Chambre {{ $room->number }}</h3>
            <p class="text-xs text-blue-800/70">{{ $room->roomType->name }} · client en séjour</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-blue-100 text-blue-800">
            {{ $a?->status === 'in_progress' ? 'Recouche en cours' : 'Recouche' }}
        </span>
    </div>

    @if($a)
        <p class="mb-3 text-xs text-blue-900/80">
            {{ $a->team?->name }}
            @if($a->status === 'in_progress' && $a->started_at)
                · commencée à {{ $a->started_at->format('H:i') }}@if($a->startedBy) par {{ $a->startedBy->name }}@endif
            @endif
        </p>
    @endif

    {{-- Alerte si un incident interne est en attente (savon fini, réassort...) --}}
    @if($a && $a->status === 'blocked' && $a->isIssueInterne())
        <div class="mb-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900">
            <div class="flex items-center justify-between gap-1 mb-1 font-semibold text-[11px] text-amber-800">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="package-search" class="w-3.5 h-3.5"></i> Besoin interne en attente
                </span>
                @if($a->reportedBy)
                    <span class="text-[10px] text-amber-700/80">par {{ $a->reportedBy->name }}</span>
                @endif
            </div>
            <p class="text-[11px] text-amber-950/80 mb-2">{{ $a->issue_notes }}</p>
            <form method="POST" action="{{ route('housekeeping.resolve_issue', $room) }}">
                @csrf
                <button type="submit" class="w-full py-1.5 bg-emerald-600 text-white rounded-md text-[11px] font-semibold hover:bg-emerald-700 transition flex items-center justify-center gap-1 shadow-2xs">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Incident résolu / Réapprovisionné
                </button>
            </form>
        </div>
    @endif

    {{-- Accès direct au mini-bar pour la chambre --}}
    @if($room->aUnMiniBar())
        <div class="mb-3 flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-200/70 text-xs">
            <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}" class="inline-flex items-center gap-1.5 font-medium text-amber-900 hover:text-amber-950 transition">
                <i data-lucide="wine" class="w-3.5 h-3.5 text-amber-700"></i>
                <span>Mini-bar</span>
                @if($room->miniBarComplet())
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold">Complet</span>
                @elseif($room->miniBarVide())
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-800 font-semibold">Vide</span>
                @else
                    @php $manquant = $room->minibarLines->sum(fn ($l) => $l->manquant()); @endphp
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">{{ $manquant > 0 ? "-{$manquant} article(s)" : 'À vérifier' }}</span>
                @endif
            </a>
            <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}" class="inline-flex items-center gap-1 text-[11px] text-amber-800 hover:text-amber-950 font-semibold underline">
                <span>Gérer</span>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
            </a>
        </div>
    @endif

    <div class="mt-auto">
        @if($a?->status === 'in_progress' && $room->aUnMiniBar() && $a->minibarChecks->isEmpty())
            {{-- Le relevé du mini-bar fait partie de la recouche. --}}
            <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}"
               class="w-full py-2 bg-amber-600 text-white rounded-lg text-xs font-semibold hover:bg-amber-700 transition flex items-center justify-center gap-1.5">
                <i data-lucide="wine" class="w-4 h-4"></i> Relevé du mini-bar
            </a>
        @elseif($a?->status === 'in_progress')
            <div class="flex gap-2">
                <form method="POST" action="{{ route('housekeeping.stayover', $room) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Recouche terminée
                    </button>
                </form>
                <button type="button"
                        @click="$dispatch('open-issue-modal', { roomId: {{ $room->id }}, roomNumber: '{{ $room->number }}', actionUrl: '{{ route('housekeeping.issue', $room) }}' })"
                        class="px-3 py-2 bg-white border border-blue-300 text-blue-800 rounded-lg text-xs hover:bg-blue-100 transition cursor-pointer"
                        title="Signaler un problème">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </button>
            </div>
        @elseif($a)
            <div class="flex gap-2">
                <form method="POST" action="{{ route('housekeeping.clean', $room) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="play" class="w-4 h-4"></i> Commencer la recouche
                    </button>
                </form>
                <button type="button"
                        @click="$dispatch('open-issue-modal', { roomId: {{ $room->id }}, roomNumber: '{{ $room->number }}', actionUrl: '{{ route('housekeeping.issue', $room) }}' })"
                        class="px-3 py-2 bg-white border border-blue-300 text-blue-800 rounded-lg text-xs hover:bg-blue-100 transition cursor-pointer"
                        title="Signaler un problème">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </button>
            </div>
        @endif
    </div>
</div>
