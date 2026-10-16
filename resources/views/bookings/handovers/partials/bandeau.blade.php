{{--
    Le tiroir de la réception attend : bandeau pour le réceptionniste qui n'a
    pas encore de caisse, tant que personne n'a repris la passation.
--}}
@php
    $passationEnAttente = null;
    if (auth()->check() && auth()->user()->exerce(['reception'])
        && ! request()->routeIs('bookings.handovers.*')
        && app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'bookings.handovers.take')) {
        $sansCaisse = ! \App\Models\CashRegisterSession::query()
            ->where('user_id', auth()->id())->where('module', 'reception')->whereNull('closed_at')->exists();
        if ($sansCaisse) {
            $passationEnAttente = app(\App\Services\PassationService::class)->enAttente()
                ->first(fn ($p) => $p->from_user_id !== auth()->id());
        }
    }
@endphp

@if($passationEnAttente)
    <div class="bg-amber-500 text-white px-4 lg:px-8 py-2 flex flex-wrap items-center justify-between gap-3 flex-shrink-0 print:hidden" role="status">
        <p class="text-xs font-semibold flex items-center gap-2">
            <i data-lucide="hand-coins" class="w-4 h-4 shrink-0"></i>
            {{ $passationEnAttente->fromUser?->name }} a remis le tiroir de la réception à {{ $passationEnAttente->handed_at->format('H:i') }}
            @if($passationEnAttente->planned_user_id === auth()->id()) — le planning vous attend @endif
            : prenez connaissance de la passation avant d'ouvrir votre caisse.
        </p>
        <a href="{{ route('bookings.handovers.take', $passationEnAttente) }}" class="shrink-0 rounded-md bg-white/20 hover:bg-white/30 px-3 py-1 text-xs font-bold transition">Prendre le service</a>
    </div>
@endif
