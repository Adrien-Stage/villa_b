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
            @if($canValidate)
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
