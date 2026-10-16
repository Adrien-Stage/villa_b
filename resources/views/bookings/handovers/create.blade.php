@extends('layouts.hotel')

@section('title', 'Remettre le tiroir — Passation de service')

@section('content')
@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
@endphp

<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="hand-coins" class="w-7 h-7 text-primary"></i>
                <span>Fin de service : remettre le tiroir</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Comptez le tiroir et laissez vos consignes. Celui qui prend le service verra tout ce que votre quart a fait
                et recomptera le tiroir avant d'ouvrir sa caisse.
            </p>
        </div>
        <a href="{{ route('bookings.cash_register.index') }}"
           class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Compta Réception
        </a>
    </div>

    @include('bookings.handovers.partials.flash')

    <form method="POST" action="{{ route('bookings.handovers.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-4">
        @csrf
        <div class="rounded-lg bg-accent/15 border border-secondary/20 px-4 py-3 text-sm text-primary/80">
            @if($prevu)
                Le planning attend <strong>{{ $prevu->user->name }}</strong> au quart {{ $prevu->shift->name }}
                ({{ $prevu->debut()->format('H:i') }} – {{ $prevu->fin()->format('H:i') }}).
                Si cette personne est empêchée, un autre réceptionniste reprendra la passation.
            @else
                Aucun réceptionniste n'est prévu au planning pour le prochain quart : la passation attendra le prochain réceptionniste qui prend son service.
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="handed_amount" class="block text-xs font-medium text-primary/60 mb-1.5">Montant compté dans le tiroir (FCFA)</label>
                <input type="number" id="handed_amount" name="handed_amount" min="0" step="1" required
                       value="{{ old('handed_amount') }}" class="{{ $champ }}">
                <p class="text-xs text-primary/45 mt-1">Attendu : {{ $fcfa($rapport['theorique']) }}. Le tiroir passe tel quel à votre collègue.</p>
            </div>
            <div class="md:col-span-2">
                <label for="instructions" class="block text-xs font-medium text-primary/60 mb-1.5">Consignes pour le quart suivant</label>
                <textarea id="instructions" name="instructions" rows="4" maxlength="3000" class="{{ $champ }}"
                          placeholder="Ex. Le client de la 104 attend un taxi à 22 h. La 203 a demandé un fer à repasser. M. Abena repasse régler son solde demain matin.">{{ old('instructions') }}</textarea>
            </div>
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
            Remettre le tiroir et terminer mon service
        </button>
    </form>

    @include('bookings.handovers.partials.fiche', ['rapport' => $rapport])
    @include('bookings.handovers.partials.reste', ['resteAFaire' => $resteAFaire])
</div>
@endsection
