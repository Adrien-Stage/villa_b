@extends('layouts.hotel')

@section('title', 'Banquet ' . $banquet->reference)

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $libellesModes = ['cash' => 'Espèces', 'mobile_money' => 'Mobile money', 'card' => 'Carte', 'transfer' => 'Virement', 'other' => 'Autre'];
    $couleurs = ['devis' => 'bg-amber-50 text-amber-800', 'confirme' => 'bg-sky-50 text-sky-800', 'realise' => 'bg-violet-50 text-violet-800', 'solde' => 'bg-green-50 text-green-700', 'annule' => 'bg-gray-100 text-gray-500'];
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('restaurant.banquets.index') }}" class="text-xs text-primary/50 hover:text-primary">&larr; Banquets</a>
        <h1 class="font-heading text-2xl font-semibold text-primary">{{ $banquet->title }}</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $banquet->reference }} · {{ $banquet->event_date->format('d/m/Y') }}
            @if($banquet->start_time) · {{ $banquet->start_time }}@if($banquet->end_time)–{{ $banquet->end_time }}@endif @endif
            · {{ $banquet->pointOfSale?->name }}@if($banquet->space) · {{ $banquet->space->name }}@endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $couleurs[$banquet->status] ?? '' }}">{{ $banquet->libelleStatut() }}</span>
        @droit('restaurant.banquets.status')
            @php
                $actions = match ($banquet->status) {
                    'devis' => ['confirme' => 'Confirmer', 'annule' => 'Annuler'],
                    'confirme' => ['realise' => 'Marquer réalisé', 'annule' => 'Annuler'],
                    default => [],
                };
            @endphp
            @foreach($actions as $statut => $libelle)
                <form method="POST" action="{{ route('restaurant.banquets.status', $banquet) }}"
                    @if($statut === 'annule') onsubmit="return confirm('Annuler ce banquet ?')" @endif>
                    @csrf
                    <input type="hidden" name="status" value="{{ $statut }}">
                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $statut === 'annule' ? 'border border-red-200 bg-white text-red-600 hover:bg-red-50' : 'bg-primary text-white hover:opacity-95' }}">{{ $libelle }}</button>
                </form>
            @endforeach
        @enddroit
        @if($banquet->estModifiable())
            @droit('restaurant.banquets.modifier')
                <button type="button" onclick="document.getElementById('banquet-edit').classList.remove('hidden')"
                    class="rounded-lg border border-secondary/25 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/20">Modifier</button>
            @enddroit
        @endif
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Prestation</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <dt class="text-primary/50">Client</dt>
            <dd class="text-primary">{{ $banquet->client_name }}@if($banquet->client_phone) · {{ $banquet->client_phone }}@endif</dd>
            @if($banquet->client_email)
                <dt class="text-primary/50">Email</dt><dd class="text-primary">{{ $banquet->client_email }}</dd>
            @endif
            <dt class="text-primary/50">Couverts</dt>
            <dd class="text-primary">{{ $banquet->covers }} × {{ $fcfa($banquet->price_per_cover) }}</dd>
            @if($banquet->extras_amount > 0)
                <dt class="text-primary/50">Suppléments</dt><dd class="text-primary">{{ $fcfa($banquet->extras_amount) }}</dd>
            @endif
            <dt class="text-primary/50">Total</dt>
            <dd class="font-semibold text-primary">{{ $fcfa($banquet->total_amount) }}</dd>
            <dt class="text-primary/50">Acompte demandé</dt>
            <dd class="text-primary">{{ $fcfa($banquet->deposit_required) }}</dd>
            <dt class="text-primary/50">Établi par</dt>
            <dd class="text-primary">{{ $banquet->createdBy?->name ?? '—' }}, le {{ $banquet->created_at->format('d/m/Y') }}</dd>
        </dl>
        @if($banquet->menu)
            <h3 class="mt-5 mb-1 text-xs font-bold uppercase tracking-wider text-primary/60">Menu</h3>
            <p class="whitespace-pre-line text-sm text-primary/80">{{ $banquet->menu }}</p>
        @endif
        @if($banquet->notes)
            <h3 class="mt-5 mb-1 text-xs font-bold uppercase tracking-wider text-primary/60">Notes</h3>
            <p class="whitespace-pre-line text-sm text-primary/70">{{ $banquet->notes }}</p>
        @endif
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Règlements</h2>
        <p class="text-sm text-primary">Encaissé : <strong>{{ $fcfa($encaisse) }}</strong></p>
        <p class="mb-3 text-sm text-primary">Reste dû : <strong>{{ $fcfa($resteDu) }}</strong></p>

        <ul class="mb-4 space-y-1.5 text-xs">
            @forelse($banquet->payments as $reglement)
                <li class="flex justify-between gap-2 border-b border-secondary/10 pb-1.5">
                    <span class="text-primary/70">{{ $reglement->paid_at->format('d/m/Y') }} · {{ $reglement->kind === 'acompte' ? 'Acompte' : 'Solde' }} · {{ $libellesModes[$reglement->payment_method] ?? $reglement->payment_method }}</span>
                    <span class="font-semibold text-primary">{{ $fcfa($reglement->amount) }}</span>
                </li>
            @empty
                <li class="text-primary/45">Aucun règlement.</li>
            @endforelse
        </ul>

        @if($resteDu > 0 && ! in_array($banquet->status, ['annule', 'solde'], true))
            @droit('restaurant.banquets.payments.creer')
                @if(! $caisse)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Ouvrez votre caisse pour encaisser un règlement.</p>
                @else
                    <form method="POST" action="{{ route('restaurant.banquets.payments.store', $banquet) }}" class="space-y-2">
                        @csrf
                        <label class="block">
                            <span class="text-xs text-primary/60">Montant (FCFA)</span>
                            <input type="number" name="amount" required min="1" max="{{ (int) floor($resteDu / 100) }}" value="{{ old('amount') }}"
                                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                        </label>
                        <label class="block">
                            <span class="text-xs text-primary/60">Paiement</span>
                            <select name="payment_method" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                                @foreach($paymentMethods as $methode)
                                    <option value="{{ $methode }}">{{ $libellesModes[$methode] ?? $methode }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Encaisser</button>
                    </form>
                @endif
            @enddroit
        @endif
    </section>
</div>

@if($banquet->estModifiable())
    @droit('restaurant.banquets.modifier')
        <x-modal id="banquet-edit" title="Modifier {{ $banquet->reference }}" max-width="max-w-2xl" formAction="{{ route('restaurant.banquets.update', $banquet) }}">
            @method('PUT')
            @include('restaurant.banquets._champs', ['b' => $banquet, 'restaurantParDefaut' => null])
            <x-slot:footer>
                <button type="button" onclick="document.getElementById('banquet-edit').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
            </x-slot:footer>
        </x-modal>
    @enddroit
@endif
@endsection
