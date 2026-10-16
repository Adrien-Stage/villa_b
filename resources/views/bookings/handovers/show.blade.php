@extends('layouts.hotel')

@section('title', 'Passation de service du ' . $passation->handed_at->format('d/m/Y à H:i'))

@section('content')
@php
    $fcfa = fn ($c) => $c === null ? '—' : number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
@endphp

<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
        <div>
            <a href="{{ route('bookings.handovers.index') }}" class="text-xs text-primary/50 hover:text-primary inline-flex items-center gap-1 mb-1">
                <i data-lucide="arrow-left" class="w-3 h-3"></i> Passations de service
            </a>
            <h1 class="text-2xl font-heading font-semibold text-primary">Passation du {{ $passation->handed_at->format('d/m/Y à H:i') }}</h1>
        </div>
        <div class="flex gap-2">
            @if($passation->isPending())
                @droit('bookings.handovers.take')
                    <a href="{{ route('bookings.handovers.take', $passation) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                        <i data-lucide="hand-coins" class="w-4 h-4"></i> Prendre le service
                    </a>
                @enddroit
            @endif
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold border border-secondary/30 bg-white text-primary hover:bg-gray-50">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer
            </button>
        </div>
    </div>

    @include('bookings.handovers.partials.flash')

    <section class="bg-white rounded-xl shadow-sm p-5 grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45 mb-1">Remis par</p>
            <p class="font-semibold text-primary">{{ $passation->fromUser?->name }}</p>
            <p class="text-primary/60">le {{ $passation->handed_at->format('d/m à H:i') }} · {{ $fcfa($passation->handed_amount) }} comptés</p>
        </div>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45 mb-1">Prévu au planning</p>
            <p class="text-primary">{{ $passation->plannedUser?->name ?? 'Personne' }}</p>
            @if($passation->plannedAssignment?->shift)
                <p class="text-primary/60">quart {{ $passation->plannedAssignment->shift->name }}</p>
            @endif
        </div>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45 mb-1">Repris par</p>
            @if($passation->isPending())
                <p class="text-amber-700 font-semibold">En attente de reprise</p>
            @else
                <p class="font-semibold text-primary">{{ $passation->toUser?->name }}</p>
                <p class="text-primary/60">le {{ $passation->taken_at?->format('d/m à H:i') }} · {{ $fcfa($passation->received_amount) }} comptés</p>
                @if((int) $passation->discrepancy_amount !== 0)
                    <p class="text-red-700 font-semibold">Écart à la remise : {{ $fcfa($passation->discrepancy_amount) }}</p>
                @endif
                @if($passation->reprisHorsPlanning())
                    <p class="text-amber-700 text-xs mt-1">Repris à la place de {{ $passation->plannedUser?->name }}.</p>
                @endif
                @if($passation->reception_notes)
                    <p class="text-xs text-primary/60 mt-1">« {{ $passation->reception_notes }} »</p>
                @endif
            @endif
        </div>
    </section>

    <section class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-primary" aria-labelledby="consignes">
        <h2 id="consignes" class="font-heading font-semibold text-primary text-sm mb-2">Consignes</h2>
        @if($passation->instructions)
            <p class="text-sm text-primary/85 whitespace-pre-line">{{ $passation->instructions }}</p>
        @else
            <p class="text-sm text-primary/45">Aucune consigne particulière.</p>
        @endif
    </section>

    @include('bookings.handovers.partials.fiche', ['rapport' => $rapport])
</div>
@endsection
