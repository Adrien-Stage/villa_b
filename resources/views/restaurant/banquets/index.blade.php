@extends('layouts.hotel')

@section('title', 'Banquets')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $couleurs = ['devis' => 'bg-amber-50 text-amber-800', 'confirme' => 'bg-sky-50 text-sky-800', 'realise' => 'bg-violet-50 text-violet-800', 'solde' => 'bg-green-50 text-green-700', 'annule' => 'bg-gray-100 text-gray-500'];
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Banquets</h1>
        <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
            Événements réservés dans l'un ou l'autre restaurant : devis, confirmation à l'encaissement de l'acompte, réalisation, solde.
        </p>
    </div>
    @droit('restaurant.banquets.creer')
        <button type="button" onclick="document.getElementById('banquet-create').classList.remove('hidden')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nouveau devis
        </button>
    @enddroit
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mb-4 flex flex-wrap gap-2">
    @foreach(['a_venir' => 'À venir', 'passes' => 'Passés', 'tous' => 'Tous'] as $cle => $libelle)
        <a href="{{ route('restaurant.banquets.index', ['vue' => $cle]) }}"
            class="rounded-full px-3 py-1.5 text-xs font-medium {{ $vue === $cle ? 'bg-primary text-white' : 'border border-secondary/30 bg-white text-primary/60 hover:text-primary' }}">{{ $libelle }}</a>
    @endforeach
</div>

<x-table :rows="$banquets" empty="Aucun banquet." empty-icon="party-popper" caption="Banquets">
    <x-slot:head>
        <x-table.col>Date</x-table.col>
        <x-table.col>Événement</x-table.col>
        <x-table.col hide="xl">Restaurant · salle</x-table.col>
        <x-table.col align="right" hide="lg">Couverts</x-table.col>
        <x-table.col align="right">Total</x-table.col>
        <x-table.col align="right" hide="2xl">Encaissé</x-table.col>
        <x-table.col>Statut</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($banquets as $banquet)
        <x-table.row :href="route('restaurant.banquets.show', $banquet)" :muted="$banquet->status === 'annule'">
            <x-table.cell nowrap>
                <span class="font-semibold text-primary">{{ $banquet->event_date->format('d/m/Y') }}</span>
                @if($banquet->start_time)<span class="text-primary/50"> {{ $banquet->start_time }}</span>@endif
            </x-table.cell>
            <x-table.cell>
                <a href="{{ route('restaurant.banquets.show', $banquet) }}" class="font-semibold text-primary hover:underline">{{ $banquet->title }}</a>
                <p class="text-[11px] text-primary/45">{{ $banquet->reference }} · {{ $banquet->client_name }}</p>
            </x-table.cell>
            <x-table.cell hide="xl" class="text-primary/70">{{ $banquet->pointOfSale?->name }}@if($banquet->space) · {{ $banquet->space->name }}@endif</x-table.cell>
            <x-table.cell align="right" hide="lg">{{ $banquet->covers }}</x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold">{{ $fcfa($banquet->total_amount) }}</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap>{{ $fcfa($banquet->total_encaisse) }}</x-table.cell>
            <x-table.cell nowrap>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $couleurs[$banquet->status] ?? '' }}">{{ $banquet->libelleStatut() }}</span>
            </x-table.cell>
            <x-table.actions :label="'Actions pour le banquet '.$banquet->reference">
                <x-table.action :href="route('restaurant.banquets.show', $banquet)" icon="eye">Ouvrir</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>

@droit('restaurant.banquets.creer')
    <x-modal id="banquet-create" title="Nouveau devis de banquet" max-width="max-w-2xl" formAction="{{ route('restaurant.banquets.store') }}">
        @include('restaurant.banquets._champs', ['b' => null])
        <x-slot:footer>
            <button type="button" onclick="document.getElementById('banquet-create').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer le devis</button>
        </x-slot:footer>
    </x-modal>
    @if($errors->any() && old('title') !== null && ! request()->route('banquet'))
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('banquet-create')?.classList.remove('hidden'));</script>
    @endif
@enddroit
@endsection
