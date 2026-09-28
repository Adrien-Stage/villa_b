@extends('layouts.hotel')

@section('title', 'Bons de réquisition & Demandes des services — Économat')

@section('content')
@php
    $statusStyles = [
        'pending'   => 'bg-blue-50 text-blue-700 border border-blue-200',
        'approved'  => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        'rejected'  => 'bg-red-50 text-red-700 border border-red-200',
        'delivered' => 'bg-green-50 text-green-700 border border-green-200',
        'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    ];
@endphp

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="inbox" class="w-7 h-7 text-primary"></i>
                <span>{{ $isKeeper ? 'Bons de réquisition & Demandes des services' : 'Mes demandes à l\'économat' }}</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                {{ $isKeeper ? 'Traçabilité, arbitrage et livraison des bons d\'approvisionnement émis par l\'hébergement, le housekeeping, la restauration, la boutique et la comptabilité.' : 'Sollicitez des marchandises, fournitures de bureau et consommables auprès du magasin central.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.requisitions.export')
                <x-barre-export route="economat.requisitions.export" />
            @enddroit

            @droit('economat.requisitions.creer')
                <a href="{{ route('economat.requisitions.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Nouveau bon</span>
                </a>
            @enddroit
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- 4 Cartes KPI Synthèse des Bons --}}
    @if(isset($stats))
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Total des bons</p>
                <p class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-primary/45 mt-1">Bons émis sur le périmètre</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-600">En attente d'arbitrage</p>
                <p class="text-2xl font-bold font-mono text-blue-700 mt-1">{{ $stats['pending'] }}</p>
                <p class="text-xs text-blue-600/70 mt-1">À valider par l'économat</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Validés (à servir)</p>
                <p class="text-2xl font-bold font-mono text-indigo-700 mt-1">{{ $stats['approved'] }}</p>
                <p class="text-xs text-indigo-600/70 mt-1">Prêts pour livraison physique</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600">Livrés / Servis</p>
                <p class="text-2xl font-bold font-mono text-green-700 mt-1">{{ $stats['delivered'] }}</p>
                <p class="text-xs text-green-600/70 mt-1">Déstockés et transférés</p>
            </div>
        </div>
    @endif

    {{-- Filtres avancés --}}
    @include('economat.requisitions.partials.filtres')

    {{-- Tableau des Bons --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        @if($requisitions->isEmpty())
            <div class="p-12 text-center text-primary/40">
                <i data-lucide="inbox" class="w-12 h-12 text-primary/20 mx-auto mb-3"></i>
                <p class="text-sm font-medium text-primary/60">
                    {{ $filtres ? 'Aucun bon de réquisition ne correspond aux filtres sélectionnés.' : 'Aucun bon de réquisition enregistré.' }}
                </p>
                @if($filtres)
                    <div class="mt-3">
                        <a href="{{ route('economat.requisitions.index') }}" class="text-xs text-primary underline">
                            Réinitialiser les filtres
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-surface-light border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-4">N° de Bon</th>
                            <th class="py-3 px-4">Service Demandeur</th>
                            <th class="py-3 px-4">Demandeur</th>
                            <th class="py-3 px-4 text-center">Date Émission</th>
                            <th class="py-3 px-4 text-center">Nb Articles</th>
                            <th class="py-3 px-4">Statut</th>
                            <th class="py-3 px-4">Motif</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($requisitions as $req)
                            <tr class="hover:bg-surface-light/40 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-primary">
                                    <a href="{{ route('economat.requisitions.show', $req) }}" class="hover:underline">
                                        {{ $req->number }}
                                    </a>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-medium text-primary">{{ $req->departmentLabel() }}</span>
                                </td>
                                <td class="py-3 px-4 text-primary/70">
                                    {{ $req->requestedBy?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-primary/60">
                                    {{ $req->created_at->format('d/m/Y') }}
                                    <span class="text-[10px] text-primary/40 block">{{ $req->created_at->format('H:i') }}</span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-primary">
                                    {{ $req->lines->count() }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusStyles[$req->status] ?? 'bg-gray-100' }}">
                                        {{ $req->statusLabel() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-primary/60 max-w-xs truncate" title="{{ $req->purpose }}">
                                    {{ $req->purpose ?: '—' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('economat.requisitions.show', $req) }}"
                                            class="p-1.5 rounded-lg text-primary/60 hover:text-primary hover:bg-surface-light transition-colors"
                                            title="Consulter les détails">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>

                                        <a href="{{ route('economat.requisitions.print', $req) }}" target="_blank"
                                            class="p-1.5 rounded-lg text-primary/60 hover:text-primary hover:bg-surface-light transition-colors"
                                            title="Imprimer le bon">
                                            <i data-lucide="printer" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-secondary/20 bg-surface-light/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-primary/50">
                    Affichage de {{ $requisitions->firstItem() ?? 0 }} à {{ $requisitions->lastItem() ?? 0 }} sur {{ $requisitions->total() }} bon(s)
                </div>
                <div>
                    {{ $requisitions->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
