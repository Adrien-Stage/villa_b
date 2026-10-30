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
            @if($canEdit)
                <a href="{{ route('economat.purchase_requests.edit', $request) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                    <i data-lucide="edit-3" class="w-3.5 h-3.5 text-primary/70"></i> Modifier la demande
                </a>
            @endif
            <a href="{{ route('economat.purchase_requests.print', $request) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Imprimer
            </a>
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

        @if($request->supplier)
            <div class="p-3.5 bg-gray-50 border border-secondary/15 rounded-lg flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                        <i data-lucide="truck" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-semibold text-primary/50 tracking-wider block">Fournisseur retenu</span>
                        <span class="text-sm font-bold text-primary">{{ $request->supplier->name }}</span>
                        @if($request->supplier->contact_name)
                            <span class="text-xs text-primary/60">· {{ $request->supplier->contact_name }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 text-xs text-primary/70">
                    @if($request->supplier->phone)
                        <span class="inline-flex items-center gap-1"><i data-lucide="phone" class="w-3.5 h-3.5 text-primary/50"></i> {{ $request->supplier->phone }}</span>
                    @endif
                    @if($request->supplier->email)
                        <span class="inline-flex items-center gap-1"><i data-lucide="mail" class="w-3.5 h-3.5 text-primary/50"></i> {{ $request->supplier->email }}</span>
                    @endif
                    @droit('economat.suppliers.voir')
                        <a href="{{ route('economat.suppliers.index') }}" class="text-xs text-primary underline hover:text-primary/70 ml-2">Fichier fournisseur</a>
                    @enddroit
                </div>
            </div>
        @endif

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
                    <span class="text-xs text-blue-700">Validée le {{ $request->reviewed_at?->format('d/m/Y') }} par {{ $request->reviewedBy?->name ?? 'Le responsable des achats' }}{{ $request->aEteAjustee() ? ', quantités ajustées' : '' }}.
                        {{ $canManage ? '' : 'Elle est chez le responsable des achats, qui en tire le(s) bon(s) de commande.' }}</span>
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
                <span class="font-bold text-xs text-emerald-800 uppercase block">
                    {{ $request->archived_at ? 'Commandée — archivée le ' . $request->archived_at->format('d/m/Y') . ', bon(s) envoyé(s) au fournisseur :' : 'Bon(s) de commande fournisseur généré(s), en attente d\'envoi :' }}
                </span>
                <div class="flex flex-wrap gap-2">
                    @foreach($request->purchaseOrders as $po)
                        {{-- Le chef de service suit sa demande sans tenir l'économat :
                             le bon de commande lui est nommé, pas ouvert. --}}
                        @droit('economat.orders.voir')
                            <a href="{{ route('economat.orders.show', $po) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 rounded-lg text-xs font-mono font-bold hover:bg-emerald-100 transition-colors">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i> {{ $po->number }} ({{ $po->supplier?->name ?? 'Fournisseur' }})
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 rounded-lg text-xs font-mono font-bold">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i> {{ $po->number }} ({{ $po->supplier?->name ?? 'Fournisseur' }})
                            </span>
                        @enddroit
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Barre d'actions : l'approbation porte les quantités saisies dans le tableau. --}}
        @if($request->isPending() && ($canReview || $peutAnnuler))
            <div class="flex flex-wrap items-end gap-2 pt-3 border-t border-secondary/15">
                @if($canReview)
                    <form id="form-approbation" method="POST" action="{{ route('economat.purchase_requests.approve', $request) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="block">
                            <span class="block text-[11px] font-medium text-primary/60 mb-1">Note pour le demandeur (facultatif)</span>
                            <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" class="w-72 max-w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:outline-none focus:border-primary">
                        </label>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Valider aux quantités retenues
                        </button>
                    </form>
                    <button type="button" @click="showRejectModal = true" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Refuser la demande
                    </button>
                    <p class="w-full text-[11px] text-primary/50">Ajustez au besoin les quantités dans le tableau ci-dessous avant de valider : 0 écarte l'article. Le demandeur est averti de chaque changement.</p>
                @endif

                @if($peutAnnuler && $request->canBeCancelled())
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
                    @php
                        // L'approbateur saisit ici la quantité qu'il retient ; une fois
                        // décidée, la quantité approuvée s'affiche à côté de la demandée.
                        $saisie = $canReview && $request->isPending();
                        $approuvee = $saisie || $request->lines->contains(fn ($l) => $l->quantity_approved !== null);
                    @endphp
                    <tr>
                        <th class="px-5 py-3 text-left">Article</th>
                        <th class="px-3 py-3 text-center">Unité</th>
                        <th class="px-5 py-3 text-right">Quantité demandée</th>
                        @if($approuvee)
                            <th class="px-5 py-3 text-right">Quantité approuvée</th>
                        @endif
                        <th class="px-5 py-3 text-right">Prix du fournisseur</th>
                        <th class="px-5 py-3 text-right">Total estimé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($request->lines as $line)
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-5 py-3 text-primary font-medium">
                                {{ $line->item?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-center font-mono font-bold text-xs text-primary/70">
                                {{ $line->item?->shortUnit() ?? $line->item?->unit }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-primary">
                                {{ rtrim(rtrim(number_format($line->quantity_requested, 3, ',', ' '), '0'), ',') }}
                            </td>
                            @if($approuvee)
                                <td class="px-5 py-3 text-right font-mono">
                                    @if($saisie)
                                        <input type="number" form="form-approbation" name="quantities[{{ $line->id }}]" min="0" step="0.001"
                                               value="{{ old('quantities.' . $line->id, rtrim(rtrim(number_format((float) $line->quantity_requested, 3, '.', ''), '0'), '.')) }}"
                                               aria-label="Quantité approuvée pour {{ $line->item?->name }}"
                                               class="w-24 rounded-md border border-secondary/30 px-2 py-1 text-right text-sm text-primary focus:outline-none focus:border-primary">
                                    @elseif($line->quantity_approved !== null)
                                        <span class="font-bold {{ $line->estAjustee() ? 'text-amber-700' : 'text-primary' }}">
                                            {{ rtrim(rtrim(number_format($line->quantity_approved, 3, ',', ' '), '0'), ',') }}
                                        </span>
                                        @if($line->estAjustee())<span class="block text-[10px] text-amber-700">ajustée</span>@endif
                                    @endif
                                </td>
                            @endif
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
                        <td colspan="{{ $approuvee ? 5 : 4 }}" class="px-5 py-3 text-right">Total estimé de la demande :</td>
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

@php
        $aCommander = $request->lines->filter(fn ($l) => $l->quantiteRetenue() > 0)->values();
        $lignesConversion = $aCommander->map(fn ($l) => [
            'id' => $l->id,
            'nom' => $l->item?->name ?? 'Article',
            'quantite' => (float) $l->quantiteRetenue(),
            'unite' => $l->item?->shortUnit() ?? '',
            'item_id' => $l->stock_item_id,
            // Une demande antérieure portait déjà son fournisseur : il est proposé.
            'fournisseurs' => $comparaisons[$l->id] ?? [],
        ])->values();
    @endphp
    {{-- Modal de conversion en bons de commande : le fournisseur de chaque article se choisit ici. --}}
    <div x-show="showConvertModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-6xl w-full p-6 space-y-4 max-h-[92vh] flex flex-col"
             @click.outside="showConvertModal = false"
             x-data="conversionEnBons({{ Js::from($lignesConversion) }}, {{ Js::from($suppliers->map(fn ($s) => ['id' => $s->id, 'nom' => $s->name])->values()) }}, {{ Js::from($request->supplier_id) }})">
            <div class="shrink-0">
                <h3 class="text-base font-bold text-primary">Générer les bons de commande</h3>
                <p class="text-xs text-primary/60 mt-0.5">
                    Choisissez le fournisseur de chaque article : la carte compare leur dernière livraison et leurs prix sur une période.
                    Un bon est créé par fournisseur, au prix de sa dernière livraison.
                </p>
            </div>

            <form method="POST" action="{{ route('economat.purchase_requests.convert', $request) }}" @submit="verifier($event)"
                  class="grid grid-cols-1 lg:grid-cols-5 gap-4 min-h-0 flex-1 overflow-hidden">
                @csrf

                {{-- Les articles et leur fournisseur --}}
                <div class="lg:col-span-3 flex flex-col min-h-0 border border-secondary/20 rounded-lg overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b border-secondary/15 flex items-center justify-between shrink-0">
                        <span class="text-xs font-semibold text-primary uppercase">Fournisseur par article</span>
                        <button type="button" @click="moinsCherPartout()" class="text-[11px] font-semibold text-primary underline hover:text-primary/70">Prendre le moins cher partout</button>
                    </div>
                    <div class="overflow-y-auto divide-y divide-secondary/10 flex-1">
                        <template x-for="ligne in lignes" :key="ligne.id">
                            <div class="p-3 flex items-center justify-between gap-3" :class="active === ligne.id ? 'bg-accent/20' : 'hover:bg-gray-50/50'">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-primary truncate" x-text="ligne.nom"></div>
                                    <div class="text-xs text-primary/50" x-text="'Qté : ' + formatNombre(ligne.quantite) + ' ' + ligne.unite"></div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <input type="hidden" :name="'line_suppliers[' + ligne.id + ']'" :value="ligne.choix?.id ?? ''">
                                    <template x-if="ligne.choix">
                                        <div>
                                            <div class="text-xs font-semibold text-primary" x-text="ligne.choix.nom"></div>
                                            <div class="text-[11px] text-primary/60">
                                                <span class="font-mono" x-text="ligne.choix.prix ? formatMontant(ligne.choix.prix / 100) + ' F / ' + ligne.unite : 'Prix au bon'"></span>
                                                · <button type="button" @click="activer(ligne)" class="underline hover:text-primary">changer</button>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!ligne.choix">
                                        <button type="button" @click="activer(ligne)" class="rounded-lg border border-primary/30 px-2.5 py-1 text-xs font-semibold text-primary hover:bg-accent/30">Choisir le fournisseur →</button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="px-4 py-2 border-t border-secondary/15 bg-gray-50/70 text-[11px] text-primary/70 shrink-0" role="status">
                        <span x-text="bons().length === 0 ? 'Aucun fournisseur choisi.' : bons().length + ' bon(s) de commande : ' + bons().map(b => b.nom + ' (' + b.lignes + ' article' + (b.lignes > 1 ? 's' : '') + ')').join(' · ')"></span>
                        <p x-show="erreur" x-text="erreur" class="mt-1 font-semibold text-red-700" role="alert"></p>
                    </div>
                </div>

                {{-- La carte des fournisseurs de l'article --}}
                <aside class="lg:col-span-2 border border-secondary/20 rounded-lg overflow-y-auto" aria-live="polite">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-secondary/15">
                        <h4 class="text-xs font-semibold text-primary uppercase">Fournisseurs de l'article</h4>
                        <p class="text-[11px] text-primary/50" x-text="ligneActive() ? ligneActive().nom + ' — prix par ' + ligneActive().unite : 'Choisissez un article à gauche.'"></p>
                    </div>
                    <template x-if="ligneActive()">
                        <div class="p-3 space-y-3">
                            <fieldset class="rounded-lg border border-secondary/20 p-2.5">
                                <legend class="px-1 text-[10px] font-semibold uppercase tracking-wider text-primary/50">Comparer sur une période</legend>
                                <div class="flex items-end gap-2">
                                    <label class="flex-1 text-[11px] text-primary/60">Du
                                        <input type="date" x-model="du" class="mt-0.5 w-full px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                                    </label>
                                    <label class="flex-1 text-[11px] text-primary/60">Au
                                        <input type="date" x-model="au" class="mt-0.5 w-full px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                                    </label>
                                    <button type="button" @click="comparer()" class="px-2.5 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">Comparer</button>
                                </div>
                            </fieldset>
                            <p x-show="chargement" class="text-xs text-primary/50">Chargement…</p>
                            <template x-if="!chargement && ligneActive().fournisseurs.length === 0">
                                <p class="text-xs text-primary/60">Aucun fournisseur ne livre encore cet article. Choisissez-en un ci-dessous ; son prix se fixera sur le bon.</p>
                            </template>
                            <template x-for="f in (chargement ? [] : ligneActive().fournisseurs)" :key="f.id">
                                <div class="rounded-lg border border-secondary/20 p-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-primary" x-text="f.nom"></p>
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                <span x-show="f.moinsCher" class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-sky-200 bg-sky-50 text-sky-700">Moins cher</span>
                                                <span x-show="f.habituel" class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-700">Habituel</span>
                                            </div>
                                        </div>
                                        <button type="button" @click="choisir(f)" class="shrink-0 px-2.5 py-1 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">Choisir</button>
                                    </div>
                                    <p class="mt-1 text-xs text-primary/80">
                                        <template x-if="f.derniere"><span>Dernière livraison : <strong class="font-mono" x-text="formatMontant(f.derniere.prix / 100) + ' F'"></strong> le <span x-text="formatDate(f.derniere.date)"></span></span></template>
                                        <template x-if="!f.derniere"><span class="text-primary/50" x-text="f.tarif ? 'Pas encore livré — tarif ' + formatMontant(f.tarif / 100) + ' F' : 'Pas encore livré, aucun prix connu'"></span></template>
                                    </p>
                                    <template x-if="f.periode">
                                        <p class="mt-0.5 text-[11px] text-primary/60" x-text="f.periode.livraisons === 0 ? 'Aucune livraison sur la période'
                                            : f.periode.livraisons + ' livraison(s) sur la période : de ' + formatMontant(f.periode.min / 100) + ' à ' + formatMontant(f.periode.max / 100) + ' F, dernière à ' + formatMontant(f.periode.dernier / 100) + ' F'"></p>
                                    </template>
                                </div>
                            </template>
                            <div class="flex items-center gap-2 pt-1">
                                <select x-model="autre" aria-label="Autre fournisseur" class="flex-1 px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                                    <option value="">Autre fournisseur…</option>
                                    <template x-for="s in autresFournisseurs()" :key="s.id"><option :value="s.id" x-text="s.nom"></option></template>
                                </select>
                                <button type="button" @click="choisirAutre()" :disabled="!autre" class="px-2.5 py-1.5 border border-primary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/30 disabled:opacity-40">Choisir</button>
                            </div>
                        </div>
                    </template>
                </aside>

                <div class="lg:col-span-5 flex items-center justify-end gap-2 pt-1 shrink-0">
                    <button type="button" @click="showConvertModal = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                        <span x-text="bons().length > 1 ? 'Générer les ' + bons().length + ' bons' : 'Générer le bon'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
function conversionEnBons(lignes, fournisseurs, fournisseurDeLaDemande) {
    const baseUrl = @js(url('/economat/demandes-achat/articles'));
    const prixDe = (f) => f.derniere ? f.derniere.prix : f.tarif;

    return {
        // Une demande antérieure portait déjà son fournisseur : il est repris.
        lignes: lignes.map(l => {
            const deja = fournisseurDeLaDemande ? l.fournisseurs.find(f => f.id === fournisseurDeLaDemande) : null;
            return { ...l, choix: deja ? { id: deja.id, nom: deja.nom, prix: prixDe(deja) } : null };
        }),
        fournisseurs,
        active: null,
        chargement: false,
        du: '',
        au: '',
        autre: '',
        erreur: '',
        ligneActive() {
            return this.lignes.find(l => l.id === this.active) || null;
        },
        activer(ligne) {
            this.active = ligne.id;
            this.autre = '';
            if (this.du || this.au) this.comparer();
        },
        async comparer() {
            const ligne = this.ligneActive();
            if (!ligne) return;
            const params = new URLSearchParams();
            if (this.du) params.set('du', this.du);
            if (this.au) params.set('au', this.au);
            this.chargement = true;
            try {
                const reponse = await fetch(`${baseUrl}/${ligne.item_id}/fournisseurs?${params}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (reponse.ok) ligne.fournisseurs = (await reponse.json()).fournisseurs;
            } finally {
                this.chargement = false;
            }
        },
        // Le fournisseur choisi passe sur l'article ; la carte s'efface pour le suivant.
        choisir(f) {
            const ligne = this.ligneActive();
            if (!ligne) return;
            ligne.choix = { id: f.id, nom: f.nom, prix: prixDe(f) };
            this.erreur = '';
            this.active = null;
        },
        autresFournisseurs() {
            const connus = (this.ligneActive()?.fournisseurs || []).map(f => f.id);
            return this.fournisseurs.filter(s => !connus.includes(s.id));
        },
        choisirAutre() {
            const s = this.fournisseurs.find(x => String(x.id) === String(this.autre));
            if (s) this.choisir({ id: s.id, nom: s.nom, derniere: null, tarif: null });
        },
        moinsCherPartout() {
            this.lignes.forEach(l => {
                const f = l.fournisseurs.find(x => x.moinsCher) || (l.fournisseurs.length === 1 && prixDe(l.fournisseurs[0]) ? l.fournisseurs[0] : null);
                if (f) l.choix = { id: f.id, nom: f.nom, prix: prixDe(f) };
            });
            this.active = null;
        },
        // Un bon par fournisseur.
        bons() {
            const groupes = {};
            this.lignes.filter(l => l.choix).forEach(l => {
                const g = groupes[l.choix.id] ??= { nom: l.choix.nom, lignes: 0 };
                g.lignes++;
            });
            return Object.values(groupes);
        },
        verifier(event) {
            const sans = this.lignes.filter(l => !l.choix).length;
            this.erreur = sans > 0 ? (sans > 1 ? sans + ' articles n\'ont' : 'Un article n\'a') + ' pas encore de fournisseur.' : '';
            if (this.erreur) event.preventDefault();
        },
        formatMontant(v) { return new Intl.NumberFormat('fr-FR').format(Math.round(v || 0)); },
        formatNombre(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(v || 0); },
        formatDate(iso) { if (!iso) return ''; const [a, m, j] = String(iso).slice(0, 10).split('-'); return `${j}/${m}/${a}`; },
    };
}
</script>
</div>
@endsection
