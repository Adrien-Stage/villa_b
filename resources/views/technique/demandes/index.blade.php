@extends('layouts.hotel')

@section('title', "Demandes d'intervention")

@section('content')
@php
    $filtres = ['actives' => 'À traiter', 'done' => 'Terminées', 'cancelled' => 'Annulées', 'toutes' => 'Toutes'];
@endphp
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $toutVoir ? "Demandes d'intervention" : "Mes demandes d'intervention" }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $toutVoir ? "Toutes les demandes adressées au service technique." : "Les demandes que vous avez faites, et celles des services que vous dirigez." }}
            </p>
        </div>
        @droit('technique.demandes.creer')
            <a href="{{ route('technique.demandes.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i> Demander une intervention
            </a>
        @enddroit
    </div>

    @include('economat.partials.flash')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-secondary/20 p-3">
        {{-- Avant les boutons : le bouton cliqué, envoyé après, l'emporte. --}}
        <input type="hidden" name="statut" value="{{ $statut }}">
        <div class="flex rounded-lg border border-secondary/30 overflow-hidden" role="group" aria-label="État">
            @foreach($filtres as $cle => $libelle)
                <button type="submit" name="statut" value="{{ $cle }}"
                        @class(['px-3 py-1.5 text-xs font-medium', 'bg-primary text-white' => $statut === $cle, 'bg-white text-primary/70 hover:bg-gray-50' => $statut !== $cle])>{{ $libelle }}</button>
            @endforeach
        </div>
        <label class="text-xs text-primary/70">Spécialité
            <select name="specialite" class="mt-1 block rounded-lg border border-secondary/30 text-sm px-2 py-1.5" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach(\App\Models\MaintenanceRequest::SPECIALTIES as $cle => $libelle)
                    <option value="{{ $cle }}" @selected(request('specialite') === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-primary/70">Priorité
            <select name="priorite" class="mt-1 block rounded-lg border border-secondary/30 text-sm px-2 py-1.5" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach(\App\Models\MaintenanceRequest::PRIORITIES as $cle => $libelle)
                    <option value="{{ $cle }}" @selected(request('priorite') === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        @if($toutVoir)
            <label class="text-xs text-primary/70">Service
                <select name="service" class="mt-1 block rounded-lg border border-secondary/30 text-sm px-2 py-1.5" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach(\App\Models\StockRequisition::DEPARTMENTS as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(request('service') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="text-xs text-primary/70 flex-1 min-w-[10rem]">Recherche
            <input type="search" name="q" value="{{ request('q') }}" placeholder="N°, titre, chambre…" class="mt-1 block w-full rounded-lg border border-secondary/30 text-sm px-2 py-1.5">
        </label>
    </form>

    @include('technique.partials.liste', ['demandes' => $demandes, 'vide' => 'Aucune demande pour ces critères.'])
</div>
@endsection
