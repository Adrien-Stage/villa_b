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

    <div class="mt-auto">
        @if($a?->status === 'in_progress')
            <form method="POST" action="{{ route('housekeeping.stayover', $room) }}">
                @csrf
                <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="check" class="w-4 h-4"></i> Recouche terminée
                </button>
            </form>
        @elseif($a)
            <form method="POST" action="{{ route('housekeeping.clean', $room) }}">
                @csrf
                <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="play" class="w-4 h-4"></i> Commencer la recouche
                </button>
            </form>
        @endif
    </div>
</div>
