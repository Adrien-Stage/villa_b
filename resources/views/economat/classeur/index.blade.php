@extends('layouts.hotel')

@section('title', 'Classeur Excel — Économat')

@section('content')
@php
    $magasins = collect([['id' => '', 'nom' => 'Magasin central (économat)']])
        ->merge($depots->map(fn ($d) => ['id' => $d->id, 'nom' => 'Dépôt « ' . $d->name . ' »']));
@endphp
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-heading font-semibold text-primary">Classeur Excel</h1>
        <p class="text-sm text-primary/60 mt-0.5">
            Exportez un magasin — articles, fournisseurs, mouvements — pour le gérer dans Excel, puis réimportez le fichier.
        </p>
    </div>

    @include('economat.partials.flash')

    <div class="grid gap-4 md:grid-cols-2 mb-8">
        {{-- Exporter --}}
        <form method="GET" action="{{ route('economat.classeur.export') }}" class="bg-white rounded-xl border border-secondary/20 p-5 flex flex-col gap-4">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                    <i data-lucide="file-down" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="font-heading font-semibold text-primary">Exporter</h2>
                    <p class="text-xs text-primary/60 mt-0.5">
                        Le classeur se suffit à lui-même : listes déroulantes, stock recalculé par formule, synthèse par catégorie.
                    </p>
                </div>
            </div>
            <label class="block">
                <span class="text-xs font-medium text-primary/70">Magasin</span>
                <select name="depot" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                    @foreach($magasins as $m)
                        <option value="{{ $m['id'] }}" @selected((string) $choisi === (string) $m['id'])>{{ $m['nom'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="mt-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="download" class="w-4 h-4" aria-hidden="true"></i> Télécharger le classeur
            </button>
        </form>

        {{-- Importer --}}
        @droit('economat.classeur.import')
            <form method="POST" action="{{ route('economat.classeur.import') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-secondary/20 p-5 flex flex-col gap-4">
                @csrf
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center shrink-0">
                        <i data-lucide="file-up" class="w-5 h-5" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="font-heading font-semibold text-primary">Importer</h2>
                        <p class="text-xs text-primary/60 mt-0.5">
                            Un aperçu montre d'abord ce qui sera créé et les erreurs ligne par ligne : rien n'est écrit avant votre confirmation.
                        </p>
                    </div>
                </div>
                <label class="block">
                    <span class="text-xs font-medium text-primary/70">Magasin</span>
                    <select name="depot" class="mt-1 w-full rounded-lg border border-secondary/30 text-sm px-3 py-2">
                        @foreach($magasins as $m)
                            <option value="{{ $m['id'] }}" @selected((string) $choisi === (string) $m['id'])>{{ $m['nom'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-primary/70">Fichier (.xlsx, .xls, .ods)</span>
                    <input type="file" name="fichier" required accept=".xlsx,.xls,.ods"
                           class="mt-1 block w-full text-sm text-primary/70 file:mr-3 file:rounded-lg file:border-0 file:bg-accent/40 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-accent/60">
                    @error('fichier')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="mt-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="scan-search" class="w-4 h-4" aria-hidden="true"></i> Analyser le fichier
                </button>
            </form>
        @enddroit
    </div>

    <div class="mb-4 px-4 py-3 bg-accent/10 border border-secondary/20 text-primary/70 text-xs rounded-lg flex gap-2">
        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true"></i>
        <p>
            Vers un dépôt, ce qui entre passe par le magasin central puis lui est livré, comme une demande livrée : le transfert ne coûte rien au service.
            La charge naît à la sortie vers un bénéficiaire, ou à l'inventaire du dépôt.
            Une ligne exportée porte son N° : réimportée, elle n'est pas rejouée.
        </p>
    </div>

    <x-table :rows="$imports" empty="Aucun import pour l'instant." empty-icon="file-spreadsheet" caption="Imports de classeurs">
        <x-slot:head>
            <x-table.col>Import</x-table.col>
            <x-table.col hide="md">Magasin</x-table.col>
            <x-table.col hide="lg">Fichier</x-table.col>
            <x-table.col>Statut</x-table.col>
            <x-table.col align="right" hide="sm">Mouvements</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($imports as $import)
            <x-table.row>
                <x-table.cell>
                    <a href="{{ route('economat.classeur.show', $import) }}" class="font-medium text-primary hover:underline">{{ $import->reference }}</a>
                    <p class="text-[11px] text-primary/50">{{ $import->created_at->format('d/m/Y H:i') }} · {{ $import->creator?->name ?? '—' }}</p>
                </x-table.cell>
                <x-table.cell hide="md" class="text-xs text-primary/70">{{ $import->magasinLabel() }}</x-table.cell>
                <x-table.cell hide="lg" class="text-xs text-primary/60 max-w-[14rem] truncate">{{ $import->file_name }}</x-table.cell>
                <x-table.cell>
                    <span @class([
                        'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold border',
                        'border-green-200 bg-green-50 text-green-700' => ! $import->isDraft(),
                        'border-amber-200 bg-amber-50 text-amber-800' => $import->isDraft(),
                    ])>{{ $import->statusLabel() }}</span>
                </x-table.cell>
                <x-table.cell align="right" hide="sm" class="text-primary/70">{{ $import->analysis['resume']['mouvements'] ?? 0 }}</x-table.cell>
                <x-table.actions :label="'Actions pour l\'import '.$import->reference">
                    <x-table.action :href="route('economat.classeur.show', $import)" icon="eye">Ouvrir</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
