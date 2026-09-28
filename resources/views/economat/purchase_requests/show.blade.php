@extends('layouts.hotel')

@section('title', $request->number . ' — Demande d\'achat')

@section('content')
@php
    $statusStyles = [
        'pending'   => 'bg-amber-50 text-amber-700 border-amber-200',
        'approved'  => 'bg-blue-50 text-blue-700 border-blue-200',
        'rejected'  => 'bg-red-50 text-red-700 border-red-200',
        'converted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="{ showRejectModal: false, showConvertModal: false }">
    <div class="flex items-center justify-between">
        <a href="{{ route('economat.purchase_requests.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux demandes d'achat
        </a>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $statusStyles[$request->status] ?? 'bg-gray-100' }}">
                {{ $request->statusLabel() }}
            </span>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Carte récapitulative --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-semibold text-primary/50 uppercase tracking-wider">Demande d'approvisionnement</span>
                <h1 class="text-2xl font-heading font-bold text-primary font-mono mt-0.5">{{ $request->number }}</h1>
                <p class="text-sm text-primary/70 mt-1">
                    Département : <strong>{{ $request->departmentLabel() }}</strong> · Priorité : <strong>{{ $request->priorityLabel() }}</strong>
                </p>
                <p class="text-xs text-primary/40 mt-1">
                    Créé le {{ $request->created_at->format('d/m/Y à H:i') }} par {{ $request->requestedBy?->name ?? 'Demandeur' }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-primary/50 uppercase font-semibold">Montant estimé</span>
                <div class="text-2xl font-bold font-mono text-primary mt-0.5">
                    {{ number_format($request->total_estimated_amount / 100, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        @if($request->purpose)
            <div class="p-3.5 bg-gray-50 rounded-lg text-sm text-primary/80 border border-secondary/10">
                <span class="font-semibold text-xs text-primary/60 uppercase block mb-1">Justification du besoin :</span>
                {{ $request->purpose }}
            </div>
        @endif

        @if($request->status === 'rejected')
            <div class="p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                <span class="font-bold text-xs uppercase block mb-1">Motif du refus :</span>
                {{ $request->rejection_reason }}
                <div class="text-xs text-red-600/70 mt-1">Refusé le {{ $request->reviewed_at?->format('d/m/Y') }} par {{ $request->reviewedBy?->name ?? 'La direction' }}</div>
            </div>
        @elseif($request->status === 'approved')
            <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 flex items-center justify-between">
                <div>
                    <span class="font-bold text-xs uppercase block">Demande approuvée pour achat</span>
                    <span class="text-xs text-blue-700">Validée le {{ $request->reviewed_at?->format('d/m/Y') }} par {{ $request->reviewedBy?->name ?? 'La direction' }}</span>
                    @if($request->review_notes)
                        <div class="mt-1 text-xs italic text-blue-900">Note : {{ $request->review_notes }}</div>
                    @endif
                </div>
                @if($canManage && $request->canBeConverted())
                    <button type="button" @click="showConvertModal = true" class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Générer le(s) bon(s) de commande
                    </button>
                @endif
            </div>
        @elseif($request->status === 'converted' && $request->purchaseOrders->isNotEmpty())
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg space-y-2">
                <span class="font-bold text-xs text-emerald-800 uppercase block">Bon(s) de commande fournisseur généré(s) :</span>
                <div class="flex flex-wrap gap-2">
                    @foreach($request->purchaseOrders as $po)
                        <a href="{{ route('economat.orders.show', $po) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 rounded-lg text-xs font-mono font-bold hover:bg-emerald-100 transition-colors">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> {{ $po->number }} ({{ $po->supplier?->name ?? 'Fournisseur' }})
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Barre d'actions hiérarchiques --}}
        @if($request->isPending() && ($canReview || $canManage))
            <div class="flex flex-wrap gap-2 pt-3 border-t border-secondary/15">
                @if($canReview)
                    <form method="POST" action="{{ route('economat.purchase_requests.approve', $request) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Approuver la demande
                        </button>
                    </form>
                    <button type="button" @click="showRejectModal = true" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Refuser la demande
                    </button>
                @endif

                @if($request->canBeCancelled())
                    <form method="POST" action="{{ route('economat.purchase_requests.cancel', $request) }}" onsubmit="return confirm('Annuler cette demande d\'achat ?');">
                        @csrf
                        <button type="submit" class="px-4 py-2 border border-secondary/30 text-primary/70 hover:bg-gray-50 text-sm font-medium rounded-lg transition-colors">
                            Annuler la demande
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    {{-- Tableau des articles --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15">
            <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/40 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-5 py-3 text-left">Article</th>
                        <th class="px-5 py-3 text-left">Fournisseur habituel</th>
                        <th class="px-5 py-3 text-right">Quantité demandée</th>
                        <th class="px-5 py-3 text-right">P.U. estimé</th>
                        <th class="px-5 py-3 text-right">Total estimé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($request->lines as $line)
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-5 py-3 text-primary font-medium">
                                {{ $line->item?->name ?? '—' }}
                                <span class="text-xs text-primary/40">({{ $line->item?->unit }})</span>
                            </td>
                            <td class="px-5 py-3 text-primary/60 text-xs">
                                {{ $line->item?->supplier?->name ?? 'Non défini' }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-primary">
                                {{ rtrim(rtrim(number_format($line->quantity_requested, 3, ',', ' '), '0'), ',') }} {{ $line->item?->unit }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-primary/70">
                                {{ number_format($line->estimated_unit_price / 100, 0, ',', ' ') }} F
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-primary">
                                {{ number_format($line->estimatedTotal() / 100, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50/80 border-t border-secondary/15 font-semibold text-primary">
                    <tr>
                        <td colspan="4" class="px-5 py-3 text-right">Total estimé de la demande :</td>
                        <td class="px-5 py-3 text-right font-mono text-base font-bold text-primary">
                            {{ number_format($request->total_estimated_amount / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Modal de refus --}}
    <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4" @click.outside="showRejectModal = false">
            <h3 class="text-base font-bold text-primary">Refuser la demande d'achat</h3>
            <p class="text-xs text-primary/60">Veuillez indiquer le motif du refus pour notifier le demandeur.</p>

            <form method="POST" action="{{ route('economat.purchase_requests.reject', $request) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase mb-1">Motif du refus *</label>
                    <textarea name="rejection_reason" rows="3" required placeholder="Ex: Stock suffisant en réserve, budget dépassé..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:outline-none focus:border-primary"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showRejectModal = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-xs font-bold rounded-lg hover:bg-red-700">Confirmer le rejet</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal de conversion en bon de commande --}}
    <div x-show="showConvertModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4" @click.outside="showConvertModal = false">
            <h3 class="text-base font-bold text-primary">Générer les bons de commande</h3>
            <p class="text-xs text-primary/60">
                Par défaut, le système groupe automatiquement les articles par fournisseur habituel. Vous pouvez également forcer un fournisseur unique pour l'ensemble du lot.
            </p>

            <form method="POST" action="{{ route('economat.purchase_requests.convert', $request) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase mb-1">Fournisseur (optionnel)</label>
                    <select name="supplier_id" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:outline-none focus:border-primary">
                        <option value="">Automatique (Groupement par fournisseur habituel)</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showConvertModal = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-surface-dark">Générer le(s) bon(s)</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
