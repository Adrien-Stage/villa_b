@extends('layouts.hotel')

@section('title', 'Interventions')

@section('content')

<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Interventions de l'administrateur</h1>
    <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
        L'administrateur consulte tout et n'écrit que la configuration et les comptes. Encaisser, valider, comptabiliser :
        il ne le fait que pendant une intervention déclarée — motif, durée, services. Le manager est prévenu, chaque action
        est marquée au journal, et la trace part à la console d'orchestration.
    </p>
</div>

@foreach(['success' => 'border-green-200 bg-green-50 text-green-700', 'error' => 'border-red-200 bg-red-50 text-red-700'] as $cle => $classes)
    @if(session($cle))
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($cle) }}</div>
    @endif
@endforeach

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
        </ul>
    </div>
@endif

@droit('interventions.creer')
    @if($enCours)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Intervention #{{ $enCours->id }} en cours jusqu'à {{ $enCours->fin_prevue->format('H:i') }}</p>
            <p class="mt-1">{{ implode(', ', $enCours->libellesPerimetres()) }} — {{ $enCours->motif }}</p>
            <form method="POST" action="{{ route('interventions.terminer', $enCours) }}" class="mt-3">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">Terminer l'intervention</button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ route('interventions.store') }}" class="mb-6 bg-white rounded-xl shadow-sm p-5 space-y-4">
            @csrf
            <h2 class="font-heading text-lg font-semibold text-primary">Ouvrir une intervention</h2>
            <div>
                <label for="i-motif" class="block text-xs font-semibold text-primary/70 mb-1">Motif</label>
                <textarea id="i-motif" name="motif" rows="2" required minlength="10" maxlength="500"
                          placeholder="Ce qui rend l'intervention nécessaire — il part au manager et à la console."
                          class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm">{{ old('motif') }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                <div>
                    <label for="i-duree" class="block text-xs font-semibold text-primary/70 mb-1">Durée</label>
                    <select id="i-duree" name="duree" class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm">
                        @foreach($durees as $minutes)
                            <option value="{{ $minutes }}" @selected((int) old('duree', 30) === $minutes)>
                                {{ $minutes < 60 ? $minutes . ' minutes' : ($minutes / 60) . ' heure' . ($minutes > 60 ? 's' : '') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <fieldset>
                    <legend class="block text-xs font-semibold text-primary/70 mb-1">Services concernés</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($perimetres as $cle => [$libelle])
                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs text-primary">
                                <input type="checkbox" name="perimetres[]" value="{{ $cle }}" @checked(in_array($cle, old('perimetres', []), true)) class="rounded border-secondary/40">
                                {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-xs font-semibold">Ouvrir l'intervention</button>
            </div>
        </form>
    @endif
@enddroit

<x-table :rows="$interventions" empty="Aucune intervention." empty-icon="siren" caption="Interventions de l'administrateur">
    <x-slot:head>
        <x-table.col>N°</x-table.col>
        <x-table.col>Administrateur</x-table.col>
        <x-table.col hide="xl">Services</x-table.col>
        <x-table.col>Motif</x-table.col>
        <x-table.col hide="lg">Période</x-table.col>
        <x-table.col>Console</x-table.col>
    </x-slot:head>

    @foreach($interventions as $i)
        <x-table.row>
            <x-table.cell nowrap class="font-mono text-primary/60">#{{ $i->id }}</x-table.cell>
            <x-table.cell>{{ $i->user?->name }}</x-table.cell>
            <x-table.cell hide="xl" class="text-primary/80">{{ implode(', ', $i->libellesPerimetres()) }}</x-table.cell>
            <x-table.cell class="max-w-md text-primary/80">{{ $i->motif }}</x-table.cell>
            <x-table.cell hide="lg" nowrap class="text-xs text-primary/60">
                {{ $i->debut->format('d/m/Y H:i') }} →
                @if($i->estEnCours())
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">en cours, jusqu'à {{ $i->fin_prevue->format('H:i') }}</span>
                @else
                    {{ $i->fin_reelle?->format('H:i') }}
                    <span class="text-primary/45">({{ $i->cloture === 'expiree' ? 'durée écoulée' : 'terminée' }})</span>
                @endif
            </x-table.cell>
            <x-table.cell nowrap>
                @if($i->erp_a_transmettre)
                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">En attente</span>
                @elseif($i->erp_tardive)
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Transmise en retard</span>
                @else
                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Transmise</span>
                @endif
            </x-table.cell>
        </x-table.row>
    @endforeach
</x-table>

@endsection
