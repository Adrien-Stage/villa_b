{{--
    Carte d'une chambre du cycle ménage, avec les actions correspondant à son
    statut. Partagée par l'écran des agents et celui du chef de service : les
    deux manipulent les mêmes états, seul le droit de contrôler diffère.

    Attend :
      $room         Room, statut dans le cycle (dirty|cleaning|clean|inspected)
      $statusStyles tableau des libellés et classes par statut
      $canValidate  bool — droit de contrôler et de remettre à la vente
--}}
@php
    $s = $room->status->value;
    $style = $statusStyles[$s];
@endphp

<div class="rounded-xl border {{ $style['card'] }} p-4 flex flex-col">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="font-heading font-bold text-primary text-lg">Chambre {{ $room->number }}</h3>
            <p class="text-xs text-primary/50">{{ $room->roomType->name }}</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest {{ $style['chip'] }}">{{ $style['label'] }}</span>
    </div>

    @php $passage = $room->activeHousekeepingAssignment ?? $room->latestHousekeepingAssignment; @endphp
    @if($s === 'cleaning' && $passage?->started_at)
        <p class="mb-3 text-xs text-primary/60">Commencée à {{ $passage->started_at->format('H:i') }}@if($passage->startedBy) par {{ $passage->startedBy->name }}@endif</p>
    @elseif(in_array($s, ['clean', 'inspected'], true) && $passage?->completed_at)
        <p class="mb-3 text-xs text-primary/60">Nettoyée à {{ $passage->completed_at->format('H:i') }}@if($passage->completedBy) par {{ $passage->completedBy->name }}@endif</p>
    @endif

    {{-- Alerte si un incident interne est en attente (savon fini, réassort...) --}}
    @if($passage && $passage->status === 'blocked' && $passage->isIssueInterne())
        <div class="mb-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900">
            <div class="flex items-center justify-between gap-1 mb-1 font-semibold text-[11px] text-amber-800">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="package-search" class="w-3.5 h-3.5"></i> Besoin interne en attente
                </span>
                @if($passage->reportedBy)
                    <span class="text-[10px] text-amber-700/80">par {{ $passage->reportedBy->name }}</span>
                @endif
            </div>
            <p class="text-[11px] text-amber-950/80 mb-2">{{ $passage->issue_notes }}</p>
            <form method="POST" action="{{ route('housekeeping.resolve_issue', $room) }}">
                @csrf
                <button type="submit" class="w-full py-1.5 bg-emerald-600 text-white rounded-md text-[11px] font-semibold hover:bg-emerald-700 transition flex items-center justify-center gap-1 shadow-2xs">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Problème résolu / Réapprovisionné
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
        @if($s === 'dirty')
            <div class="flex gap-2">
                <form method="POST" action="{{ route('housekeeping.clean', $room) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full py-2 bg-yellow-600 text-white rounded-lg text-xs font-semibold hover:bg-yellow-700 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="play" class="w-4 h-4"></i> Commencer le nettoyage
                    </button>
                </form>
                <button type="button"
                        @click="$dispatch('open-issue-modal', { roomId: {{ $room->id }}, roomNumber: '{{ $room->number }}', actionUrl: '{{ route('housekeeping.issue', $room) }}' })"
                        class="px-3 py-2 bg-white border border-yellow-300 text-yellow-800 rounded-lg text-xs hover:bg-yellow-100 transition cursor-pointer"
                        title="Signaler un problème">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </button>
            </div>

        @elseif($s === 'cleaning')
            <div class="flex gap-2">
                @if(\App\Services\MinibarService::releveDuAuMenage($room))
                    {{-- Client parti sans relevé : le mini-bar se relève avant tout. --}}
                    <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}"
                       class="flex-1 py-2 bg-amber-600 text-white rounded-lg text-xs font-semibold hover:bg-amber-700 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="wine" class="w-4 h-4"></i> Relevé du mini-bar
                    </a>
                @else
                <form method="POST" action="{{ route('housekeeping.ready', $room) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Marquer nettoyée
                    </button>
                </form>
                @endif
                <button type="button"
                        @click="$dispatch('open-issue-modal', { roomId: {{ $room->id }}, roomNumber: '{{ $room->number }}', actionUrl: '{{ route('housekeeping.issue', $room) }}' })"
                        class="px-3 py-2 bg-white border border-yellow-300 text-yellow-800 rounded-lg text-xs hover:bg-yellow-100 transition cursor-pointer"
                        title="Signaler un problème">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </button>
            </div>

        @elseif($s === 'clean')
            @if($canValidate && $room->aUnMiniBar() && !$room->miniBarComplet())
                {{-- Le prochain client trouve un mini-bar complet. --}}
                <a href="{{ route('housekeeping.minibar.rooms.show', $room) }}"
                   class="w-full py-2 bg-amber-600 text-white rounded-lg text-xs font-semibold hover:bg-amber-700 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="package-plus" class="w-4 h-4"></i> Recharger le mini-bar
                </a>
            @elseif($canValidate)
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
                <p class="text-xs text-purple-700/80 text-center py-2">En attente du contrôle de la gouvernante.</p>
            @endif

        @elseif($s === 'inspected')
            @if($canValidate)
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
