@extends('layouts.hotel')

@section('title', 'Livres et arrêté comptable')

@section('content')
<h1 class="text-2xl font-semibold text-primary mb-4">Livres et arrêté comptable</h1>
@include('accounting.ledger.partials.nav')

@foreach (array_filter([session('success'), session('error'), $errors->first()]) as $message)
    <p class="mb-4 rounded-lg bg-accent/20 p-3 text-sm text-primary" role="status">{{ $message }}</p>
@endforeach

@php
    $permission = app(\App\Services\PermissionResolver::class);
    $canExport = $permission->allows(auth()->user(), 'accounting.ledger.statutory.export');
    $canClose = $permission->allows(auth()->user(), 'accounting.ledger.statutory.close');
@endphp
@if ($canExport)
    <form action="{{ route('accounting.ledger.statutory.export') }}" class="bg-white rounded-xl border border-secondary/20 p-4 mb-5">
        <h2 class="font-semibold text-primary mb-3">Éditions par période</h2>
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm">Édition
                <select name="kind" class="block rounded-lg border-secondary/30" required>
                    <option value="books">Livre-journal, balance et grand livre</option>
                    <option value="fiscal">Synthèse fiscale et pièces de période</option>
                </select>
            </label>
            <label class="text-sm">Du
                <input name="from" type="date" value="{{ today()->startOfMonth()->toDateString() }}" class="block rounded-lg border-secondary/30" required>
            </label>
            <label class="text-sm">Au
                <input name="to" type="date" value="{{ today()->toDateString() }}" class="block rounded-lg border-secondary/30" required>
            </label>
            <button name="format" value="excel" class="rounded-lg bg-primary px-3 py-2 text-sm text-white">Excel</button>
            <button name="format" value="pdf" class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">PDF</button>
            <button name="format" value="csv" class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">CSV du journal</button>
        </div>
    </form>
@endif

<section class="bg-white rounded-xl border border-secondary/20 p-4">
    <h2 class="font-semibold text-primary mb-3">Arrêté annuel</h2>
    @if ($years->isEmpty())
        <p class="text-sm">Ouvrez un exercice dans l’onglet Périodes.</p>
    @else
        <form class="flex flex-wrap items-end gap-3">
            <label class="text-sm">Exercice
                <select name="year" class="block rounded-lg border-secondary/30">
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected($selected?->id === $year->id)>{{ $year->label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">Afficher</button>
        </form>
    @endif

    @if ($selected && $canExport)
        <form action="{{ route('accounting.ledger.statutory.export') }}" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="kind" value="annual">
            <input type="hidden" name="year" value="{{ $selected->id }}">
            @if (!$archive)
                <label class="text-sm">Référentiel du projet
                    <select name="regime" class="block rounded-lg border-secondary/30">
                        @foreach (\App\Services\FiscalStatements::REGIMES as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button name="format" value="excel" class="rounded-lg bg-primary px-3 py-2 text-sm text-white">Excel</button>
            <button name="format" value="pdf" class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">PDF</button>
        </form>
    @endif

    @if ($archive)
        <p class="mt-4 text-sm">Arrêté archivé le {{ $archive->created_at->format('d/m/Y') }}. Conservation jusqu’au {{ $archive->retain_until->format('d/m/Y') }}.</p>
        @if ($permission->allows(auth()->user(), 'accounting.ledger.statutory.dsf'))
            <details class="border-t border-secondary/15 mt-4 pt-4 text-sm">
                <summary class="cursor-pointer font-semibold">Préremplir un modèle DSF</summary>
                <form action="{{ route('accounting.ledger.statutory.dsf', $selected) }}" method="post" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <p>Le modèle doit correspondre à l’exercice et au référentiel de la DGI. Vérifiez le classeur avant de le joindre à la déclaration.</p>
                    <label class="block">Modèle DGI (.xlsx)
                        <input type="file" name="template" accept=".xlsx" required class="block mt-1">
                    </label>
                    <label class="block">Correspondances validées (.csv)
                        <input type="file" name="mapping" accept=".csv" required class="block mt-1">
                    </label>
                    <p>Colonnes du CSV · <code>{{ implode(';', \App\Services\DsfWorkbook::HEADER) }}</code>. Valeurs possibles · <code>debit</code>, <code>credit</code>, <code>opening</code>, <code>closing</code>, <code>closing_credit</code>.</p>
                    <button class="rounded-lg bg-primary px-3 py-2 text-white">Télécharger le projet DSF</button>
                </form>
            </details>
        @endif
    @elseif ($canClose && $selected && $selected->ends_on->isBefore(today()))
        <form action="{{ route('accounting.ledger.statutory.close', $selected) }}" method="post" enctype="multipart/form-data" class="mt-5 space-y-4 border-t border-secondary/15 pt-4">
            @csrf
            <label class="block text-sm">Référentiel applicable
                <select name="regime" class="block rounded-lg border-secondary/30" required>
                    <option value="">Choisir le référentiel</option>
                    @foreach (\App\Services\FiscalStatements::REGIMES as $key => $name)
                        <option value="{{ $key }}" @selected(old('regime') === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">Méthodes, engagements et justification des soldes
                <textarea name="notes" rows="4" maxlength="20000" required class="block w-full rounded-lg border-secondary/30">{{ old('notes') }}</textarea>
            </label>
            <label class="block text-sm">Justificatifs d’inventaire
                <input type="file" name="evidence[]" accept=".pdf,.csv,.txt,.xlsx" multiple required class="block mt-1">
            </label>
            @if ($flows->isNotEmpty())
                <details>
                    <summary class="cursor-pointer text-sm font-medium">Ventiler les flux du système normal</summary>
                    <p class="text-sm mt-2">La rubrique proposée est calculée à partir des contreparties. Revoyez les écritures à ventiler et corrigez les propositions si nécessaire.</p>
                    @foreach ($flows as $flow)
                        <label class="mt-2 flex items-center gap-3 text-sm">
                            {{ $flow['reference'] }} · {{ number_format($flow['net'] / 100, 2, ',', ' ') }} FCFA
                            <select name="flows[{{ $flow['reference'] }}]" class="rounded-lg border-secondary/30">
                                <option value="">À ventiler</option>
                                @foreach (['Exploitation', 'Investissement', 'Financement'] as $type)
                                    <option @selected(old('flows.' . $flow['reference']) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </details>
            @endif
            <p class="text-sm">L’arrêté verrouille définitivement l’exercice. Vérifiez le projet et les justificatifs avant de valider.</p>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm text-white">Clôturer et archiver l’exercice</button>
        </form>
    @endif
</section>
@endsection
