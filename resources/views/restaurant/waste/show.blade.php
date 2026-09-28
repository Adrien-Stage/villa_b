@extends('layouts.hotel')

@section('title', 'Fiche de perte ' . $waste->reference)

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('restaurant.waste.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary/60 hover:text-primary mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Retour aux pertes & déchets
            </a>
            <h1 class="font-heading text-2xl font-semibold text-primary">Fiche de mise au rebut {{ $waste->reference }}</h1>
            <p class="text-sm text-primary/50 mt-0.5">Constatée le {{ $waste->occurred_at?->format('d/m/Y à H:i') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('restaurant.waste.print', $waste) }}" target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le PV
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-secondary/15 overflow-hidden">
        {{-- En-tête statut & valorisation --}}
        <div class="p-6 bg-secondary/5 border-b border-secondary/15 flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-red-100 text-red-800">
                    {{ $waste->reasonLabel() }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-secondary/15 text-primary ml-2">
                    {{ $waste->departmentLabel() }}
                </span>
            </div>

            <div class="text-right">
                <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Valorisation de la perte</p>
                <p class="text-2xl font-heading font-semibold text-red-600 mt-0.5">{{ $waste->formattedTotalCost() }}</p>
            </div>
        </div>

        {{-- Détails de la matière sortie --}}
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/40 mb-2">Article du stock</h3>
                    <p class="text-base font-semibold text-primary">{{ $waste->item?->name ?? 'Article supprimé' }}</p>
                    <p class="text-xs text-primary/50 mt-0.5">Catégorie : {{ $waste->item?->category?->name ?? 'Non classé' }}</p>
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/40 mb-2">Quantité sortie du garde-manger</h3>
                    <p class="text-lg font-bold text-red-600">
                        {{ rtrim(rtrim(number_format((float) $waste->quantity, 3, ',', ' '), '0'), ',') }} {{ $waste->item?->unit }}
                    </p>
                    <p class="text-xs text-primary/50 mt-0.5">Coût unitaire appliqué : {{ number_format($waste->unitCostFcfa(), 2, ',', ' ') }} FCFA / {{ $waste->item?->unit }}</p>
                </div>
            </div>

            <hr class="border-secondary/15">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/40 mb-2">Personne responsable / Déclarant</h3>
                    <p class="text-sm font-medium text-primary">{{ $waste->responsible_person ?? 'Non spécifié' }}</p>
                    <p class="text-xs text-primary/50 mt-0.5">Enregistré dans l'ERP par {{ $waste->recordedBy?->name ?? 'Système' }}</p>
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/40 mb-2">Mouvement de stock associé</h3>
                    @if($waste->movement)
                        <p class="text-sm text-primary">Mouvement #{{ $waste->movement->id }} (Sortie)</p>
                        <p class="text-xs text-primary/50 mt-0.5">Stock après opération : {{ rtrim(rtrim(number_format((float) $waste->movement->stock_after, 3, ',', ' '), '0'), ',') }} {{ $waste->item?->unit }}</p>
                    @else
                        <p class="text-xs text-primary/40 italic">Aucun mouvement direct lié</p>
                    @endif
                </div>
            </div>

            @if($waste->notes)
                <hr class="border-secondary/15">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/40 mb-2">Circonstances / Commentaires</h3>
                    <div class="bg-secondary/5 rounded-lg p-3 text-xs text-primary leading-relaxed whitespace-pre-line border border-secondary/10">
                        {{ $waste->notes }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
