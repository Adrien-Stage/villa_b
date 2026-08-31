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

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/50 flex items-center justify-between">
        <span class="text-sm text-primary/60">{{ $expenses->count() }} dépense(s) — {{ ucfirst($period['label']) }}</span>
        <span class="text-sm font-semibold text-primary">Total : {{ $fcfa($total) }}</span>
    </div>
    @if($expenses->isEmpty())
        <div class="py-12 text-center text-primary/40">
            <i data-lucide="receipt" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p class="text-sm">Aucune dépense enregistrée sur cette période.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Date</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Catégorie</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Libellé</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Moyen</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Montant</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($expenses as $expense)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-sm text-primary/70 whitespace-nowrap">{{ $expense->occurred_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-accent/30 text-primary">{{ $expense->categoryLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-primary">{{ $expense->label }}</p>
                                @if($expense->receipt_path)
                                    <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" class="text-[11px] text-primary/50 hover:underline inline-flex items-center gap-1">
                                        <i data-lucide="paperclip" class="w-3 h-3"></i> pièce jointe
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-primary/60">{{ $methodLabels[$expense->payment_method] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 tabular-nums whitespace-nowrap">{{ $fcfa($expense->amount) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="document.getElementById('expense-edit-{{ $expense->id }}').classList.remove('hidden')"
                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <form method="POST" action="{{ route('accounting.expenses.destroy', $expense) }}" onsubmit="return confirm('Supprimer cette dépense ?');">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="month" value="{{ $period['month'] }}">
                                        <button type="submit" class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    @endif
</div>

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
