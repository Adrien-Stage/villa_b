@extends('layouts.hotel')

@section('title', $demande->reference)

@section('content')
@php
    $qte = fn ($q) => rtrim(rtrim(number_format(abs((float) $q), 3, ',', ' '), '0'), ',');
    $champ = 'w-full rounded-lg border border-secondary/30 text-sm px-3 py-2 focus:ring-1 focus:ring-primary focus:border-primary';
    $bouton = 'inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors';
@endphp
<div class="max-w-6xl mx-auto">
    <a href="{{ route('technique.demandes.index') }}" class="text-xs text-primary/60 hover:text-primary inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5" aria-hidden="true"></i> Demandes d'intervention
    </a>
    <div class="mt-1 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $demande->title }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $demande->reference }} · {{ $demande->lieu() }} · {{ $demande->specialtyLabel() }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('technique.demandes.print', $demande) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-secondary/30 bg-white text-primary text-xs font-semibold hover:bg-accent/20 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4 text-secondary"></i> Imprimer la fiche
            </a>
            <span class="inline-flex flex-wrap gap-1.5">@include('technique.partials.badges')</span>
        </div>
    </div>

    @include('economat.partials.flash')

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Colonne principale : Demande et Fiche d'intervention --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- 1. Détails du signalement initial --}}
            <section class="bg-white rounded-xl border border-secondary/20 p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-secondary/15">
                    <h2 class="font-heading font-semibold text-primary text-base flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-secondary"></i> Signalement initial
                    </h2>
                    <span class="text-xs font-mono font-bold text-primary/70">{{ $demande->reference }}</span>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-xs text-primary/50">Demandée par</dt><dd class="text-primary font-medium">{{ $demande->requester?->name ?? '—' }} · {{ $demande->departmentLabel() }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Date de signalement</dt><dd class="text-primary">{{ $demande->created_at->format('d/m/Y à H:i') }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Technicien assigné</dt><dd class="text-primary font-medium">{{ $demande->technician?->name ?? 'Non encore assigné' }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Localisation</dt>
                        <dd class="text-primary">
                            {{ $demande->lieu() }}
                            @if($demande->room)
                                <span class="text-xs text-primary/60">({{ $demande->room->status->label() }})</span>
                            @endif
                            @if($demande->room_blocked)
                                <span class="block text-xs text-orange-700 font-semibold">{{ $demande->isActive() ? '(Chambre retirée de la vente)' : '(Chambre retirée de la vente durant l\'intervention)' }}</span>
                            @endif
                        </dd>
                    </div>
                    @if($demande->dureeMinutes() !== null)
                        <div><dt class="text-xs text-primary/50">Durée d'intervention</dt><dd class="text-primary font-medium">{{ intdiv($demande->dureeMinutes(), 60) }} h {{ str_pad($demande->dureeMinutes() % 60, 2, '0', STR_PAD_LEFT) }}</dd></div>
                    @endif
                    @if($demande->source === \App\Models\MaintenanceRequest::SOURCE_HOUSEKEEPING)
                        <div><dt class="text-xs text-primary/50">Origine de la demande</dt><dd class="text-primary">Signalement du service Housekeeping / Ménage</dd></div>
                    @endif
                </dl>
                @if($demande->description)
                    <div class="mt-4 pt-4 border-t border-secondary/15">
                        <p class="text-xs font-semibold text-primary/70 mb-1">Description détaillée du problème constaté :</p>
                        <p class="text-sm text-primary/80 whitespace-pre-line bg-secondary/5 rounded-lg p-3 border border-secondary/10">{{ $demande->description }}</p>
                    </div>
                @endif
                @if($demande->cancel_reason)
                    <p class="mt-4 text-sm text-rose-700 bg-rose-50 rounded-lg p-3 border border-rose-200">
                        <span class="font-semibold">Demande annulée :</span> {{ $demande->cancel_reason }}
                    </p>
                @endif
            </section>

            {{-- 2. Fiche d'intervention technique (Affichage ou Formulaire structuré) --}}
            <section class="bg-white rounded-xl border border-secondary/20 p-5 shadow-xs" x-data="{ modeEdition: @js(!$demande->aFicheSoumise()) }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-secondary/15">
                    <div>
                        <h2 class="font-heading font-semibold text-primary text-base flex items-center gap-2">
                            <i data-lucide="clipboard-signature" class="w-5 h-5 text-indigo-600"></i> Fiche d'intervention technique
                        </h2>
                        <p class="text-xs text-primary/60 mt-0.5">Rapport d'exécution conforme au document imprimé de l'intervention</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($demande->aFicheSoumise() && $peut['soumettre'])
                            <button type="button" @click="modeEdition = !modeEdition"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span x-text="modeEdition ? 'Afficher la fiche' : 'Modifier la fiche'"></span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Affichage structuré de la fiche déjà soumise --}}
                @if($demande->aFicheSoumise())
                    <div x-show="!modeEdition" class="space-y-4">
                        @if($demande->status === \App\Models\MaintenanceRequest::STATUS_SUBMITTED)
                            <div class="rounded-lg bg-purple-50 border border-purple-200 p-3.5 flex items-start gap-3">
                                <span class="w-2.5 h-2.5 rounded-full bg-purple-600 animate-pulse mt-1 shrink-0"></span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-purple-900">Fiche soumise au responsable — En attente de visa</p>
                                    <p class="text-xs text-purple-800 mt-0.5">
                                        Rapport consigné par {{ $demande->submitter?->name ?? $demande->technician?->name ?? 'le technicien' }}
                                        {{ $demande->fiche_soumise_at ? 'le ' . $demande->fiche_soumise_at->format('d/m/Y à H:i') : '' }}.
                                        En attente de visa pour clôture définitive.
                                    </p>
                                </div>
                            </div>
                        @elseif($demande->isDone())
                            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 flex items-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-700"></i>
                                <p class="text-xs font-semibold text-emerald-900">
                                    Intervention visée et clôturée
                                    @if($demande->completer)
                                        par {{ $demande->completer->name }} le {{ $demande->completed_at?->format('d/m/Y à H:i') }}
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Section 1 : Diagnostic & Cause --}}
                        <div class="rounded-lg border border-secondary/15 p-3.5 bg-slate-50/50">
                            <span class="block text-xs font-bold uppercase tracking-wider text-primary/70 mb-1">
                                1. Diagnostic & Cause constatée de la panne
                            </span>
                            <p class="text-sm text-primary whitespace-pre-line">{{ $demande->diagnostic ?: 'Non précisé sur la fiche' }}</p>
                        </div>

                        {{-- Section 2 : Travaux réalisés --}}
                        <div class="rounded-lg border border-secondary/15 p-3.5 bg-slate-50/50">
                            <span class="block text-xs font-bold uppercase tracking-wider text-primary/70 mb-1">
                                2. Rapport d'exécution des travaux & Réparations réalisées
                            </span>
                            <p class="text-sm text-primary whitespace-pre-line">{{ $demande->travaux_realises ?: $demande->resolution }}</p>
                        </div>

                        {{-- Section 3 : Résultat de l'intervention --}}
                        <div class="rounded-lg border border-secondary/15 p-3.5 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="block text-xs font-bold uppercase tracking-wider text-primary/70 mb-1">
                                    3. Résultat de l'intervention
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $demande->resultatBadgeClass() }}">
                                    @if($demande->resultat === 'resolu')
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                    @elseif($demande->resultat === 'partiel')
                                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                    @else
                                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                                    @endif
                                    {{ $demande->resultatLabel() }}
                                </span>
                            </div>
                            @if($demande->technician)
                                <div class="text-right text-xs text-primary/60">
                                    Technicien intervenant : <span class="font-semibold text-primary">{{ $demande->technician->name }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Section 4 : Recommandations préventives --}}
                        @if($demande->recommandations)
                            <div class="rounded-lg border border-secondary/15 p-3.5 bg-slate-50/50">
                                <span class="block text-xs font-bold uppercase tracking-wider text-primary/70 mb-1">
                                    4. Consignes & Recommandations d'entretien
                                </span>
                                <p class="text-sm text-primary/90 whitespace-pre-line">{{ $demande->recommandations }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Formulaire structuré de soumission de la fiche d'intervention --}}
                @if($peut['soumettre'])
                    <div x-show="modeEdition" x-cloak>
                        <form id="form-fiche-intervention" method="POST" action="{{ route('technique.demandes.submit', $demande) }}" class="space-y-4" x-data="{ filtrePiece: '' }">
                            @csrf

                            <div class="bg-indigo-50/80 rounded-lg p-3.5 border border-indigo-200 text-xs text-indigo-950 mb-3 shadow-xs">
                                <div class="flex items-center gap-1.5 font-bold text-indigo-950 mb-1">
                                    <i data-lucide="shield-alert" class="w-4 h-4 text-indigo-700"></i> Fiche d'intervention obligatoire pour la clôture
                                </div>
                                <p class="text-indigo-900 leading-relaxed">
                                    <strong>Règle :</strong> pour clôturer une intervention, il faut obligatoirement renseigner et soumettre cette fiche d'intervention. Remplissez les rubriques ci-dessous fidèlement à l'imprimé officiel.
                                </p>
                            </div>

                            {{-- Rubrique 1 : Diagnostic approfondi & Cause constatée --}}
                            <div>
                                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1">
                                    1. Diagnostic & Cause constatée de la panne <span class="text-red-500">*</span>
                                </label>
                                <textarea name="diagnostic" rows="3" required maxlength="2000"
                                          placeholder="Ex: Cause de la panne : encrassement du filtre et fuite au niveau du joint de raccordement suite à usure normale..."
                                          class="{{ $champ }}">{{ old('diagnostic', $demande->diagnostic) }}</textarea>
                                <p class="text-[11px] text-primary/50 mt-0.5">Indiquez la cause technique de la panne constatée sur place.</p>
                            </div>

                            {{-- Rubrique 2 : Travaux et réparations réalisés --}}
                            <div>
                                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1">
                                    2. Rapport d'exécution des travaux & Réparations réalisées <span class="text-red-500">*</span>
                                </label>
                                <textarea name="travaux_realises" rows="5" required maxlength="3000"
                                          placeholder="Ex: Démontage et nettoyage complet du groupe, remplacement du joint d'étanchéité, serrage des raccords, test de fonctionnement et remise en service sous pression nominale..."
                                          class="{{ $champ }}">{{ old('travaux_realises', $demande->travaux_realises ?: $demande->resolution) }}</textarea>
                                <p class="text-[11px] text-primary/50 mt-0.5">Détaillez les opérations correctives et les tests d'essais menés.</p>
                            </div>

                            {{-- Rubrique 3 : Résultat de l'intervention --}}
                            <div>
                                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">
                                    3. Résultat de l'intervention <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                    @php
                                        $choisiResultat = old('resultat', $demande->resultat ?: \App\Models\MaintenanceRequest::RESULTAT_RESOLU);
                                    @endphp
                                    @foreach(\App\Models\MaintenanceRequest::RESULTATS as $cle => $libelle)
                                        <label class="flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer transition-colors text-xs hover:bg-slate-50"
                                               :class="{ 'border-primary ring-1 ring-primary bg-primary/5': '{{ $cle }}' === '{{ $choisiResultat }}' }">
                                            <input type="radio" name="resultat" value="{{ $cle }}" class="mt-0.5 text-primary focus:ring-primary"
                                                   @checked($choisiResultat === $cle)>
                                            <div>
                                                <span class="font-semibold block text-primary">{{ $libelle }}</span>
                                                <span class="text-[11px] text-primary/60">
                                                    @if($cle === 'resolu')
                                                        Équipement remis en service normal.
                                                    @elseif($cle === 'partiel')
                                                        Fonctionnel mais nécessite un suivi.
                                                    @else
                                                        Travaux supplémentaires ou pièces requises.
                                                    @endif
                                                </span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Rubrique 4 : Consignes et Recommandations préventives --}}
                            <div>
                                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1">
                                    4. Consignes & Recommandations préventives (optionnel)
                                </label>
                                <textarea name="recommandations" rows="2" maxlength="2000"
                                          placeholder="Ex: Nettoyage mensuel conseillé. Prévoir le remplacement du flexible lors du prochain arrêt technique..."
                                          class="{{ $champ }}">{{ old('recommandations', $demande->recommandations) }}</textarea>
                                <p class="text-[11px] text-primary/50 mt-0.5">Conseils pour le personnel d'étage ou préconisations d'entretien futur.</p>
                            </div>

                            {{-- Rubrique 5 : Pièces sorties du dépôt à cette occasion --}}
                            @if($depots->isNotEmpty())
                                <div class="pt-2 border-t border-secondary/15">
                                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1">
                                        5. Pièces ou matériels sortis du dépôt technique (optionnel)
                                    </label>
                                    @foreach($depots as $depot)
                                        @php
                                            $disponibles = $stocks->get($depot->id, collect())->sortBy(fn ($s) => $s->item->name);
                                        @endphp
                                        @if($disponibles->isNotEmpty())
                                            <input type="hidden" name="depot" value="{{ $depot->id }}">
                                            <p class="text-[11px] text-primary/60 mb-1">Dépôt : <span class="font-medium text-primary">{{ $depot->name }}</span></p>
                                            <input type="search" x-model="filtrePiece" placeholder="Rechercher une pièce ou référence…" class="{{ $champ }} text-xs mb-2">
                                            <div class="max-h-48 overflow-y-auto divide-y divide-secondary/10 border border-secondary/15 rounded-lg mb-2 bg-slate-50/30">
                                                @foreach($disponibles as $stock)
                                                    <label class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-accent/5 cursor-pointer"
                                                           x-show="!filtrePiece || @js(mb_strtolower($stock->item->name . ' ' . $stock->item->reference)).includes(filtrePiece.toLowerCase())">
                                                        <span class="flex-1 text-primary">
                                                            {{ $stock->item->name }}
                                                            <span class="text-primary/50">({{ $qte($stock->current_stock) }} {{ $stock->item->unit }})</span>
                                                        </span>
                                                        <input type="number" name="pieces[{{ $stock->stock_item_id }}]" min="0" step="any" max="{{ (float) $stock->current_stock }}"
                                                               class="w-24 rounded border border-secondary/30 px-2 py-1 text-right text-xs" placeholder="0">
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-center justify-end gap-3 pt-3 border-t border-secondary/15">
                                @if($demande->aFicheSoumise())
                                    <button type="button" @click="modeEdition = false"
                                            class="{{ $bouton }} border border-secondary/30 bg-white text-primary hover:bg-gray-50">
                                        Annuler
                                    </button>
                                @endif
                                <button type="submit" class="{{ $bouton }} bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                                    <i data-lucide="send" class="w-4 h-4"></i> Soumettre la fiche d'intervention pour clôture
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </section>

            {{-- 3. Tableau des pièces employées et matériels délivrés --}}
            @if($demande->parts->isNotEmpty())
                <section class="bg-white rounded-xl border border-secondary/20 p-5 shadow-xs" aria-labelledby="titre-pieces">
                    <div class="flex items-center justify-between mb-3">
                        <h2 id="titre-pieces" class="font-heading font-semibold text-primary text-base flex items-center gap-2">
                            <i data-lucide="wrench" class="w-4 h-4 text-secondary"></i> Pièces et matériels délivrés du dépôt
                        </h2>
                        <span class="text-xs font-semibold text-primary/70">
                            Total : <span class="text-primary">{{ number_format($demande->valeurDesPieces() / 100, 0, ',', ' ') }} FCFA</span>
                        </span>
                    </div>
                    <x-table :rows="$demande->parts" caption="Pièces employées">
                        <x-slot:head>
                            <x-table.col>Réf. & Désignation</x-table.col>
                            <x-table.col align="right">Quantité</x-table.col>
                            <x-table.col align="right" hide="sm">P.U. (FCFA)</x-table.col>
                            <x-table.col align="right">Total (FCFA)</x-table.col>
                            <x-table.col hide="md">Délivré le</x-table.col>
                        </x-slot:head>
                        @foreach($demande->parts as $piece)
                            @php
                                $quantiteLigne = abs((float) $piece->quantity);
                                $totalLigne = (int) round($quantiteLigne * ($piece->unit_cost / 100));
                            @endphp
                            <x-table.row>
                                <x-table.cell class="text-sm">
                                    <span class="font-medium text-primary">{{ $piece->item?->name ?? 'Article' }}</span>
                                    <span class="block text-[11px] text-primary/50">{{ $piece->item?->reference ?? '—' }}</span>
                                </x-table.cell>
                                <x-table.cell align="right" nowrap class="font-medium">
                                    {{ $qte($piece->quantity) }} <span class="text-xs text-primary/60">{{ $piece->item?->unit }}</span>
                                </x-table.cell>
                                <x-table.cell align="right" hide="sm" class="text-xs text-primary/70">
                                    {{ number_format($piece->unit_cost / 100, 0, ',', ' ') }}
                                </x-table.cell>
                                <x-table.cell align="right" class="font-semibold text-primary">
                                    {{ number_format($totalLigne, 0, ',', ' ') }}
                                </x-table.cell>
                                <x-table.cell hide="md" class="text-xs text-primary/60">
                                    {{ $piece->occurred_at?->format('d/m H:i') }}
                                </x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table>
                </section>
            @endif

            {{-- 4. Historique complet des événements --}}
            <section class="bg-white rounded-xl border border-secondary/20 p-5 shadow-xs" aria-labelledby="titre-historique">
                <h2 id="titre-historique" class="font-heading font-semibold text-primary text-base mb-3 flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-secondary"></i> Historique de l'intervention
                </h2>
                <ol class="relative border-l border-secondary/30 ml-2 space-y-4">
                    @foreach($demande->events as $etape)
                        <li class="ml-4">
                            <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-secondary/60 border-2 border-white" aria-hidden="true"></span>
                            <p class="text-sm font-medium text-primary">{{ $etape->libelle() }} <span class="font-normal text-primary/60">— {{ $etape->user?->name ?? 'Système' }}</span></p>
                            <p class="text-[11px] text-primary/50">{{ $etape->created_at?->format('d/m/Y à H:i') }}</p>
                            @if($etape->notes)
                                <p class="mt-1 text-xs text-primary/75 whitespace-pre-line bg-secondary/5 p-2 rounded border border-secondary/10">{{ $etape->notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Colonne latérale : Actions, Visa, Dépôt technique --}}
        <aside class="space-y-4">
            {{-- Visa du Responsable Technique (Fiche soumise) --}}
            @if($peut['viser'])
                <div class="bg-purple-50 rounded-xl border border-purple-200 p-4 space-y-3 shadow-xs">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-purple-700"></i>
                        <div>
                            <h2 class="font-heading font-semibold text-purple-900 text-sm">Visa & Clôture</h2>
                            <p class="text-[11px] text-purple-800">Validation et clôture définitive</p>
                        </div>
                    </div>

                    <p class="text-xs text-purple-800 leading-relaxed">
                        La fiche d'intervention a été soumise par l'équipe. Vérifiez le rapport d'exécution ci-contre et les pièces employées, puis apposez votre visa pour clôturer l'intervention.
                    </p>
                    <form method="POST" action="{{ route('technique.demandes.approve', $demande) }}" class="space-y-2.5">
                        @csrf
                        <input type="text" name="note" placeholder="Observations ou visa (optionnel)…" class="{{ $champ }}">
                        <button type="submit" class="{{ $bouton }} w-full bg-purple-700 text-white hover:bg-purple-800 shadow-sm">
                            <i data-lucide="check-check" class="w-4 h-4"></i> Viser et clôturer l'intervention
                        </button>
                    </form>

                    @if($peut['reprise'])
                        <form method="POST" action="{{ route('technique.demandes.rework', $demande) }}" class="pt-3 border-t border-purple-200 space-y-2">
                            @csrf
                            <p class="text-xs text-amber-900 font-semibold">Travail incomplet ou non satisfaisant ?</p>
                            <input type="text" name="motif" required placeholder="Motif du renvoi au technicien…" class="{{ $champ }} text-xs">
                            <button type="submit" class="{{ $bouton }} w-full bg-amber-600 text-white hover:bg-amber-700 text-xs py-1.5">
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Renvoyer pour reprise
                            </button>
                        </form>
                    @endif
                </div>
            @elseif($peut['cloture_bloquee'])
                <div class="bg-amber-50/90 rounded-xl border border-amber-200 p-4 space-y-2 shadow-xs">
                    <div class="flex items-center gap-2 text-amber-950">
                        <i data-lucide="lock" class="w-5 h-5 text-amber-700"></i>
                        <div>
                            <h2 class="font-heading font-semibold text-amber-950 text-sm">Clôture impossible</h2>
                            <p class="text-[11px] text-amber-800">Fiche d'intervention obligatoire</p>
                        </div>
                    </div>
                    <p class="text-xs text-amber-900 leading-relaxed">
                        Pour clôturer une intervention, il faut <strong>obligatoirement soumettre la fiche d'intervention</strong>. Tant que la fiche n'est pas renseignée et soumise, la clôture reste verrouillée.
                    </p>
                </div>
            @endif

            {{-- Rappel Clôture pour le Technicien --}}
            @if($peut['soumettre'] && in_array($demande->status, [\App\Models\MaintenanceRequest::STATUS_ASSIGNED, \App\Models\MaintenanceRequest::STATUS_IN_PROGRESS], true))
                <div class="bg-indigo-50/80 rounded-xl border border-indigo-200 p-4 space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-2 text-indigo-950">
                        <i data-lucide="clipboard-check" class="w-5 h-5 text-indigo-700"></i>
                        <div>
                            <h2 class="font-heading font-semibold text-indigo-950 text-sm">Pour clôturer l'intervention</h2>
                            <p class="text-[11px] text-indigo-800">Fiche d'intervention requise</p>
                        </div>
                    </div>
                    <p class="text-xs text-indigo-900 leading-relaxed">
                        Pour clôturer cette intervention, vous devez obligatoirement remplir et soumettre la fiche d'intervention ci-contre.
                    </p>
                    <button type="button" @click="modeEdition = true; document.getElementById('form-fiche-intervention')?.scrollIntoView({ behavior: 'smooth' })"
                            class="{{ $bouton }} w-full bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-xs py-2">
                        <i data-lucide="file-pen" class="w-4 h-4"></i> Remplir et soumettre la fiche
                    </button>
                </div>
            @endif

            {{-- Dépôt technique : Délivrer du matériel / pièces pour cette intervention --}}
            @if($peut['pieces'])
                <div class="bg-white rounded-xl border border-secondary/20 p-4 space-y-3 shadow-xs" x-data="{ ouvert: true, filtre: '' }">
                    <div class="flex items-center justify-between cursor-pointer" @click="ouvert = !ouvert">
                        <div class="flex items-center gap-2">
                            <i data-lucide="package-minus" class="w-5 h-5 text-indigo-600"></i>
                            <div>
                                <h2 class="font-heading font-semibold text-primary text-sm">Délivrer du matériel</h2>
                                <p class="text-[11px] text-primary/60">Prélèvement sur le dépôt technique</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-primary/50 transition-transform" :class="{ 'rotate-180': ouvert }"></i>
                    </div>

                    <div x-show="ouvert" class="space-y-3 pt-2 border-t border-secondary/15">
                        @if($depots->isEmpty())
                            <p class="text-xs text-primary/60">Aucun dépôt technique configuré.</p>
                        @else
                            @foreach($depots as $depot)
                                @php
                                    $disponibles = $stocks->get($depot->id, collect())->sortBy(fn ($s) => $s->item->name);
                                @endphp
                                <form method="POST" action="{{ route('technique.demandes.parts', $demande) }}" class="space-y-2.5">
                                    @csrf
                                    <input type="hidden" name="depot" value="{{ $depot->id }}">
                                    <div class="flex items-center justify-between text-xs text-primary/70">
                                        <span>Dépôt : <strong>{{ $depot->name }}</strong></span>
                                        <a href="{{ route('technique.depot.index') }}" target="_blank" class="text-indigo-600 hover:underline text-[11px]">Voir stock</a>
                                    </div>

                                    @if($disponibles->isEmpty())
                                        <div class="p-2.5 rounded-lg bg-secondary/5 border border-secondary/15 text-xs text-primary/60">
                                            Aucune pièce actuellement en stock dans ce dépôt.
                                            <a href="{{ route('technique.depot.index') }}" class="block text-indigo-600 hover:underline mt-1 font-medium">Gérer le dépôt technique →</a>
                                        </div>
                                    @else
                                        <input type="search" x-model="filtre" placeholder="Filtrer les pièces disponibles…" class="{{ $champ }} text-xs">
                                        <div class="max-h-48 overflow-y-auto divide-y divide-secondary/10 border border-secondary/15 rounded-lg bg-slate-50/20">
                                            @foreach($disponibles as $stock)
                                                <label class="flex items-center gap-2 px-2.5 py-1.5 text-xs hover:bg-accent/5 cursor-pointer"
                                                       x-show="!filtre || @js(mb_strtolower($stock->item->name . ' ' . $stock->item->reference)).includes(filtre.toLowerCase())">
                                                    <span class="flex-1 text-primary">
                                                        {{ $stock->item->name }}
                                                        <span class="block text-[10px] text-primary/50">Dispo : {{ $qte($stock->current_stock) }} {{ $stock->item->unit }}</span>
                                                    </span>
                                                    <input type="number" name="pieces[{{ $stock->stock_item_id }}]" min="0" step="any" max="{{ (float) $stock->current_stock }}"
                                                           class="w-20 rounded border border-secondary/30 px-2 py-1 text-right text-xs" placeholder="0"
                                                           aria-label="Quantité de {{ $stock->item->name }}">
                                                </label>
                                            @endforeach
                                        </div>
                                        <div>
                                            <input type="text" name="motif" placeholder="Motif ou affectation de la pièce (optionnel)…" class="{{ $champ }} text-xs">
                                        </div>
                                        <button type="submit" class="{{ $bouton }} w-full bg-primary text-white hover:bg-surface-dark shadow-sm text-xs py-2">
                                            <i data-lucide="package-check" class="w-4 h-4"></i> Délivrer le matériel pour cette intervention
                                        </button>
                                    @endif
                                </form>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            {{-- Prise en charge directe par un technicien --}}
            @if($peut['prendre'])
                <form method="POST" action="{{ route('technique.demandes.take', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
                    @csrf
                    <p class="text-xs text-primary/70 mb-3">Cette intervention n'est affectée à aucun technicien pour le moment.</p>
                    <button type="submit" class="{{ $bouton }} w-full bg-primary text-white hover:bg-surface-dark shadow-sm">
                        <i data-lucide="hand" class="w-4 h-4" aria-hidden="true"></i> Je prends cette intervention
                    </button>
                </form>
            @endif

            {{-- Affectation / Réaffectation par le responsable --}}
            @if($peut['affecter'])
                <form method="POST" action="{{ route('technique.demandes.assign', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-3 shadow-xs">
                    @csrf
                    <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-1.5">
                        <i data-lucide="user-plus" class="w-4 h-4 text-secondary"></i>
                        {{ $demande->assigned_to ? 'Réaffecter le technicien' : 'Affecter un technicien' }}
                    </h2>
                    <label class="block text-xs text-primary/70">Technicien
                        <select name="technician_id" required class="mt-1 {{ $champ }}">
                            <option value="">Choisir un technicien…</option>
                            @foreach($techniciens as $technicien)
                                @php
                                    $siennes = $technicien->technicianSpecialties->pluck('specialty');
                                @endphp
                                <option value="{{ $technicien->id }}" @selected($demande->assigned_to === $technicien->id)>
                                    {{ $technicien->name }}{{ $siennes->contains($demande->specialty) ? ' ★' : '' }}{{ $siennes->isNotEmpty() ? ' — ' . $siennes->map(fn ($s) => \App\Models\MaintenanceRequest::SPECIALTIES[$s] ?? $s)->implode(', ') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-xs text-primary/70">Spécialité
                            <select name="specialty" class="mt-1 {{ $champ }}">
                                @foreach(\App\Models\MaintenanceRequest::SPECIALTIES as $cle => $libelle)
                                    <option value="{{ $cle }}" @selected($demande->specialty === $cle)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs text-primary/70">Priorité
                            <select name="priority" class="mt-1 {{ $champ }}">
                                @foreach(\App\Models\MaintenanceRequest::PRIORITIES as $cle => $libelle)
                                    <option value="{{ $cle }}" @selected($demande->priority === $cle)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="text-[11px] text-primary/50">★ : technicien ayant cette spécialité déclarée.</p>
                    <button type="submit" class="{{ $bouton }} w-full bg-primary text-white hover:bg-surface-dark shadow-sm">
                        <i data-lucide="user-check" class="w-4 h-4" aria-hidden="true"></i> Affecter
                    </button>
                </form>
            @endif

            {{-- Démarrage de l'intervention --}}
            @if($peut['commencer'])
                <form method="POST" action="{{ route('technique.demandes.start', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
                    @csrf
                    <p class="text-xs text-primary/70 mb-3">L'intervention est affectée. Cliquez ci-dessous pour signaler le démarrage des travaux.</p>
                    <button type="submit" class="{{ $bouton }} w-full bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                        <i data-lucide="play" class="w-4 h-4" aria-hidden="true"></i> Commencer l'intervention
                    </button>
                </form>
            @endif

            {{-- Réouverture éventuelle par le demandeur ou responsable --}}
            @if($peut['rouvrir'])
                <form method="POST" action="{{ route('technique.demandes.reopen', $demande) }}" class="bg-white rounded-xl border border-amber-200 p-4 space-y-3 shadow-xs">
                    @csrf
                    <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-1.5">
                        <i data-lucide="rotate-ccw" class="w-4 h-4 text-amber-600"></i> Le problème persiste ?
                    </h2>
                    <p class="text-xs text-primary/60">Vous pouvez rouvrir cette demande jusqu'au {{ $demande->completed_at->addDays(\App\Models\MaintenanceRequest::DELAI_REOUVERTURE)->format('d/m/Y') }}.</p>
                    <textarea name="motif" rows="3" required maxlength="1000" placeholder="Décrivez ce qui ne fonctionne toujours pas…" class="{{ $champ }}"></textarea>
                    <button type="submit" class="{{ $bouton }} w-full bg-amber-600 text-white hover:bg-amber-700 shadow-sm">
                        <i data-lucide="rotate-ccw" class="w-4 h-4" aria-hidden="true"></i> Rouvrir la demande
                    </button>
                </form>
            @endif

            {{-- Annulation de la demande si non commencée --}}
            @if($peut['annuler'])
                <form method="POST" action="{{ route('technique.demandes.cancel', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-2 shadow-xs"
                      x-data="{ ouvert: false }">
                    @csrf
                    <button type="button" x-show="!ouvert" @click="ouvert = true" class="text-xs font-medium text-red-700 hover:underline flex items-center gap-1">
                        <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Annuler la demande…
                    </button>
                    <div x-show="ouvert" x-cloak class="space-y-2 pt-2 border-t border-secondary/15">
                        <label class="block text-xs text-primary/70">Motif de l'annulation :</label>
                        <input type="text" name="motif" maxlength="500" required placeholder="Pourquoi annuler cette demande ?" class="{{ $champ }}">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="ouvert = false" class="text-xs text-primary/60 hover:underline px-2 py-1">Fermer</button>
                            <button type="submit" class="{{ $bouton }} flex-1 border border-red-200 text-red-700 hover:bg-red-50 text-xs py-1.5">
                                Confirmer l'annulation
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </aside>
    </div>
</div>
@endsection
