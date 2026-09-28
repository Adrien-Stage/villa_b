@extends('layouts.hotel')

@section('title', 'Bons d\'entrée en stock & Réceptions — Économat')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    {{-- En-tête & Barre d'actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="package-check" class="w-7 h-7 text-primary"></i>
                <span>Bons d'entrée en stock & Réceptions</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Contrôle contradictoire des livraisons fournisseurs, pointage des marchandises, validation des entrées en stock et traçabilité des signatures.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.receipts.export')
                <x-barre-export route="economat.receipts.export" />
            @enddroit

            <a href="{{ route('economat.orders.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="shopping-cart" class="w-4 h-4 text-primary/70"></i>
                <span>Bons de commande</span>
            </a>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Synthèse & Indicateurs KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
            <span class="text-xs text-primary/50 font-medium">Bons d'entrée enregistrés</span>
            <div class="text-2xl font-bold font-mono text-primary mt-1">{{ number_format($stats['total_receipts'], 0, ',', ' ') }}</div>
            <div class="text-[11px] text-primary/40 mt-0.5">Livraisons physiques pointées</div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
            <span class="text-xs text-emerald-700 font-medium">100% Conformes & Soldées</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1">{{ number_format($stats['conforme'], 0, ',', ' ') }}</div>
            <div class="text-[11px] text-emerald-600/70 mt-0.5">Aucun refus ni anomalie</div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
            <span class="text-xs text-rose-700 font-medium">Livraisons avec Litiges / Avaries</span>
            <div class="text-2xl font-bold font-mono text-rose-700 mt-1">{{ number_format($stats['avec_litige'], 0, ',', ' ') }}</div>
            <div class="text-[11px] text-rose-600/70 mt-0.5">Marchandises non conformes refusées</div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm bg-emerald-50/20">
            <span class="text-xs text-emerald-800 font-medium">Valeur totale entrée en stock</span>
            <div class="text-2xl font-bold font-mono text-emerald-800 mt-1">
                {{ number_format($stats['total_amount'] / 100, 0, ',', ' ') }} <span class="text-sm font-sans font-normal text-emerald-700">F</span>
            </div>
            <div class="text-[11px] text-emerald-700/70 mt-0.5">Valorisation brute admise au CUMP</div>
        </div>
    </div>

    {{-- Barre de filtres multicritères --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
        <form method="GET" action="{{ route('economat.receipts.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-primary/60 uppercase mb-1">Fournisseur</label>
                <select name="supplier_id" class="w-full px-3 py-1.5 text-sm border border-secondary/30 rounded-lg text-primary focus:outline-none focus:border-primary bg-white">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" @selected(request('supplier_id') == $sup->id)>{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-primary/60 uppercase mb-1">Conformité / Litige</label>
                <select name="litige" class="w-full px-3 py-1.5 text-sm border border-secondary/30 rounded-lg text-primary focus:outline-none focus:border-primary bg-white">
                    <option value="">Tous les bons d'entrée</option>
                    <option value="sans" @selected(request('litige') === 'sans')>100% Conforme (Sans litige)</option>
                    <option value="avec" @selected(request('litige') === 'avec')>Avec Litige / Refus</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-primary/60 uppercase mb-1">Date début</label>
                <input type="date" name="du" value="{{ request('du', request('date_from')) }}" class="w-full px-3 py-1.5 text-sm border border-secondary/30 rounded-lg text-primary focus:outline-none focus:border-primary bg-white">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-primary/60 uppercase mb-1">Date fin</label>
                <input type="date" name="au" value="{{ request('au', request('date_to')) }}" class="w-full px-3 py-1.5 text-sm border border-secondary/30 rounded-lg text-primary focus:outline-none focus:border-primary bg-white">
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[11px] font-semibold text-primary/60 uppercase mb-1">Recherche</label>
                    <input type="text" name="recherche" value="{{ request('recherche') }}" placeholder="N° Bon, BL, fournisseur..." class="w-full px-3 py-1.5 text-sm border border-secondary/30 rounded-lg text-primary focus:outline-none focus:border-primary bg-white">
                </div>
                <button type="submit" class="px-4 py-1.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm shrink-0">
                    Filtrer
                </button>
                @if(request()->hasAny(['supplier_id', 'du', 'au', 'date_from', 'date_to', 'litige', 'recherche']))
                    <a href="{{ route('economat.receipts.index') }}" class="px-2.5 py-1.5 text-xs text-primary/50 hover:text-primary shrink-0" title="Réinitialiser">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tableau des Bons d'Entrée --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        @if($receipts->isEmpty())
            <div class="py-16 text-center text-sm text-primary/40">
                <i data-lucide="package-open" class="w-12 h-12 mx-auto text-primary/20 mb-3"></i>
                <p class="font-medium text-primary/60">Aucun bon d'entrée en stock trouvé pour ces critères.</p>
                <p class="text-xs text-primary/40 mt-1">Les bons d'entrée sont générés lors du déchargement et pointage contradictoire des bons de commande.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70 border-b border-secondary/15">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">N° Bon d'entrée</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">N° Bon Commande</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Fournisseur</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">BL Fournisseur</th>
                            <th class="px-3 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur admise</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Litige / Qualité</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Signé par</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($receipts as $rc)
                            <tr class="hover:bg-accent/5 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('economat.receipts.show', $rc) }}" class="font-mono font-bold text-primary hover:underline">
                                        {{ $rc->number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    @if($rc->purchaseOrder)
                                        <a href="{{ route('economat.orders.show', $rc->purchaseOrder) }}" class="font-mono text-xs text-primary/80 hover:underline">
                                            {{ $rc->purchaseOrder->number }}
                                        </a>
                                    @else
                                        <span class="text-xs text-primary/40">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium text-primary">
                                    {{ $rc->supplier?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-primary/70">
                                    {{ $rc->delivery_note_number ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-primary/70">
                                    {{ $rc->lines_count }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-800">
                                    {{ number_format($rc->total_amount / 100, 0, ',', ' ') }} F
                                </td>
                                <td class="px-4 py-3">
                                    @if($rc->hasRejections())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i> Refusé ({{ rtrim(rtrim(number_format($rc->totalRejectedQuantity(), 2, ',', ' '), '0'), ',') }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i data-lucide="check" class="w-3 h-3"></i> 100% Conforme
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-indigo-50 border border-indigo-200/70 rounded text-[11px] font-medium text-indigo-900" title="Signature certifiée">
                                        <i data-lucide="pen-tool" class="w-3 h-3 text-indigo-600"></i>
                                        <span>{{ $rc->receiverSignature() ?? ($rc->receivedBy?->name ?? 'Économe') }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-primary/60 font-mono">
                                    {{ $rc->received_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('economat.receipts.print', $rc) }}" target="_blank" class="p-1.5 border border-secondary/30 rounded-lg text-primary hover:bg-gray-100 transition-colors" title="Imprimer le bordereau">
                                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                        </a>
                                        <a href="{{ route('economat.receipts.show', $rc) }}" class="px-2.5 py-1 bg-secondary/10 hover:bg-secondary/20 rounded-lg text-xs font-semibold text-primary transition-colors">
                                            Détails
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-secondary/15 bg-gray-50/50">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
