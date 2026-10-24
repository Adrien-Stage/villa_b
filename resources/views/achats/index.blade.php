@extends('layouts.hotel')

@section('title', 'Achats')

@section('content')
@php
    $fcfa = fn ($centimes) => number_format(((int) $centimes) / 100, 0, ',', ' ') . ' F';
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    $carte = 'bg-white border border-secondary/20 rounded-xl px-4 py-3';
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Achats</h1>
            <p class="text-sm text-primary/60 mt-0.5 max-w-3xl">
                Le circuit d'achat au {{ $aujourdhui->locale('fr')->isoFormat('D MMMM YYYY') }} : la demande est approuvée par le
                contrôle de gestion, le responsable des achats en tire le bon de commande et l'envoie, l'économat réceptionne.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @droit('economat.purchase_requests.creer')
                <a href="{{ route('economat.purchase_requests.create') }}" class="px-4 py-2 border border-secondary/30 text-sm font-semibold rounded-lg text-primary hover:bg-accent/20">Nouvelle demande d'achat</a>
            @enddroit
            @if($peutProposer)
                <a href="{{ route('economat.control.suggestions.index') }}" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Réapprovisionner</a>
            @endif
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Les compteurs du circuit, dans son ordre --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        @if($voitDemandes)
            <a href="{{ route('economat.purchase_requests.index', ['status' => 'pending']) }}" class="{{ $carte }} hover:border-primary/40">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">À approuver</p>
                <p class="text-xl font-heading font-bold {{ $aApprouver->isNotEmpty() ? 'text-amber-700' : 'text-primary' }} mt-0.5">{{ $aApprouver->count() }}</p>
                <p class="text-[10px] text-primary/40">demandes en attente</p>
            </a>
            <a href="{{ route('economat.purchase_requests.index', ['status' => 'approved']) }}" class="{{ $carte }} hover:border-primary/40">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">À commander</p>
                <p class="text-xl font-heading font-bold {{ $aCommander->isNotEmpty() ? 'text-blue-700' : 'text-primary' }} mt-0.5">{{ $aCommander->count() }}</p>
                <p class="text-[10px] text-primary/40">demandes approuvées</p>
            </a>
        @endif
        @if($voitBons)
            <a href="{{ route('economat.orders.index', ['statut' => 'draft']) }}" class="{{ $carte }} hover:border-primary/40">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">À envoyer</p>
                <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $aEnvoyer->count() }}</p>
                <p class="text-[10px] text-primary/40">bons prêts</p>
            </a>
            <a href="{{ route('economat.orders.index', ['statut' => 'sent']) }}" class="{{ $carte }} hover:border-primary/40">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">Livraisons attendues</p>
                <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $attendues->count() }}</p>
                <p class="text-[10px] {{ $enRetard->isNotEmpty() ? 'text-red-600 font-medium' : 'text-primary/40' }}">{{ $enRetard->count() }} en retard</p>
            </a>
            <div class="{{ $carte }}">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">Engagé ce mois</p>
                <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $fcfa($engage) }}</p>
                <p class="text-[10px] text-primary/40">bons envoyés depuis le 1er</p>
            </div>
        @endif
        @if($voitAlertes)
            <a href="{{ route('economat.control.suggestions.index') }}" class="{{ $carte }} hover:border-primary/40">
                <p class="text-[10px] uppercase tracking-wider text-primary/45">Alertes de stock</p>
                <p class="text-xl font-heading font-bold {{ $alertes->isNotEmpty() ? 'text-red-600' : 'text-primary' }} mt-0.5">{{ $alertes->count() }}</p>
                <p class="text-[10px] text-primary/40">articles sous le seuil</p>
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- 1. À approuver --}}
        @if($voitDemandes)
            <section class="bg-white border border-secondary/20 rounded-xl shadow-sm" aria-labelledby="titre-approuver">
                <header class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 id="titre-approuver" class="text-sm font-semibold text-primary">Demandes à approuver</h2>
                    <span class="text-[11px] text-primary/45">{{ $peutApprouver ? 'Votre décision est attendue' : 'Chez le contrôle de gestion' }}</span>
                </header>
                @forelse($aApprouver->take($lignes) as $demande)
                    <a href="{{ route('economat.purchase_requests.show', $demande) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-secondary/10 last:border-0 hover:bg-gray-50/60">
                        <span class="min-w-0">
                            <span class="font-mono text-sm font-bold text-primary">{{ $demande->number }}</span>
                            @if($demande->priority === 'urgent')<span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">Urgente</span>@endif
                            <span class="block text-xs text-primary/55 truncate">{{ $demande->departmentLabel() }} — {{ $demande->requestedBy?->name ?? '—' }}, {{ $demande->lines_count }} article(s)</span>
                        </span>
                        <span class="text-right shrink-0">
                            <span class="block font-mono text-sm font-semibold text-primary">{{ $fcfa($demande->total_estimated_amount) }}</span>
                            <span class="block text-[10px] text-primary/40">{{ $demande->created_at->format('d/m/Y') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-primary/45">Aucune demande en attente.</p>
                @endforelse
            </section>

            {{-- 2. À commander --}}
            <section class="bg-white border border-secondary/20 rounded-xl shadow-sm" aria-labelledby="titre-commander">
                <header class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 id="titre-commander" class="text-sm font-semibold text-primary">Demandes approuvées à commander</h2>
                    <span class="text-[11px] text-primary/45">{{ $peutCommander ? 'Générez les bons' : 'Chez le responsable des achats' }}</span>
                </header>
                @forelse($aCommander->take($lignes) as $demande)
                    <a href="{{ route('economat.purchase_requests.show', $demande) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-secondary/10 last:border-0 hover:bg-gray-50/60">
                        <span class="min-w-0">
                            <span class="font-mono text-sm font-bold text-primary">{{ $demande->number }}</span>
                            @if($demande->priority === 'urgent')<span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">Urgente</span>@endif
                            <span class="block text-xs text-primary/55 truncate">Approuvée le {{ $demande->reviewed_at?->format('d/m/Y') }} par {{ $demande->reviewedBy?->name ?? '—' }}</span>
                        </span>
                        <span class="font-mono text-sm font-semibold text-primary shrink-0">{{ $fcfa($demande->total_estimated_amount) }}</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-primary/45">Aucune demande approuvée en attente de commande.</p>
                @endforelse
            </section>
        @endif

        {{-- 3. Bons à envoyer --}}
        @if($voitBons)
            <section class="bg-white border border-secondary/20 rounded-xl shadow-sm" aria-labelledby="titre-envoyer">
                <header class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 id="titre-envoyer" class="text-sm font-semibold text-primary">Bons à envoyer aux fournisseurs</h2>
                    <span class="text-[11px] text-primary/45">{{ $peutEnvoyer ? 'Envoyez ou marquez transmis' : 'Chez le responsable des achats' }}</span>
                </header>
                @forelse($aEnvoyer->take($lignes) as $bon)
                    <a href="{{ route('economat.orders.show', $bon) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-secondary/10 last:border-0 hover:bg-gray-50/60">
                        <span class="min-w-0">
                            <span class="font-mono text-sm font-bold text-primary">{{ $bon->number }}</span>
                            <span class="block text-xs text-primary/55 truncate">{{ $bon->supplier?->name ?? '—' }}, {{ $bon->lines_count }} article(s)</span>
                        </span>
                        <span class="font-mono text-sm font-semibold text-primary shrink-0">{{ $fcfa($bon->total_amount) }}</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-primary/45">Aucun bon en attente d'envoi.</p>
                @endforelse
            </section>

            {{-- 4. Livraisons attendues --}}
            <section class="bg-white border border-secondary/20 rounded-xl shadow-sm" aria-labelledby="titre-livraisons">
                <header class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 id="titre-livraisons" class="text-sm font-semibold text-primary">Livraisons attendues</h2>
                    <span class="text-[11px] text-primary/45">Réception par l'économat</span>
                </header>
                @forelse($attendues->take($lignes) as $bon)
                    @php $retard = $enRetard->contains('id', $bon->id); @endphp
                    <a href="{{ route('economat.orders.show', $bon) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-secondary/10 last:border-0 hover:bg-gray-50/60">
                        <span class="min-w-0">
                            <span class="font-mono text-sm font-bold text-primary">{{ $bon->number }}</span>
                            <span class="block text-xs text-primary/55 truncate">{{ $bon->supplier?->name ?? '—' }} — {{ $bon->statusLabel() }}</span>
                        </span>
                        <span class="text-right shrink-0 text-xs {{ $retard ? 'text-red-600 font-semibold' : 'text-primary/55' }}">
                            {{ $bon->expected_at ? ($retard ? 'En retard depuis le ' : 'Attendue le ') . $bon->expected_at->format('d/m/Y') : 'Sans date prévue' }}
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-primary/45">Aucune livraison attendue.</p>
                @endforelse
            </section>
        @endif

        {{-- 5. Alertes de stock --}}
        @if($voitAlertes)
            <section class="bg-white border border-secondary/20 rounded-xl shadow-sm lg:col-span-2" aria-labelledby="titre-alertes">
                <header class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 id="titre-alertes" class="text-sm font-semibold text-primary">Alertes de stock</h2>
                    @if($peutProposer && $alertes->isNotEmpty())
                        <a href="{{ route('economat.control.suggestions.index') }}" class="text-xs font-semibold text-primary hover:underline">Proposer une demande d'achat →</a>
                    @endif
                </header>
                @if($alertes->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-primary/45">Aucun article sous son seuil d'alerte.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Articles sous le seuil d'alerte</caption>
                            <thead class="text-[10px] uppercase tracking-wider text-primary/45">
                                <tr class="border-b border-secondary/10">
                                    <th scope="col" class="px-4 py-2 text-left font-medium">Article</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium">Fournisseur habituel</th>
                                    <th scope="col" class="px-4 py-2 text-right font-medium">En stock</th>
                                    <th scope="col" class="px-4 py-2 text-right font-medium">Seuil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alertes->take($lignes) as $article)
                                    <tr class="border-b border-secondary/10 last:border-0">
                                        <td class="px-4 py-2 text-primary font-medium">{{ $article->name }}</td>
                                        <td class="px-4 py-2 text-primary/60">{{ $article->supplier?->name ?? 'Non défini' }}</td>
                                        <td class="px-4 py-2 text-right font-mono {{ (float) $article->current_stock <= 0 ? 'text-red-600 font-semibold' : 'text-primary' }}">{{ $qte($article->current_stock) }} {{ $article->unit }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-primary/60">{{ $qte($article->min_stock) }} {{ $article->unit }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($alertes->count() > $lignes)
                        <p class="px-4 py-2 text-[11px] text-primary/45">Et {{ $alertes->count() - $lignes }} autre(s) article(s).</p>
                    @endif
                @endif
            </section>
        @endif
    </div>

    @if($fournisseurs !== null)
        <p class="text-xs text-primary/45">{{ $fournisseurs }} fournisseur(s) actif(s) au fichier.
            <a href="{{ route('economat.suppliers.index') }}" class="font-semibold text-primary hover:underline">Ouvrir le fichier des fournisseurs</a>
        </p>
    @endif
</div>
@endsection
