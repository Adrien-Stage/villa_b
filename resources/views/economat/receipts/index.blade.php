@extends('layouts.hotel')

@section('title', 'Bons de réception — Économat')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Bons de réception (Entrées en magasin)</h1>
            <p class="text-sm text-primary/60 mt-0.5">Contrôles contradictoires des livraisons fournisseurs, entrées en stock et litiges.</p>
        </div>
        <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i> Voir les bons de commande
        </a>
    </div>

    @include('economat.partials.flash')

    {{-- Synthèse --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
            <span class="text-xs text-primary/50 font-medium">Bons de réception enregistrés</span>
            <div class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['total_receipts'] }}</div>
        </div>
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm bg-emerald-50/15">
            <span class="text-xs text-emerald-700 font-medium">Valeur totale des marchandises reçues</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1">
                {{ number_format($stats['total_amount'] / 100, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
        <form method="GET" action="{{ route('economat.receipts.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <select name="supplier_id" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" @selected(request('supplier_id') == $sup->id)>{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary" placeholder="Date début">
            </div>
            <div class="w-40">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary" placeholder="Date fin">
            </div>
            <button type="submit" class="px-4 py-1.5 bg-secondary/10 hover:bg-secondary/20 text-primary text-sm font-medium rounded-lg transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['supplier_id', 'date_from', 'date_to']))
                <a href="{{ route('economat.receipts.index') }}" class="px-3 py-1.5 text-xs text-primary/50 hover:text-primary">Effacer</a>
            @endif
        </form>
    </div>

    {{-- Tableau --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        @if($receipts->isEmpty())
            <div class="py-12 text-center text-sm text-primary/40">
                <i data-lucide="package-check" class="w-10 h-10 mx-auto text-primary/20 mb-3"></i>
                Aucun bon de réception trouvé.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">N° Réception</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Bon de commande</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Fournisseur</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">N° BL Livreur</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur acceptée</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Réceptionné par</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($receipts as $rc)
                            <tr class="hover:bg-accent/5 cursor-pointer transition-colors" onclick="window.location='{{ route('economat.receipts.show', $rc) }}'">
                                <td class="px-5 py-3 font-mono font-bold text-primary">{{ $rc->number }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-primary/70">{{ $rc->purchaseOrder?->number ?? '—' }}</td>
                                <td class="px-5 py-3 text-primary/80">{{ $rc->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-primary/60">{{ $rc->delivery_note_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono text-primary/70">{{ $rc->lines_count }}</td>
                                <td class="px-5 py-3 text-right font-mono font-bold text-primary">
                                    {{ number_format($rc->total_amount / 100, 0, ',', ' ') }} F
                                </td>
                                <td class="px-5 py-3 text-xs text-primary/70">{{ $rc->receivedBy?->name ?? 'Magasinier' }}</td>
                                <td class="px-5 py-3 text-xs text-primary/50">{{ $rc->received_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-secondary/10">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
