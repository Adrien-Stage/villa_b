@extends('layouts.hotel')

@section('title', 'Caisse — Restaurant')

@php $fcfa = fn ($c) => $c === null ? '—' : number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA'; @endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Caisse du restaurant</h1>
        <p class="text-sm text-primary/50 mt-0.5">Une caisse par restaurant, une session par personne ; la comptabilité contresigne chaque comptage.</p>
    </div>
    @droit('restaurant.cash_register.open.creer')
        @if(! $enCours)
            <a href="{{ route('restaurant.cash_register.open') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg">
                <i data-lucide="lock-open" class="w-3.5 h-3.5"></i> Ouvrir ma caisse
            </a>
        @elseif($enCours->status === 'open')
            <a href="{{ route('restaurant.cash_register.close') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg">
                <i data-lucide="lock" class="w-3.5 h-3.5"></i> Compter ma caisse
            </a>
        @endif
    @enddroit
</div>

@foreach(['success' => 'border-green-200 bg-green-50 text-green-700', 'info' => 'border-sky-200 bg-sky-50 text-sky-800'] as $cle => $classes)
    @if(session($cle))<div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($cle) }}</div>@endif
@endforeach

<x-table :rows="$sessions" empty="Aucune session de caisse." empty-icon="calculator" caption="Sessions de caisse du restaurant">
    <x-slot:head>
        <x-table.col>Caisse</x-table.col>
        <x-table.col>Titulaire</x-table.col>
        <x-table.col hide="lg">Ouverture</x-table.col>
        <x-table.col align="right" hide="2xl">Fond</x-table.col>
        <x-table.col align="right" hide="xl">Théorique</x-table.col>
        <x-table.col align="right" hide="xl">Compté</x-table.col>
        <x-table.col align="right">Écart</x-table.col>
        <x-table.col>État</x-table.col>
    </x-slot:head>

    @foreach($sessions as $s)
        <x-table.row>
            <x-table.cell>{{ $s->pointOfSale?->name ?? 'Restaurant' }}</x-table.cell>
            <x-table.cell>{{ $s->user?->name }}</x-table.cell>
            <x-table.cell hide="lg" nowrap class="text-primary/60">{{ $s->opened_at?->format('d/m/Y H:i') }}</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap>{{ $fcfa($s->opening_amount) }}</x-table.cell>
            <x-table.cell align="right" hide="xl" nowrap>{{ $fcfa($s->theoretical_closing_amount) }}</x-table.cell>
            <x-table.cell align="right" hide="xl" nowrap>{{ $fcfa($s->actual_closing_amount) }}</x-table.cell>
            <x-table.cell align="right" nowrap class="{{ (int) $s->discrepancy_amount < 0 ? 'text-red-700' : '' }}">{{ $fcfa($s->discrepancy_amount) }}</x-table.cell>
            <x-table.cell nowrap>
                @if($s->closed_at)
                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Close{{ $s->witness ? ' — ' . $s->witness->name : '' }}</span>
                @elseif($s->isPendingReview())
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Attend la comptabilité</span>
                @elseif($s->status === 'paused')
                    <span class="rounded-full bg-accent/40 px-2 py-0.5 text-xs font-semibold text-primary/70">En pause</span>
                @else
                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800">Ouverte</span>
                @endif
            </x-table.cell>
        </x-table.row>
    @endforeach
</x-table>
@endsection
