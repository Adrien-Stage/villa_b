@extends('layouts.hotel')

@section('title', $supplier->name . ' — Fournisseurs')

@php
    $fcfa = fn ($centimes) => number_format(((int) $centimes) / 100, 0, ',', ' ');
    $coordonnees = [
        'Contact' => $supplier->contact_name,
        'Téléphone' => $supplier->phone,
        'Email' => $supplier->email,
        'Adresse' => collect([$supplier->address, $supplier->city])->filter()->implode(', '),
        'NIU' => $supplier->tax_id,
        'RCCM' => $supplier->rccm,
    ];
    $conditions = [
        'Paiement' => $supplier->payment_terms ? $supplier->payment_terms_label : null,
        'Mode de paiement' => $supplier->payment_method ? $supplier->payment_method_label : null,
        'Délai de livraison' => $supplier->delivery_lead_time_days !== null ? $supplier->delivery_lead_time_days . ' jour(s)' : null,
    ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    {{-- En-tête --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ route('economat.suppliers.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour aux fournisseurs">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-heading font-semibold text-primary">{{ $supplier->name }}</h1>
                    @if($supplier->code)
                        <span class="px-1.5 py-0.5 text-[11px] font-mono bg-gray-100 text-primary/70 rounded">{{ $supplier->code }}</span>
                    @endif
                    @if(! $supplier->is_active)
                        <span class="px-1.5 py-0.5 text-[11px] font-medium bg-red-100 text-red-700 rounded">Inactif</span>
                    @endif
                </div>
                <p class="text-sm text-primary/60 mt-0.5">
                    {{ $supplier->category ? $supplier->category_label : 'Fournisseur' }}{{ $supplier->city ? ' · ' . $supplier->city : '' }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('economat.suppliers.print', $supplier) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer la fiche
            </a>
            @droit('economat.orders.creer')
                <a href="{{ route('economat.orders.create', ['fournisseur' => $supplier->id]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i> Commander
                </a>
            @enddroit
        </div>
    </div>

    {{-- Chiffres --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            ['Articles au catalogue', $chiffres['articles'], null],
            ['Livraisons reçues', $chiffres['livraisons'], $chiffres['derniere'] ? 'Dernière le ' . \Illuminate\Support\Carbon::parse($chiffres['derniere'])->format('d/m/Y') : 'Aucune encore'],
            ['Livré sur 12 mois', $fcfa($chiffres['montant_12_mois']) . ' F', null],
            ['Livré depuis le début', $fcfa($chiffres['montant_total']) . ' F', null],
            ['Bons en cours', $chiffres['bons_en_cours'], $attendues->count() > 0 ? $attendues->count() . ' livraison(s) attendue(s)' : null],
        ] as [$libelle, $valeur, $detail])
            <div class="bg-white border border-secondary/20 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">{{ $libelle }}</p>
                <p class="text-lg font-heading font-bold text-primary mt-1">{{ $valeur }}</p>
                @if($detail)<p class="text-[11px] text-primary/50 mt-0.5">{{ $detail }}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- Informations --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="bg-white border border-secondary/20 rounded-xl p-5" aria-labelledby="titre-coordonnees">
            <h2 id="titre-coordonnees" class="text-sm font-semibold text-primary mb-3">Coordonnées</h2>
            <dl class="space-y-1.5 text-sm">
                @foreach($coordonnees as $libelle => $valeur)
                    <div class="flex gap-3"><dt class="w-24 shrink-0 text-primary/50">{{ $libelle }}</dt><dd class="text-primary">{{ $valeur ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section class="bg-white border border-secondary/20 rounded-xl p-5" aria-labelledby="titre-conditions">
            <h2 id="titre-conditions" class="text-sm font-semibold text-primary mb-3">Conditions commerciales</h2>
            <dl class="space-y-1.5 text-sm">
                @foreach($conditions as $libelle => $valeur)
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-primary/50">{{ $libelle }}</dt><dd class="text-primary">{{ $valeur ?: '—' }}</dd></div>
                @endforeach
                @if($voitBanque)
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-primary/50">Coordonnées bancaires</dt><dd class="text-primary font-mono text-xs">{{ $supplier->bank_details ?: '—' }}</dd></div>
                @endif
            </dl>
        </section>
        <section class="bg-white border border-secondary/20 rounded-xl p-5" aria-labelledby="titre-notes">
            <h2 id="titre-notes" class="text-sm font-semibold text-primary mb-3">Notes et accords</h2>
            <p class="text-sm text-primary/70 whitespace-pre-line">{{ $supplier->notes ?: 'Aucune note.' }}</p>
        </section>
    </div>

    {{-- Catalogue --}}
    <section class="bg-white border border-secondary/20 rounded-xl overflow-hidden" aria-labelledby="titre-catalogue">
        <div class="px-5 py-3.5 border-b border-secondary/15 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
            <h2 id="titre-catalogue" class="text-sm font-semibold text-primary">Articles qu'il nous livre <span class="text-primary/40 font-normal">({{ $catalogue->count() }})</span></h2>
            <p class="text-xs text-primary/50">Prix d'achat dans l'unité de stock de chaque article ; chaque réception met à jour le sien.</p>
        </div>
        @if($catalogue->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-primary/50">Aucun article au catalogue de ce fournisseur.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Article</th>
                            <th class="px-4 py-2.5 text-left">Catégorie</th>
                            <th class="px-4 py-2.5 text-right">Prix d'achat</th>
                            <th class="px-4 py-2.5 text-left">Prix du</th>
                            <th class="px-4 py-2.5 text-left">Face aux autres</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($catalogue as $ligne)
                            <tr>
                                <td class="px-5 py-2.5">
                                    <span class="font-medium text-primary">{{ $ligne['item']->name }}</span>
                                    @if($ligne['item']->reference)<span class="ml-1 text-[11px] font-mono text-primary/40">{{ $ligne['item']->reference }}</span>@endif
                                </td>
                                <td class="px-4 py-2.5 text-primary/60">{{ $ligne['item']->category?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold text-primary whitespace-nowrap">
                                    @if($ligne['prix'] > 0)
                                        {{ $fcfa($ligne['prix']) }} F <span class="text-[11px] font-sans font-normal text-primary/40">/ {{ $ligne['item']->shortUnit() }}</span>
                                    @else
                                        <span class="text-xs font-sans text-amber-700">À renseigner</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-primary/60 whitespace-nowrap">{{ $ligne['date']?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="flex flex-wrap gap-1">
                                        @if($ligne['habituel'])<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-700">Fournisseur habituel</span>@endif
                                        @if($ligne['moinsCher'])<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-sky-200 bg-sky-50 text-sky-700">Moins cher</span>@endif
                                        @if($ligne['autres'] > 0)
                                            <span class="text-[11px] text-primary/50">{{ $ligne['autres'] }} autre(s) fournisseur(s)</span>
                                        @elseif(! $ligne['habituel'] && ! $ligne['moinsCher'])
                                            <span class="text-[11px] text-primary/40">Seul fournisseur</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Commandes en cours et livraisons attendues --}}
    @if($enCours->isNotEmpty())
        <section class="bg-white border border-secondary/20 rounded-xl overflow-hidden" aria-labelledby="titre-en-cours">
            <div class="px-5 py-3.5 border-b border-secondary/15 bg-gray-50/80">
                <h2 id="titre-en-cours" class="text-sm font-semibold text-primary">Bons de commande en cours <span class="text-primary/40 font-normal">({{ $enCours->count() }})</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Bon</th>
                            <th class="px-4 py-2.5 text-left">Envoyé le</th>
                            <th class="px-4 py-2.5 text-left">Attendu le</th>
                            <th class="px-4 py-2.5 text-left">Statut</th>
                            <th class="px-4 py-2.5 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($enCours as $bon)
                            <tr>
                                <td class="px-5 py-2.5 font-mono text-xs">
                                    @if($voitBons)<a href="{{ route('economat.orders.show', $bon) }}" class="text-primary hover:underline">{{ $bon->number }}</a>@else{{ $bon->number }}@endif
                                </td>
                                <td class="px-4 py-2.5 text-primary/70">{{ $bon->sent_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-primary/70">{{ $bon->expected_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-primary/70">{{ $bon->statusLabel() }}</td>
                                <td class="px-4 py-2.5 text-right font-mono">{{ $fcfa($bon->total_amount) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Historique des livraisons --}}
    <section class="bg-white border border-secondary/20 rounded-xl overflow-hidden" aria-labelledby="titre-livraisons">
        <div class="px-5 py-3.5 border-b border-secondary/15 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
            <h2 id="titre-livraisons" class="text-sm font-semibold text-primary">Historique des livraisons</h2>
            @if($livraisons->count() >= \App\Support\FicheFournisseur::LIVRAISONS_A_L_ECRAN)
                <p class="text-xs text-primary/50">Les {{ \App\Support\FicheFournisseur::LIVRAISONS_A_L_ECRAN }} plus récentes ; le registre complet est dans Éditions.</p>
            @endif
        </div>
        @if($livraisons->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-primary/50">Aucune livraison réceptionnée pour l'instant.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Reçu le</th>
                            <th class="px-4 py-2.5 text-left">Bon d'entrée</th>
                            <th class="px-4 py-2.5 text-left">Bon de commande</th>
                            <th class="px-4 py-2.5 text-left">Bordereau</th>
                            <th class="px-4 py-2.5 text-left">Livraison</th>
                            <th class="px-4 py-2.5 text-left">Réceptionnaire</th>
                            <th class="px-4 py-2.5 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($livraisons as $recu)
                            <tr class="{{ $recu->status === \App\Models\GoodsReceipt::STATUS_CANCELLED ? 'text-primary/40 line-through' : '' }}">
                                <td class="px-5 py-2.5 whitespace-nowrap">{{ $recu->received_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs">
                                    @if($voitReceptions)<a href="{{ route('economat.receipts.print', $recu) }}" target="_blank" rel="noopener" class="text-primary hover:underline">{{ $recu->number }}</a>@else{{ $recu->number }}@endif
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $recu->purchaseOrder?->number ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-primary/70">
                                    {{ $recu->delivery_note_number ?: '—' }}
                                    @if($recu->attachments_count > 0)<span class="text-[11px] text-primary/40">· {{ $recu->attachments_count }} pièce(s)</span>@endif
                                </td>
                                <td class="px-4 py-2.5 text-primary/70">{{ $recu->status === \App\Models\GoodsReceipt::STATUS_CANCELLED ? 'Annulée' : ($recu->deliveryLabel() ?? '—') }}</td>
                                <td class="px-4 py-2.5 text-primary/70">{{ $recu->receivedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono">{{ $fcfa($recu->total_amount) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
