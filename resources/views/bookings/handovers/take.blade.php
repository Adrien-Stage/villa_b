@extends('layouts.hotel')

@section('title', 'Prendre le service — Passation')

@section('content')
@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
    $aLaPlace = $passation->planned_user_id !== null && $passation->planned_user_id !== auth()->id();
@endphp

<div class="max-w-6xl mx-auto space-y-5">
    <div>
        <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
            <i data-lucide="hand-coins" class="w-7 h-7 text-primary"></i>
            <span>Prendre le service</span>
        </h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            <strong>{{ $passation->fromUser?->name }}</strong> a terminé son service à {{ $passation->handed_at->format('H:i') }}
            et vous remet le tiroir. Lisez ses consignes et ce que son quart a fait, recomptez le tiroir : votre caisse s'ouvrira avec ce montant.
        </p>
    </div>

    @include('bookings.handovers.partials.flash')

    @if($aLaPlace)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900" role="status">
            Le planning attendait <strong>{{ $passation->plannedUser?->name }}</strong> pour ce quart. En reprenant cette passation,
            vous prenez le service à sa place ; ce sera inscrit sur la passation.
        </div>
    @endif

    <section class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-primary" aria-labelledby="consignes">
        <h2 id="consignes" class="font-heading font-semibold text-primary text-sm mb-2">Consignes de {{ $passation->fromUser?->name }}</h2>
        @if($passation->instructions)
            <p class="text-sm text-primary/85 whitespace-pre-line">{{ $passation->instructions }}</p>
        @else
            <p class="text-sm text-primary/45">Aucune consigne particulière.</p>
        @endif
    </section>

    @include('bookings.handovers.partials.reste', ['resteAFaire' => $resteAFaire])
    @include('bookings.handovers.partials.fiche', ['rapport' => $rapport])

    @droit('bookings.handovers.take.creer')
    <form method="POST" action="{{ route('bookings.handovers.take.store', $passation) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-4">
        @csrf
        <h2 class="font-heading font-semibold text-primary text-sm">Recompter le tiroir et ouvrir ma caisse</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-primary/60 mb-1.5">Remis par {{ $passation->fromUser?->name }}</p>
                <p class="text-lg font-heading font-semibold text-primary tabular-nums">{{ $fcfa($passation->handed_amount) }}</p>
            </div>
            <div>
                <label for="received_amount" class="block text-xs font-medium text-primary/60 mb-1.5">Montant que vous comptez (FCFA)</label>
                <input type="number" id="received_amount" name="received_amount" min="0" step="1" required
                       value="{{ old('received_amount') }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="reception_notes" class="block text-xs font-medium text-primary/60 mb-1.5">Remarque (optionnel)</label>
                <input type="text" id="reception_notes" name="reception_notes" maxlength="2000" value="{{ old('reception_notes') }}"
                       placeholder="Ex. billet de 10 000 abîmé" class="{{ $champ }}">
            </div>
        </div>
        <label class="flex items-start gap-2 text-sm text-primary">
            <input type="checkbox" name="lu" value="1" required class="mt-0.5 rounded border-secondary/40 text-primary focus:ring-primary">
            <span>J'ai pris connaissance des consignes, des opérations du quart et de ce qui reste à faire.</span>
        </label>
        <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
            Prendre le service
        </button>
    </form>
    @enddroit
</div>
@endsection
