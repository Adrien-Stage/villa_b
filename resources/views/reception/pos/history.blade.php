@extends('layouts.pos')

@section('title', 'Historique des Ventes POS Réception')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('reception.pos.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/60 hover:text-primary transition-colors mb-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Retour au terminal POS
            </a>
            <h1 class="text-2xl font-heading font-bold text-primary flex items-center gap-2">
                <i data-lucide="history" class="w-6 h-6 text-primary"></i>
                Historique des Ventes POS Réception
            </h1>
            <p class="text-xs text-secondary mt-0.5">Consultation et réimpression des opérations de vente effectuées à la réception</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('reception.pos.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-surface-dark transition-all shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nouvelle vente POS
            </a>
        </div>
    </div>

    {{-- Filtres de recherche --}}
    <div class="bg-white rounded-2xl p-4 border border-secondary/15 shadow-xs mb-6">
        <form method="GET" action="{{ route('reception.pos.history') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-primary/50 mb-1">Recherche (N° vente, client, chambre)</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Ex: POS-REC, 204, Dupont..."
                           class="w-full pl-9 pr-3 py-2 text-xs border border-secondary/30 rounded-xl outline-none focus:border-primary">
                    <i data-lucide="search" class="w-4 h-4 text-primary/40 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-primary/50 mb-1">Statut</label>
                <select name="payment_status" class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-xl bg-white outline-none focus:border-primary">
                    <option value="">Tous les statuts</option>
                    <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Payé immédiatement</option>
                    <option value="charged_to_room" {{ request('payment_status') === 'charged_to_room' ? 'selected' : '' }}>Débité sur chambre</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-primary text-white text-xs font-bold rounded-xl hover:bg-surface-dark transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'payment_status', 'date']))
                    <a href="{{ route('reception.pos.history') }}" class="py-2 px-3 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs font-bold rounded-xl transition-colors" title="Réinitialiser">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <x-table :rows="$sales" empty="Aucune vente enregistrée pour le moment." empty-icon="receipt" caption="Ventes du POS de la réception">
        <x-slot:head>
            <x-table.col hide="lg">Date / heure</x-table.col>
            <x-table.col>N° vente</x-table.col>
            <x-table.col>Client / chambre</x-table.col>
            <x-table.col hide="2xl">Articles</x-table.col>
            <x-table.col align="right">Total TTC</x-table.col>
            <x-table.col hide="xl">Règlement</x-table.col>
            <x-table.col>Statut</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($sales as $sale)
            <x-table.row>
                <x-table.cell hide="lg" nowrap class="text-primary/70">{{ $sale->created_at->format('d/m/Y H:i') }}</x-table.cell>
                <x-table.cell nowrap class="font-mono font-bold">{{ $sale->sale_number }}</x-table.cell>
                <x-table.cell>
                    <p class="font-bold text-primary">{{ $sale->customer_name }}</p>
                    @if($sale->room_number)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-800"><i data-lucide="bed" class="h-3 w-3" aria-hidden="true"></i> Chambre {{ $sale->room_number }}</span>
                    @endif
                </x-table.cell>
                <x-table.cell hide="2xl" class="max-w-xs truncate text-primary/70">{{ $sale->items->pluck('name')->join(', ') }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-heading font-extrabold">{{ $sale->formattedTotal() }}</x-table.cell>
                <x-table.cell hide="xl" nowrap class="text-primary/80">{{ $sale->paymentMethodLabel() }}</x-table.cell>
                <x-table.cell nowrap>
                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $sale->payment_status === 'charged_to_room' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">{{ $sale->paymentStatusLabel() }}</span>
                </x-table.cell>
                <x-table.actions :label="'Actions pour la vente '.$sale->sale_number">
                    <x-table.action :href="route('reception.pos.receipt', $sale)" icon="printer">Reçu</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>

</div>
@endsection
