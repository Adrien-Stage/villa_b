@extends('layouts.hotel')

@section('title', 'Dépenses')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
    $methodLabels = ['cash' => 'Espèces', 'bank_transfer' => 'Virement', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo', 'check' => 'Chèque', 'other' => 'Autre'];
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Dépenses</h1>
        <p class="text-sm text-primary/60 mt-1">Les charges décaissées de l'établissement (électricité, eau, achats, loyer…).</p>
    </div>
    <div class="flex items-center gap-3">
        @include('accounting.partials.period')
        <button type="button" onclick="document.getElementById('expense-create').classList.remove('hidden')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle dépense
        </button>
    </div>
</div>

@include('accounting.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<x-table :rows="$expenses" empty="Aucune dépense enregistrée sur cette période." empty-icon="receipt" caption="Dépenses">
    <x-slot:toolbar>
        <span class="text-sm text-primary/60">{{ method_exists($expenses, 'total') ? $expenses->total() : $expenses->count() }} dépense(s) — {{ ucfirst($period['label']) }}</span>
        <span class="text-sm font-semibold text-primary">Total : {{ $fcfa($total) }}</span>
    </x-slot:toolbar>
    <x-slot:head>
        <x-table.col hide="md">Date</x-table.col>
        <x-table.col hide="xl">Catégorie</x-table.col>
        <x-table.col>Libellé</x-table.col>
        <x-table.col hide="lg">Moyen</x-table.col>
        <x-table.col align="right">Montant</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($expenses as $expense)
        <x-table.row>
            <x-table.cell hide="md" nowrap class="text-primary/70">{{ $expense->occurred_at->format('d/m/Y') }}</x-table.cell>
            <x-table.cell hide="xl">
                <span class="inline-flex items-center rounded-full bg-accent/30 px-2 py-0.5 text-[11px] font-semibold text-primary">{{ $expense->categoryLabel() }}</span>
            </x-table.cell>
            <x-table.cell>
                <p class="text-sm text-primary">{{ $expense->label }}</p>
                @if($expense->receipt_path)
                    <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-primary/50 hover:underline"><i data-lucide="paperclip" class="h-3 w-3" aria-hidden="true"></i> pièce jointe</a>
                @endif
            </x-table.cell>
            <x-table.cell hide="lg" class="text-primary/60">{{ $methodLabels[$expense->payment_method] ?? '—' }}</x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold tabular-nums text-red-600">{{ $fcfa($expense->amount) }}</x-table.cell>
            <x-table.actions :label="'Actions pour la dépense '.$expense->label">
                <x-table.action icon="pencil" onclick="document.getElementById('expense-edit-{{ $expense->id }}').classList.remove('hidden')">Modifier</x-table.action>
                <x-table.action :action="route('accounting.expenses.destroy', $expense)" method="DELETE" :fields="['month' => $period['month']]" icon="trash-2" tone="danger" confirm="Supprimer cette dépense ?">Supprimer</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>

{{-- Modal création --}}
<x-modal id="expense-create" title="Nouvelle dépense" formAction="{{ route('accounting.expenses.store') }}" enctype="multipart/form-data"
    closeAction="document.getElementById('expense-create').classList.add('hidden')">
    <input type="hidden" name="month" value="{{ $period['month'] }}">
    @include('accounting.partials.expense-fields', ['expense' => null, 'categories' => $categories, 'methods' => $methods, 'methodLabels' => $methodLabels])
    <x-slot:footer>
        <button type="button" onclick="document.getElementById('expense-create').classList.add('hidden')" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Enregistrer</button>
    </x-slot:footer>
</x-modal>

{{-- Modales édition --}}
@foreach($expenses as $expense)
    <x-modal id="expense-edit-{{ $expense->id }}" title="Modifier la dépense" formAction="{{ route('accounting.expenses.update', $expense) }}" enctype="multipart/form-data"
        closeAction="document.getElementById('expense-edit-{{ $expense->id }}').classList.add('hidden')">
        @method('PUT')
        <input type="hidden" name="month" value="{{ $period['month'] }}">
        @include('accounting.partials.expense-fields', ['expense' => $expense, 'categories' => $categories, 'methods' => $methods, 'methodLabels' => $methodLabels])
        <x-slot:footer>
            <button type="button" onclick="document.getElementById('expense-edit-{{ $expense->id }}').classList.add('hidden')" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
            <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Enregistrer</button>
        </x-slot:footer>
    </x-modal>
@endforeach
@endsection
