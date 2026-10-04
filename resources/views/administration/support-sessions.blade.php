@extends('layouts.hotel')

@section('title', 'Sessions du support')

@section('content')

<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Sessions du support</h1>
    <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
        Le support de l'éditeur entre par le mode assistance de la console, sous un compte technique qui consulte sans
        écrire. Chaque session est listée ici ; ses actions figurent au journal d'audit.
    </p>
</div>

<x-table :rows="$sessions" empty="Aucune session du support." empty-icon="life-buoy" caption="Sessions du support">
    <x-slot:head>
        <x-table.col>Technicien</x-table.col>
        <x-table.col>Début</x-table.col>
        <x-table.col>Fin</x-table.col>
        <x-table.col hide="lg">Référence</x-table.col>
        <x-table.col hide="xl">Adresse</x-table.col>
    </x-slot:head>

    @foreach($sessions as $s)
        <x-table.row>
            <x-table.cell>{{ $s->technicien }}</x-table.cell>
            <x-table.cell nowrap class="text-primary/70">{{ $s->debut->format('d/m/Y H:i') }}</x-table.cell>
            <x-table.cell nowrap class="text-primary/70">
                @if($s->fin)
                    {{ $s->fin->format('d/m/Y H:i') }}
                @else
                    <span class="text-primary/45">sans déconnexion enregistrée</span>
                @endif
            </x-table.cell>
            <x-table.cell hide="lg" class="font-mono text-primary/50">{{ $s->reference ?: '—' }}</x-table.cell>
            <x-table.cell hide="xl" class="font-mono text-primary/50">{{ $s->ip_address ?: '—' }}</x-table.cell>
        </x-table.row>
    @endforeach
</x-table>

@endsection
