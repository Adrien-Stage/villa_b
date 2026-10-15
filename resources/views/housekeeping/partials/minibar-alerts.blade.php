{{--
    Ce que les mini-bars attendent, en tête du tableau de bord : le relevé que
    la réception attend pour clore une note passe avant tout.
--}}
@php
    $aRelever = $minibar['demandes']->count() + $minibar['dus']->count();
    $aRemplir = $minibar['vides']->count() + $minibar['aRecharger']->count();
@endphp

@if($aRelever > 0 || $aRemplir > 0)
<div class="grid grid-cols-1 {{ $aRelever > 0 && $aRemplir > 0 ? 'lg:grid-cols-2' : '' }} gap-4 mb-6">
    @if($aRelever > 0)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
            <h2 class="font-heading font-semibold text-red-900 text-sm flex items-center gap-2 mb-3">
                <i data-lucide="wine" class="w-4 h-4"></i> Relevés du mini-bar à faire
            </h2>
            <ul class="space-y-2">
                @foreach($minibar['demandes'] as $sejour)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-red-900">
                            <strong>Chambre {{ $sejour->room?->number }}</strong> — départ : la réception attend le relevé
                            <span class="text-xs text-red-800/70">(demandé à {{ $sejour->minibar_check_requested_at->format('H:i') }})</span>
                        </span>
                        @if($sejour->room)
                            @droit('housekeeping.minibar.check')
                                <a href="{{ route('housekeeping.minibar.rooms.show', $sejour->room) }}"
                                   class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition">Faire le relevé</a>
                            @enddroit
                        @endif
                    </li>
                @endforeach
                @foreach($minibar['dus'] as $chambre)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-red-900">
                            <strong>Chambre {{ $chambre->number }}</strong> — client parti sans relevé : à relever avant de la marquer nettoyée
                        </span>
                        @droit('housekeeping.minibar.check')
                            <a href="{{ route('housekeeping.minibar.rooms.show', $chambre) }}"
                               class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition">Faire le relevé</a>
                        @enddroit
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($aRemplir > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h2 class="font-heading font-semibold text-amber-900 text-sm flex items-center gap-2 mb-3">
                <i data-lucide="package-plus" class="w-4 h-4"></i> Mini-bars à recharger
            </h2>
            <ul class="space-y-2">
                @foreach($minibar['vides'] as $chambre)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-amber-900"><strong>Chambre {{ $chambre->number }}</strong> — vidé par le client, séjour en cours</span>
                        <a href="{{ route('housekeeping.minibar.rooms.show', $chambre) }}"
                           class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 transition">Recharger</a>
                    </li>
                @endforeach
                @foreach($minibar['aRecharger'] as $chambre)
                    @php
                        $manque = $chambre->minibarLines->sum(fn ($l) => $l->manquant());
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-amber-900">
                            <strong>Chambre {{ $chambre->number }}</strong> — {{ $chambre->status->label() }},
                            {{ $manque }} article{{ $manque > 1 ? 's' : '' }} manquant{{ $manque > 1 ? 's' : '' }}
                        </span>
                        <a href="{{ route('housekeeping.minibar.rooms.show', $chambre) }}"
                           class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 transition">Recharger</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endif
