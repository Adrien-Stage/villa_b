@extends('layouts.hotel')

@section('title', 'Bons d\'entrée en stock & Réceptions — Économat')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    {{-- En-tête & Barre d'actions --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
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

            @droit('economat.receipts.direct.creer')
                <a href="{{ route('economat.receipts.direct.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                    <i data-lucide="package-plus" class="w-4 h-4"></i>
                    <span>Réception directe</span>
                </a>
            @enddroit
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
    <x-table :rows="$receipts" empty="Aucun bon d'entrée trouvé pour ces critères. Ils naissent du pointage des bons de commande, ou d'une réception directe quand la marchandise arrive sans commande." empty-icon="package-open" caption="Bons d'entrée en stock">
        <x-slot:head>
            <x-table.col>N° bon d'entrée</x-table.col>
            <x-table.col hide="xl">N° bon de commande</x-table.col>
            <x-table.col>Fournisseur</x-table.col>
            <x-table.col hide="3xl">BL fournisseur</x-table.col>
            <x-table.col align="right" hide="2xl">Articles</x-table.col>
            <x-table.col align="right">Valeur admise</x-table.col>
            <x-table.col hide="lg">Litige / qualité</x-table.col>
            <x-table.col hide="3xl">Signé par</x-table.col>
            <x-table.col hide="xl">Date</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($receipts as $rc)
            <x-table.row :href="route('economat.receipts.show', $rc)">
                <x-table.cell nowrap>
                    <a href="{{ route('economat.receipts.show', $rc) }}" class="font-mono font-bold text-primary hover:underline">{{ $rc->number }}</a>
                </x-table.cell>
                <x-table.cell hide="xl" nowrap>
                    @if($rc->purchaseOrder)
                        <a href="{{ route('economat.orders.show', $rc->purchaseOrder) }}" class="font-mono text-xs text-primary/80 hover:underline">{{ $rc->purchaseOrder->number }}</a>
                        @if($rc->purchaseOrder->isRegularisation())
                            <span class="block text-[10px] text-sky-800">Réception directe</span>
                        @endif
                    @else
                        <span class="text-xs text-primary/40">—</span>
                    @endif
                </x-table.cell>
                <x-table.cell class="font-medium">{{ $rc->supplier?->name ?? '—' }}</x-table.cell>
                <x-table.cell hide="3xl" class="font-mono text-xs text-primary/70">{{ $rc->delivery_note_number ?? '—' }}</x-table.cell>
                <x-table.cell align="right" hide="2xl" class="font-mono text-primary/70">{{ $rc->lines_count }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono font-bold text-emerald-800">{{ number_format($rc->total_amount / 100, 0, ',', ' ') }} F</x-table.cell>
                <x-table.cell hide="lg" nowrap>
                    @if($rc->hasRejections())
                        <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700"><i data-lucide="alert-circle" class="h-3 w-3" aria-hidden="true"></i> Refusé ({{ rtrim(rtrim(number_format($rc->totalRejectedQuantity(), 2, ',', ' '), '0'), ',') }})</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"><i data-lucide="check" class="h-3 w-3" aria-hidden="true"></i> 100 % conforme</span>
                    @endif
                </x-table.cell>
                <x-table.cell hide="3xl" nowrap>
                    <span class="inline-flex items-center gap-1.5 rounded border border-indigo-200/70 bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-900" title="Signature certifiée">
                        <i data-lucide="pen-tool" class="h-3 w-3 text-indigo-600" aria-hidden="true"></i><span>{{ $rc->receiverSignature() ?? ($rc->receivedBy?->name ?? 'Économe') }}</span>
                    </span>
                </x-table.cell>
                <x-table.cell hide="xl" nowrap class="font-mono text-xs text-primary/60">{{ $rc->received_at->format('d/m/Y H:i') }}</x-table.cell>
                <x-table.actions :label="'Actions pour le bon d\'entrée '.$rc->number">
                    <x-table.action :href="route('economat.receipts.show', $rc)" icon="eye">Détails</x-table.action>
                    <x-table.action :href="route('economat.receipts.print', $rc)" icon="printer" target="_blank">Imprimer le bordereau</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
