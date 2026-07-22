@extends('layouts.hotel')

@section('title', $requisition->number . ' — Demande')

@section('content')
@php
    $statusStyles = [
        'pending' => 'bg-blue-50 text-blue-700', 'approved' => 'bg-indigo-50 text-indigo-700',
        'rejected' => 'bg-red-50 text-red-700', 'delivered' => 'bg-green-50 text-green-700',
        'cancelled' => 'bg-gray-100 text-gray-600',
    ];
@endphp
<div class="max-w-3xl mx-auto">
    <a href="{{ route('economat.requisitions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 mb-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-heading font-semibold text-primary font-mono">{{ $requisition->number }}</h1>
                <p class="text-sm text-primary/60 mt-1">{{ $requisition->departmentLabel() }} · {{ $requisition->requestedBy?->name ?? '—' }}</p>
                <p class="text-xs text-primary/40 mt-0.5">Créée le {{ $requisition->created_at->format('d/m/Y H:i') }}</p>
                @if($requisition->purpose)<p class="text-sm text-primary/70 mt-2">{{ $requisition->purpose }}</p>@endif
            </div>
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold {{ $statusStyles[$requisition->status] ?? 'bg-gray-100' }}">{{ $requisition->statusLabel() }}</span>
        </div>

        @if($requisition->reviewedBy)
            <p class="mt-3 pt-3 border-t border-secondary/20 text-xs text-primary/50">
                {{ $requisition->status === 'rejected' ? 'Refusée' : 'Validée' }} par {{ $requisition->reviewedBy->name }} le {{ $requisition->reviewed_at?->format('d/m/Y H:i') }}
                @if($requisition->review_notes) — {{ $requisition->review_notes }}@endif
            </p>
        @endif
    </div>

    {{-- Livraison (économe) : formulaire avec quantités servies --}}
    <form method="POST" action="{{ $isKeeper && $requisition->canBeDelivered() ? route('economat.requisitions.deliver', $requisition) : '#' }}">
        @if($isKeeper && $requisition->canBeDelivered())@csrf @endif
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-secondary/20"><h2 class="text-sm font-semibold text-primary">Articles</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Demandé</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock</th>
                            @if($requisition->status === 'delivered')
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Servi</th>
                            @elseif($isKeeper && $requisition->canBeDelivered())
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">À servir</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($requisition->lines as $line)
                            @php $serviceable = $line->isServiceable(); @endphp
                            <tr>
                                <td class="px-5 py-3 text-primary">{{ $line->item?->name ?? '—' }} <span class="text-primary/40 text-xs">({{ $line->item?->unit }})</span></td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ rtrim(rtrim(number_format($line->quantity_requested, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span class="{{ $serviceable ? 'text-primary/50' : 'text-red-600 font-medium' }}">
                                        {{ rtrim(rtrim(number_format($line->item?->current_stock ?? 0, 3, ',', ' '), '0'), ',') }}
                                    </span>
                                    @unless($serviceable)<span class="block text-[10px] text-red-500">insuffisant</span>@endunless
                                </td>
                                @if($requisition->status === 'delivered')
                                    <td class="px-5 py-3 text-right font-medium text-green-700">{{ rtrim(rtrim(number_format($line->quantity_issued, 3, ',', ' '), '0'), ',') }}</td>
                                @elseif($isKeeper && $requisition->canBeDelivered())
                                    <td class="px-5 py-3 text-right">
                                        <input type="number" step="0.001" min="0" max="{{ $line->item?->current_stock ?? 0 }}" name="issued[{{ $line->id }}]"
                                            value="{{ min((float) $line->quantity_requested, (float) ($line->item?->current_stock ?? 0)) }}"
                                            class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($isKeeper && $requisition->canBeDelivered())
                <div class="px-5 py-3 border-t border-secondary/20 bg-gray-50 flex justify-between items-center">
                    <p class="text-xs text-primary/50">La livraison déstocke les quantités servies.</p>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">
                        <i data-lucide="truck" class="w-4 h-4"></i> Livrer et déstocker
                    </button>
                </div>
            @endif
        </div>
    </form>

    {{-- Actions de validation (économe, demande en attente) --}}
    @if($isKeeper && $requisition->canBeReviewed())
        <div class="bg-white border border-secondary/20 rounded-xl p-5" x-data="{ mode: null }">
            <h2 class="text-sm font-semibold text-primary mb-3">Traiter la demande</h2>
            <div class="flex gap-2 mb-3">
                <button type="button" @click="mode = 'approve'" class="flex-1 px-4 py-2.5 rounded-lg border text-sm font-medium transition-colors" :class="mode === 'approve' ? 'bg-green-600 text-white border-green-600' : 'border-secondary/30 text-primary hover:bg-green-50'">
                    <i data-lucide="check" class="w-4 h-4 inline"></i> Valider
                </button>
                <button type="button" @click="mode = 'reject'" class="flex-1 px-4 py-2.5 rounded-lg border text-sm font-medium transition-colors" :class="mode === 'reject' ? 'bg-red-600 text-white border-red-600' : 'border-secondary/30 text-primary hover:bg-red-50'">
                    <i data-lucide="x" class="w-4 h-4 inline"></i> Refuser
                </button>
            </div>
            <form x-show="mode" x-cloak method="POST" :action="mode === 'approve' ? '{{ route('economat.requisitions.approve', $requisition) }}' : '{{ route('economat.requisitions.reject', $requisition) }}'">
                @csrf
                <textarea name="review_notes" rows="2" placeholder="Commentaire (facultatif)" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary mb-3"></textarea>
                <button type="submit" class="w-full px-4 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">
                    <span x-text="mode === 'approve' ? 'Confirmer la validation' : 'Confirmer le refus'"></span>
                </button>
            </form>
        </div>
    @endif

    {{-- Annulation par le demandeur --}}
    @if($requisition->canBeCancelled())
        <form method="POST" action="{{ route('economat.requisitions.cancel', $requisition) }}" onsubmit="return confirm('Annuler cette demande ?');" class="mt-3 text-right">
            @csrf
            <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">Annuler la demande</button>
        </form>
    @endif
</div>
@endsection
