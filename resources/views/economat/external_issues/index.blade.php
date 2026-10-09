@extends('layouts.hotel')

@section('title', 'Sorties hors établissement — Économat')

@section('content')
@php
    $fcfa = fn (int $v) => number_format($v / 100, 0, ',', ' ');
    $champ = 'mt-1 rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-sm text-primary outline-none focus:border-primary';
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="log-out" class="w-7 h-7 text-primary"></i>
                <span>Sorties hors établissement</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Le matériel qui quitte le magasin sans servir l'hôtel : prêt, réparation à l'extérieur, don, cession, transfert, restitution.
                Chaque bon garde qui l'a emporté et porte sa signature.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.external_issues.export')
                <x-barre-export route="economat.external_issues.export" />
            @enddroit
            @droit('economat.external_issues.creer')
                <a href="{{ route('economat.external_issues.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle sortie
                </a>
            @enddroit
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Une date précise, ou une période : en GET, repris tels quels par l'export. --}}
    <form method="GET" action="{{ route('economat.external_issues.index') }}" class="rounded-xl border border-secondary/20 bg-white px-4 py-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label for="recherche" class="block text-[11px] font-medium text-primary/50">N°, nom, structure, téléphone</label>
                <input type="search" name="recherche" id="recherche" value="{{ request('recherche') }}" placeholder="BSE-2026-0001, M. Talla…" class="{{ $champ }} w-full">
            </div>
            <div>
                <label for="date" class="block text-[11px] font-medium text-primary/50">Le (une date)</label>
                <input type="date" name="date" id="date" value="{{ request('date') }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="du" class="block text-[11px] font-medium text-primary/50">Ou du</label>
                <input type="date" name="du" id="du" value="{{ request('du') }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="au" class="block text-[11px] font-medium text-primary/50">au</label>
                <input type="date" name="au" id="au" value="{{ request('au') }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="motif" class="block text-[11px] font-medium text-primary/50">Motif</label>
                <select name="motif" id="motif" class="{{ $champ }}">
                    <option value="">Tous</option>
                    @foreach(\App\Models\ExternalIssue::REASONS as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(request('motif') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statut" class="block text-[11px] font-medium text-primary/50">Statut</label>
                <select name="statut" id="statut" class="{{ $champ }}">
                    <option value="">Tous</option>
                    @foreach(\App\Models\ExternalIssue::STATUSES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(request('statut') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-surface-dark">Filtrer</button>
            <a href="{{ route('economat.external_issues.index', ['date' => today()->toDateString()]) }}" class="text-xs text-primary/60 underline hover:text-primary pb-2">Aujourd'hui</a>
            @if($filtres)
                <a href="{{ route('economat.external_issues.index') }}" class="text-xs text-primary/60 underline hover:text-primary pb-2">Réinitialiser</a>
            @endif
        </div>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Bons validés</p>
            <p class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['bons'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-red-700">Valeur sortie</p>
            <p class="text-2xl font-bold font-mono text-red-700 mt-1">{{ $fcfa($stats['valeur']) }} <span class="text-xs font-sans">F</span></p>
            <p class="text-[11px] text-primary/45">au coût moyen à la sortie</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-sky-700">À rendre</p>
            <p class="text-2xl font-bold font-mono text-sky-800 mt-1">{{ $stats['a_rendre'] }}</p>
            <p class="text-[11px] text-primary/45">prêts et réparations avec retour prévu</p>
        </div>
        <a href="{{ route('economat.external_issues.index', ['retour' => 'en_retard']) }}"
           class="bg-white rounded-xl border p-4 transition-colors {{ $stats['en_retard'] > 0 ? 'border-amber-300 hover:bg-amber-50' : 'border-secondary/20' }}">
            <p class="text-[11px] uppercase tracking-wider text-amber-700">Retours en retard</p>
            <p class="text-2xl font-bold font-mono text-amber-800 mt-1">{{ $stats['en_retard'] }}</p>
            <p class="text-[11px] text-primary/45">date de retour dépassée</p>
        </a>
    </div>

    <x-table :rows="$sorties" :empty="$filtres ? 'Aucune sortie pour ces critères.' : 'Aucune sortie hors établissement enregistrée.'" empty-icon="log-out" caption="Sorties hors établissement">
        <x-slot:head>
            <x-table.col>N° de bon</x-table.col>
            <x-table.col>Sortie le</x-table.col>
            <x-table.col>Emporté par</x-table.col>
            <x-table.col hide="lg">Motif</x-table.col>
            <x-table.col align="right" hide="xl">Articles</x-table.col>
            <x-table.col align="right">Valeur</x-table.col>
            <x-table.col hide="xl">Retour prévu</x-table.col>
            <x-table.col>Statut</x-table.col>
            <x-table.col hide="2xl">Validé par</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($sorties as $s)
            <x-table.row :href="route('economat.external_issues.show', $s)" :muted="$s->isCancelled()">
                <x-table.cell nowrap>
                    <a href="{{ route('economat.external_issues.show', $s) }}" class="font-mono font-bold text-primary hover:underline">{{ $s->number }}</a>
                </x-table.cell>
                <x-table.cell nowrap class="font-mono text-xs text-primary/60">{{ $s->issued_at->format('d/m/Y H:i') }}</x-table.cell>
                <x-table.cell>
                    <span class="font-medium text-primary">{{ $s->beneficiary_name }}</span>
                    @if($s->beneficiary_organisation)<span class="block text-[11px] text-primary/50">{{ $s->beneficiary_organisation }}</span>@endif
                </x-table.cell>
                <x-table.cell hide="lg" class="text-xs text-primary/70">{{ $s->reasonLabel() }}</x-table.cell>
                <x-table.cell align="right" hide="xl" class="font-mono text-primary/70">{{ $s->lines_count }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono font-semibold text-primary">{{ $fcfa((int) $s->total_value) }} F</x-table.cell>
                <x-table.cell hide="xl" nowrap class="text-xs {{ $s->retourEnRetard() ? 'text-amber-800 font-semibold' : 'text-primary/60' }}">
                    {{ $s->expected_return_at?->format('d/m/Y') ?? '—' }}
                    @if($s->retourEnRetard()) · en retard @endif
                </x-table.cell>
                <x-table.cell nowrap>
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $s->isCancelled() ? 'border-gray-200 bg-gray-100 text-gray-600' : 'border-red-200 bg-red-50 text-red-700' }}">{{ $s->statusLabel() }}</span>
                </x-table.cell>
                <x-table.cell hide="2xl" class="text-xs text-primary/60">{{ $s->issuedBy?->name ?? '—' }}</x-table.cell>
                <x-table.actions :label="'Actions pour le bon '.$s->number">
                    <x-table.action :href="route('economat.external_issues.show', $s)" icon="eye">Détails</x-table.action>
                    <x-table.action :href="route('economat.external_issues.print', $s)" icon="printer" target="_blank">Imprimer le bon</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
