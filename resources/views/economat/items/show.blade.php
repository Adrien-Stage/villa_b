@extends('layouts.hotel')

@section('title', $item->name . ' — Économat')

@section('content')
<div class="max-w-5xl mx-auto">
    <a href="{{ route('economat.items.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux articles
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 mb-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-heading font-semibold text-primary">{{ $item->name }}</h1>
                <p class="text-sm text-primary/50 mt-0.5">
                    {{ $item->category?->name ?? 'Sans catégorie' }}
                    @if($item->reference) · <span class="font-mono">{{ $item->reference }}</span>@endif
                    @if($item->suppliers->isNotEmpty())
                        · Fournisseur(s) : {{ $item->suppliers->pluck('name')->implode(', ') }}
                    @elseif($item->supplier)
                        · Fournisseur : {{ $item->supplier->name }}
                    @endif
                </p>
            </div>
            @php $level = $item->stockLevel(); $decompose = $item->stockDecompose(); @endphp
            <div class="text-right space-y-2">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold
                    {{ $level === 'out' ? 'bg-red-50 text-red-700' : ($level === 'low' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700') }}">
                    <span class="h-2 w-2 rounded-full {{ $level === 'out' ? 'bg-red-500' : ($level === 'low' ? 'bg-amber-500' : 'bg-green-500') }}"></span>
                    {{ rtrim(rtrim(number_format($item->current_stock, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}
                </span>
                @if($decompose)
                    <p class="text-xs text-primary/60">{{ $decompose }}</p>
                @endif
                @droit('economat.items.transformation.voir')
                    <a href="{{ route('economat.items.transformation.show', $item) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-secondary/30 rounded-lg text-xs font-semibold text-primary hover:bg-gray-50">
                        <i data-lucide="boxes" class="w-3.5 h-3.5"></i> Transformation
                    </a>
                @enddroit
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-6">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Seuil d'alerte</p>
                <p class="text-base font-bold text-primary mt-1">{{ rtrim(rtrim(number_format($item->min_stock, 3, ',', ' '), '0'), ',') }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Coût moyen (CUMP)</p>
                <p class="text-base font-bold text-primary mt-1">{{ number_format($item->average_cost / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
            <div class="bg-primary/5 border border-primary/10 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/70 font-semibold">Prix moyen fournisseurs</p>
                <p class="text-base font-bold text-primary mt-1">{{ number_format($item->averageSupplierPriceFcfa(), 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
                <p class="text-[10px] text-primary/50">{{ $item->suppliers->count() }} fournisseur(s)</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Dernier prix d'achat</p>
                <p class="text-base font-bold text-primary mt-1">{{ number_format($item->last_purchase_price / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Valeur du stock</p>
                <p class="text-base font-bold text-primary mt-1">{{ number_format($item->stockValue() / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
        </div>

        @if($item->description)
            <p class="text-sm text-primary/60 mt-4">{{ $item->description }}</p>
        @endif
    </div>

    {{-- Fournisseurs référencés pour cet article --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-6" x-data="{ openModal: false }">
        <div class="px-5 py-3.5 border-b border-secondary/15 flex flex-wrap items-center justify-between gap-3 bg-gray-50/80">
            <div>
                <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-primary/60"></i> Fournisseurs approvisionnant cet article
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary">{{ $item->suppliers->count() }}</span>
                </h2>
                <p class="text-xs text-primary/50 mt-0.5">
                    Prix moyen fournisseurs : <strong class="font-mono text-primary font-bold">{{ number_format($item->averageSupplierPriceFcfa(), 0, ',', ' ') }} FCFA / {{ $item->shortUnit() }}</strong>
                    (appliqué automatiquement en expression d'achat)
                </p>
            </div>
            @droit('economat.suppliers.creer')
                <button type="button" @click="openModal = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Associer un fournisseur
                </button>
            @enddroit
        </div>

        @if($item->suppliers->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-primary/50">
                <i data-lucide="truck" class="w-8 h-8 mx-auto text-primary/30 mb-2"></i>
                <p class="font-medium">Aucun fournisseur associé pour le moment.</p>
                <p class="text-xs text-primary/40 mt-1">Associez un fournisseur pour définir son prix d'achat convenu.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-3 text-left">Fournisseur</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-3 py-3 text-center">Unité</th>
                            <th class="px-4 py-3 text-center">Délai</th>
                            <th class="px-5 py-3 text-right">Prix convenu (FCFA)</th>
                            <th class="px-4 py-3 text-right">Écart vs Moyenne</th>
                            <th class="px-4 py-3 text-center"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @php $prixMoyen = $item->averageSupplierPriceFcfa(); @endphp
                        @foreach($item->suppliers as $sup)
                            @php
                                $prixConvenu = (int) ($sup->pivot->purchase_price ?? 0) / 100;
                                $ecart = $prixMoyen > 0 ? (($prixConvenu - $prixMoyen) / $prixMoyen) * 100 : 0;
                            @endphp
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-5 py-3">
                                    <div class="font-semibold text-primary">{{ $sup->name }}</div>
                                    @if((int) $item->supplier_id === (int) $sup->id)
                                        <span class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 mt-0.5">Fournisseur principal</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-primary/70">
                                    <div>{{ $sup->contact_name ?: '—' }}</div>
                                    <div class="text-[11px] text-primary/50">{{ $sup->phone ?: ($sup->email ?: 'Sans contact') }}</div>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-xs text-primary/70">
                                    {{ $sup->pivot->unit ?: $item->shortUnit() }}
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-primary/60">
                                    {{ $sup->pivot->delivery_lead_time_days ? $sup->pivot->delivery_lead_time_days . ' j' : ($sup->delivery_lead_time_days ? $sup->delivery_lead_time_days . ' j' : '—') }}
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-bold text-primary">
                                    {{ number_format($prixConvenu, 0, ',', ' ') }} F
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-xs">
                                    @if(abs($ecart) < 0.1)
                                        <span class="text-primary/50">Moyenne</span>
                                    @elseif($ecart < 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ number_format($ecart, 1, ',', ' ') }} %
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            +{{ number_format($ecart, 1, ',', ' ') }} %
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @droit('economat.suppliers.modifier')
                                        <form method="POST" action="{{ route('economat.items.suppliers.unlink', [$item, $sup]) }}" onsubmit="return confirm('Détacher ce fournisseur de l\'article ?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded text-red-500 hover:text-red-700 hover:bg-red-50" title="Détacher">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    @enddroit
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Modal associer fournisseur --}}
        <div x-show="openModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary/40 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5 border border-secondary/20">
                <div class="flex items-center justify-between pb-3 border-b border-secondary/15">
                    <h3 class="font-heading font-semibold text-primary">Associer un fournisseur</h3>
                    <button type="button" @click="openModal = false" class="text-primary/40 hover:text-primary"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form method="POST" action="{{ route('economat.items.suppliers.link', $item) }}" class="space-y-4 pt-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase text-primary/60 mb-1">Fournisseur *</label>
                        <select name="supplier_id" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                            <option value="">Choisir un fournisseur...</option>
                            @foreach($allSuppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-primary/60 mb-1">Prix d'achat convenu (FCFA) *</label>
                        <input type="number" step="1" min="0" name="purchase_price" required placeholder="Ex: 5000" class="w-full px-3 py-2 text-sm font-mono border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-secondary/15">
                        <button type="button" @click="openModal = false" class="px-3 py-1.5 text-xs text-primary/60 hover:text-primary">Annuler</button>
                        <button type="submit" class="px-4 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Journal des mouvements --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/20 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-primary/50"></i> Historique des mouvements
            </h2>
            @droit('economat.movements.voir')
                <a href="{{ route('economat.movements.index', ['article' => $item->id, 'du' => '', 'au' => '']) }}" class="text-xs font-semibold text-primary/70 underline hover:text-primary">Fiche de stock complète</a>
            @enddroit
        </div>
        @if($movements->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-primary/40">Aucun mouvement enregistré pour cet article.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Type</th>
                            <th class="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/50">Unité</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock restant</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Quantité</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock Total</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Motif</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($movements as $m)
                            <tr>
                                <td class="px-5 py-2.5 text-primary/50 text-xs whitespace-nowrap">{{ $m->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ $m->type === 'in' ? 'bg-green-50 text-green-700' : ($m->type === 'out' ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $m->typeLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center font-mono font-bold text-xs text-primary/70">{{ $item->shortUnit() }}</td>
                                <td class="px-5 py-2.5 text-right text-primary/60">{{ rtrim(rtrim(number_format($m->stockAvant(), 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-2.5 text-right font-medium {{ $m->quantity > 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-5 py-2.5 text-right text-primary/70">
                                    {{ rtrim(rtrim(number_format($m->stock_after, 3, ',', ' '), '0'), ',') }}
                                    @if($m->stockApresDecompose())<span class="block text-[10px] text-primary/45">{{ $m->stockApresDecompose() }}</span>@endif
                                </td>
                                <td class="px-5 py-2.5 text-primary/50 text-xs">
                                    {{ $m->reason }}
                                    @if($m->ouvertures() && $m->source_type !== \App\Models\StockMovement::SOURCE_PACKAGING)<span class="block text-amber-700">{{ ucfirst($m->ouvertures()) }}</span>@endif
                                </td>
                                <td class="px-5 py-2.5 text-primary/50 text-xs">{{ $m->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
