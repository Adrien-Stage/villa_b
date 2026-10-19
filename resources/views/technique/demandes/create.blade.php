@extends('layouts.hotel')

@section('title', 'Demander une intervention')

@section('content')
@php
    $chambre = old('room_id', $choisie);
@endphp
<div class="max-w-2xl mx-auto" x-data="{ chambre: @js((string) ($chambre ?? '')), occupees: @js($rooms->filter(fn ($r) => $r->status === \App\Enums\RoomStatus::OCCUPIED)->pluck('id')->map(fn ($id) => (string) $id)->values()) }">
    <a href="{{ route('technique.demandes.index') }}" class="text-xs text-primary/60 hover:text-primary inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5" aria-hidden="true"></i> Demandes d'intervention
    </a>
    <h1 class="mt-1 text-xl font-heading font-semibold text-primary">Demander une intervention</h1>
    <p class="text-sm text-primary/60 mt-0.5 mb-6">Le service technique est alerté dès l'envoi ; vous suivez la demande jusqu'à la réparation.</p>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('technique.demandes.store') }}" class="bg-white rounded-xl border border-secondary/20 p-5 space-y-4">
        @csrf

        <label class="block">
            <span class="text-sm font-medium text-primary">Le problème <span class="text-red-600">*</span></span>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="150" placeholder="Ex. : fuite sous le lavabo, climatiseur qui ne refroidit plus…"
                   class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
            @error('title')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium text-primary">Chambre</span>
                <select name="room_id" x-model="chambre" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                    <option value="">Pas une chambre</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" @selected((string) $chambre === (string) $room->id)>Chambre {{ $room->number }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-primary">Lieu <span x-show="!chambre" class="text-red-600">*</span></span>
                <input type="text" name="location" value="{{ old('location') }}" maxlength="150" placeholder="Hall, cuisine, piscine, salle de bain…"
                       class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                @error('location')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>

        <label x-show="chambre" class="flex items-start gap-2 text-sm text-primary/80">
            <input type="hidden" name="bloquer_chambre" value="0">
            <input type="checkbox" name="bloquer_chambre" value="1" class="mt-0.5 rounded border-secondary/40" @checked(old('bloquer_chambre'))
                   :disabled="occupees.includes(chambre)">
            <span>
                Rendre la chambre indisponible pendant l'intervention
                <span x-show="occupees.includes(chambre)" class="block text-xs text-primary/50">La chambre est occupée : elle reste au client.</span>
                <span x-show="!occupees.includes(chambre)" class="block text-xs text-primary/50">Elle ne se vendra plus jusqu'à la fin de l'intervention, puis repassera au ménage.</span>
            </span>
        </label>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="text-sm font-medium text-primary">Spécialité</span>
                <select name="specialty" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                    @foreach(\App\Models\MaintenanceRequest::SPECIALTIES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(old('specialty', 'autre') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-primary">Priorité</span>
                <select name="priority" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                    @foreach(\App\Models\MaintenanceRequest::PRIORITIES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(old('priority', 'normal') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-primary">Pour le service</span>
                <select name="department" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                    @foreach($services as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(old('department') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="block">
            <span class="text-sm font-medium text-primary">Détails</span>
            <textarea name="description" rows="4" maxlength="3000" placeholder="Ce que vous avez constaté, depuis quand, ce qui a déjà été tenté…"
                      class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">{{ old('description') }}</textarea>
        </label>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('technique.demandes.index') }}" class="px-4 py-2 rounded-lg text-sm font-medium text-primary/70 hover:text-primary">Annuler</a>
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="send" class="w-4 h-4" aria-hidden="true"></i> Envoyer au service technique
            </button>
        </div>
    </form>
</div>
@endsection
