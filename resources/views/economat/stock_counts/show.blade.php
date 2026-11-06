@extends('layouts.hotel')

@section('title', 'Inventaire ' . $count->reference . ' — Économat')

@section('content')
<div class="max-w-6xl mx-auto" x-data="stockCountApp()">
    {{-- Fil d'ariane & actions rapides --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <a href="{{ route('economat.stock_counts.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux inventaires
        </a>

        <div class="flex flex-wrap items-center gap-2">
            @droit('economat.stock_counts.export')
                <a href="{{ route('economat.stock_counts.export', ['inventaire' => $count->id]) }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm"
                    title="Les articles de l'inventaire avec leur stock théorique, et la colonne « stock compté » à remplir">
                    <i data-lucide="sheet" class="w-4 h-4 text-emerald-700"></i>
                    Fichier de comptage (Excel)
                </a>
            @enddroit
            @if($count->isClosed())
                <a href="{{ route('economat.stock_counts.report', $count) }}" target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-lg border border-indigo-200 transition-colors shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Imprimer le Procès-Verbal (PV)
                </a>
            @endif

            {{-- Bouton d'annulation par le RAF sous 48h si clôturé --}}
            @if($count->isClosed() && $canManage && $count->canBeCancelledByRaf())
                <button type="button" @click="confirmCancelValidated = true" class="px-3.5 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-300 rounded-lg transition-colors">
                    <i data-lucide="undo-2" class="w-3.5 h-3.5 inline mr-1"></i>
                    Annuler la validation (Contre-passation sous 48h)
                </button>
            @endif

            {{-- Bouton d'abandon de brouillon / inventaire non validé --}}
            @if(!$count->isClosed() && !$count->isCancelled() && $canCancel)
                <button type="button" @click="confirmCancel = true" class="px-3 py-2 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                    Abandonner l'inventaire
                </button>
            @endif
        </div>
    </div>

    @include('economat.partials.flash')
    <x-csv-import-errors />

    {{-- Après le comptage : le fichier rempli saisit d'un coup les quantités comptées. --}}
    @if($count->isDraft())
        @droit('economat.stock_counts.import')
            <form method="POST" action="{{ route('economat.stock_counts.import', $count) }}" enctype="multipart/form-data"
                  class="bg-white border border-emerald-200 rounded-xl p-4 mb-5 shadow-sm"
                  onsubmit="const b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Import en cours…';">
                @csrf
                <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                    <div class="flex-1">
                        <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                            <i data-lucide="file-up" class="w-4 h-4 text-emerald-700"></i> Importer le fichier de comptage
                        </h2>
                        <p class="text-xs text-primary/60 mt-1">
                            Téléchargez le fichier de comptage, remplissez la colonne « stock compté » (et au besoin « motif » et « note »), puis importez-le.
                            Les lignes laissées vides ne changent pas. La saisie à la main ci-dessous reste possible.
                        </p>
                        <label for="fichier-comptage" class="sr-only">Fichier de comptage</label>
                        <input id="fichier-comptage" type="file" name="fichier" required accept=".xlsx,.xls,.csv"
                               class="mt-2 block w-full text-xs text-primary file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-emerald-800 hover:file:bg-emerald-100">
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        @if($canManage)
                            <label class="inline-flex items-center gap-2 text-xs text-primary">
                                <input type="checkbox" name="cloturer" value="1" class="rounded border-secondary/40 text-primary">
                                Clôturer aussitôt : ajuster le stock sur les quantités comptées
                            </label>
                        @endif
                        <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
                            <i data-lucide="upload" class="w-4 h-4"></i> Importer le comptage
                        </button>
                    </div>
                </div>
            </form>
        @enddroit
    @endif

    {{-- Carte d'en-tête --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-5 sm:p-6 mb-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-bold text-primary font-mono">{{ $count->reference }}</h1>
                    @php
                        $badgeStyles = [
                            'draft'     => 'bg-amber-100 text-amber-900 border-amber-300',
                            'submitted' => 'bg-blue-100 text-blue-900 border-blue-300',
                            'closed'    => 'bg-green-100 text-green-900 border-green-300',
                            'rejected'  => 'bg-red-100 text-red-900 border-red-300',
                            'cancelled' => 'bg-gray-100 text-gray-700 border-gray-300',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $badgeStyles[$count->status] ?? 'bg-gray-100' }}">
                        {{ $count->statusLabel() }}
                    </span>
                    @if($count->isClosed() && $count->canBeCancelledByRaf())
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200" title="Délai légal de 48h">
                            <i data-lucide="clock" class="w-3.5 h-3.5"></i> Annulable jusqu'au {{ $count->cancellationDeadline()?->format('d/m à H:i') }}
                        </span>
                    @elseif($count->isClosed())
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i> Verrouillé définitivement
                        </span>
                    @endif
                </div>
                <p class="text-sm text-primary/70 mt-1">
                    Service : <strong>{{ $count->departmentLabel() }}</strong>
                    · Périmètre : <strong>{{ $count->perimetre('Tout le magasin (Général)') }}</strong>
                    · Date de comptage : {{ $count->count_date->format('d/m/Y') }}
                </p>
                <div class="text-xs text-primary/60 mt-1 space-y-0.5">
                    <p>Ouvert par <strong>{{ $count->openedBy?->name ?? '—' }}</strong> le {{ $count->created_at->format('d/m/Y à H:i') }}</p>
                    @if($count->submittedBy)
                        <p class="text-blue-700">Soumis au RAF par <strong>{{ $count->submittedBy->name }}</strong> le {{ $count->submitted_at?->format('d/m/Y à H:i') }}</p>
                    @endif
                    @if($count->validatedBy ?? $count->closedBy)
                        <p class="text-emerald-700">Validé & régularisé par le RAF <strong>{{ ($count->validatedBy ?? $count->closedBy)->name }}</strong> le {{ ($count->validated_at ?? $count->closed_at)?->format('d/m/Y à H:i') }}</p>
                    @endif
                    @if($count->isRejected())
                        <div class="p-2.5 mt-2 bg-red-50 border border-red-200 rounded-lg text-red-800 text-xs">
                            <strong>Rejeté par le RAF ({{ $count->rejectedBy?->name ?? 'Direction' }}) :</strong> {{ $count->rejection_reason }}
                            <p class="mt-0.5 text-[11px] text-red-600">Veuillez corriger les lignes indiquées puis re-soumettre la feuille au RAF.</p>
                        </div>
                    @endif
                    @if($count->isCancelled())
                        <p class="text-red-600">Annulé par <strong>{{ $count->cancelledBy?->name ?? '—' }}</strong> le {{ $count->cancelled_at?->format('d/m/Y à H:i') }} @if($count->cancellation_reason) — Motif : <em>{{ $count->cancellation_reason }}</em>@endif</p>
                    @endif
                </div>
                @if($count->notes)
                    <p class="text-xs text-primary/60 mt-2 bg-gray-50 border border-secondary/15 rounded-lg px-3 py-2 italic">{{ $count->notes }}</p>
                @endif
            </div>

            {{-- Indicateur de progression --}}
            <div class="sm:text-right shrink-0">
                <p class="text-xs text-primary/50 uppercase font-semibold tracking-wider">Avancement saisie</p>
                <p class="text-2xl font-bold text-primary font-mono mt-0.5">{{ $count->progressPercentage() }} %</p>
                <p class="text-[11px] text-primary/40">{{ $count->lines()->whereNotNull('counted_quantity')->count() }} / {{ $count->lines()->count() }} articles saisis</p>
            </div>
        </div>

        {{-- Bandeau des valorisations --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-5 mt-5 border-t border-secondary/15">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-primary/45">Valeur théorique</p>
                <p class="text-base sm:text-lg font-bold font-mono text-primary mt-0.5">{{ number_format($count->total_theoretical_value / 100, 0, ',', ' ') }} <span class="text-xs font-sans text-primary/40">F</span></p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-primary/45">Valeur constatée</p>
                <p class="text-base sm:text-lg font-bold font-mono text-primary mt-0.5">
                    @if($count->isClosed())
                        {{ number_format($count->total_counted_value / 100, 0, ',', ' ') }} <span class="text-xs font-sans text-primary/40">F</span>
                    @else
                        <span class="text-primary/40 font-sans text-sm italic">En cours</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-red-600">Pertes constatées</p>
                <p class="text-base sm:text-lg font-bold font-mono text-red-700 mt-0.5">
                    −{{ number_format($count->loss_value / 100, 0, ',', ' ') }} <span class="text-xs font-sans text-red-600/60">F</span>
                </p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-primary/45">Écart net valorisé</p>
                @php $valNet = (int) $count->variance_value; @endphp
                <p class="text-base sm:text-lg font-bold font-mono mt-0.5 {{ $valNet < 0 ? 'text-red-700' : ($valNet > 0 ? 'text-green-700' : 'text-primary') }}">
                    {{ $valNet > 0 ? '+' : ($valNet < 0 ? '−' : '') }}{{ number_format(abs($valNet) / 100, 0, ',', ' ') }} <span class="text-xs font-sans text-primary/40">FCFA</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Formulaire de saisie (Draft) ou Liste consultable (Closed) --}}
    <form method="POST" id="count-form" action="{{ $count->isDraft() ? route('economat.stock_counts.update', $count) : '#' }}">
        @if($count->isDraft())
            @csrf
            @method('PUT')
        @endif

        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm mb-6">
            {{-- Barre d'outils du tableau --}}
            <div class="p-4 border-b border-secondary/15 bg-gray-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold text-primary">Articles inventoriés ({{ $lines->count() }})</h2>
                    <label class="inline-flex items-center gap-1.5 text-xs text-primary/70 cursor-pointer">
                        <input type="checkbox" x-model="onlyVariances" class="rounded border-secondary/30 text-primary focus:ring-0">
                        <span>Afficher uniquement les écarts</span>
                    </label>
                </div>

                <div class="w-full sm:w-64">
                    <input type="text" x-model="search" placeholder="Rechercher un article..."
                        class="w-full text-xs px-3 py-1.5 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                </div>
            </div>

            {{-- Table des lignes --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-secondary/10">
                    <thead class="bg-gray-100/60 text-primary/60 text-[11px] font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Article</th>
                            <th class="px-3 py-3 text-center">Unité</th>
                            <th class="px-4 py-3 text-right">Stock initial (théorique)</th>
                            <th class="px-4 py-3 text-right w-32">Stock compté</th>
                            <th class="px-4 py-3 text-right">Écart qté</th>
                            <th class="px-4 py-3 text-right">CUMP</th>
                            <th class="px-4 py-3 text-right">Écart FCFA</th>
                            <th class="px-4 py-3 text-left w-48">Motif de l'écart</th>
                            <th class="px-4 py-3 text-left">Observations</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($lines as $line)
                            @php
                                $cump = (int) $line->unit_cost;
                                $cumpFCFA = $cump / 100;
                                $theo = (float) $line->theoretical_quantity;
                                $hasCounted = $line->isCounted();
                                $varQty = (float) $line->variance_quantity;
                                $varVal = (int) $line->variance_value;
                            @endphp
                            <tr class="hover:bg-accent/5 transition-colors"
                                x-show="matchesRow('{{ addslashes(mb_strtolower($line->item?->name ?? '')) }}', {{ $hasCounted && abs($varQty) >= 0.0005 ? 'true' : 'false' }})"
                                x-data="{
                                    theo: {{ $theo }},
                                    cump: {{ $cump }},
                                    counted: '{{ $line->counted_quantity !== null ? (float) $line->counted_quantity : '' }}',
                                    {{-- Article conditionné : on peut compter cartons, paquets et vrac ; le total s'en déduit. --}}
                                    niveaux: {{ Js::from(($line->item?->packagings ?? collect())->sortByDesc('factor')->map(fn ($n) => ['nom' => $n->name, 'facteur' => (float) $n->factor])->values()) }},
                                    fermes: {{ Js::from((object) ($line->packaging_counts ?? [])) }},
                                    vrac: '{{ $line->packaging_counts && $line->counted_quantity !== null
                                        ? round((float) $line->counted_quantity - collect($line->packaging_counts)->map(fn ($n, $nom) => $n * (float) ($line->item?->packagings->firstWhere('name', $nom)?->factor ?? 0))->sum(), 3)
                                        : '' }}',
                                    recompter() {
                                        let total = parseFloat(this.vrac) || 0;
                                        this.niveaux.forEach(n => { total += (parseInt(this.fermes[n.nom]) || 0) * n.facteur; });
                                        this.counted = Math.round(total * 1000) / 1000;
                                    },
                                    get diffQty() {
                                        if (this.counted === '' || isNaN(parseFloat(this.counted))) return 0;
                                        return Math.round((parseFloat(this.counted) - this.theo) * 1000) / 1000;
                                    },
                                    get diffVal() {
                                        return Math.round(this.diffQty * this.cump);
                                    }
                                }">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-primary text-xs sm:text-sm">{{ $line->item?->name ?? '—' }}</p>
                                    <p class="text-[11px] text-primary/45 mt-0.5">
                                        {{ $line->item?->category?->name ?? 'Épicerie' }}
                                    </p>
                                </td>

                                <td class="px-3 py-3 text-center font-mono font-bold text-xs text-primary/70">
                                    {{ $line->item?->shortUnit() ?? $line->item?->unit }}
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-xs text-primary/70">
                                    {{ rtrim(rtrim(number_format($theo, 3, ',', ' '), '0'), ',') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    @if($count->isDraft())
                                        <input type="number" step="0.001" min="0" max="9999999"
                                            name="lines[{{ $line->id }}][counted_quantity]"
                                            x-model="counted"
                                            placeholder="—"
                                            class="w-28 px-2 py-1 text-xs border rounded-lg bg-white text-right font-mono outline-none focus:border-primary border-secondary/30">
                                        @if($line->item && $line->item->packagings->isNotEmpty())
                                            <details class="mt-1 text-left" @if($line->packaging_counts) open @endif>
                                                <summary class="cursor-pointer text-[10px] text-primary/55">Compter par conditionnement</summary>
                                                <div class="mt-1 space-y-1">
                                                    <template x-for="n in niveaux" :key="n.nom">
                                                        <label class="flex items-center justify-end gap-1 text-[10px] text-primary/60">
                                                            <span x-text="(/[sxz]$/i.test(n.nom) ? n.nom : n.nom + 's') + ' fermés'"></span>
                                                            <input type="number" min="0" step="1" :name="'lines[{{ $line->id }}][fermes][' + n.nom + ']'" x-model="fermes[n.nom]" @input="recompter()"
                                                                class="w-16 px-1.5 py-0.5 text-[11px] border border-secondary/30 rounded text-right font-mono">
                                                        </label>
                                                    </template>
                                                    <label class="flex items-center justify-end gap-1 text-[10px] text-primary/60">
                                                        <span>{{ $line->item->unit }} en vrac</span>
                                                        <input type="number" min="0" step="0.001" name="lines[{{ $line->id }}][vrac]" x-model="vrac" @input="recompter()"
                                                            class="w-16 px-1.5 py-0.5 text-[11px] border border-secondary/30 rounded text-right font-mono">
                                                    </label>
                                                </div>
                                            </details>
                                        @endif
                                    @else
                                        <span class="font-mono font-medium text-xs {{ $line->isCounted() ? 'text-primary' : 'text-primary/30 italic' }}">
                                            {{ $line->isCounted() ? rtrim(rtrim(number_format((float) $line->counted_quantity, 3, ',', ' '), '0'), ',') : 'Non compté' }}
                                        </span>
                                        @if($line->packaging_counts)
                                            <span class="block text-[10px] text-primary/50">{{ collect($line->packaging_counts)->filter()->map(fn ($n, $nom) => \App\Support\Conditionnement::libelle($n, $nom))->implode(' · ') }}</span>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">
                                    @if($count->isDraft())
                                        <span :class="diffQty < 0 ? 'text-red-600 font-bold' : (diffQty > 0 ? 'text-green-600 font-bold' : 'text-primary/40')"
                                              x-text="counted !== '' ? (diffQty > 0 ? '+' : '') + diffQty : '—'"></span>
                                    @else
                                        <span class="{{ $varQty < 0 ? 'text-red-600 font-bold' : ($varQty > 0 ? 'text-green-600 font-bold' : 'text-primary/40') }}">
                                            {{ $hasCounted ? ($varQty > 0 ? '+' : '') . rtrim(rtrim(number_format($varQty, 3, ',', ' '), '0'), ',') : '—' }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-xs text-primary/60 whitespace-nowrap">
                                    {{ number_format($cumpFCFA, 0, ',', ' ') }} F
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">
                                    @if($count->isDraft())
                                        <span :class="diffVal < 0 ? 'text-red-600 font-bold' : (diffVal > 0 ? 'text-green-600 font-bold' : 'text-primary/40')"
                                              x-text="counted !== '' ? (diffVal > 0 ? '+' : '') + Math.round(diffVal / 100).toLocaleString('fr-FR') + ' F' : '—'"></span>
                                    @else
                                        <span class="{{ $varVal < 0 ? 'text-red-600 font-bold' : ($varVal > 0 ? 'text-green-600 font-bold' : 'text-primary/40') }}">
                                            {{ $hasCounted ? ($varVal > 0 ? '+' : '') . number_format(round($varVal / 100), 0, ',', ' ') . ' F' : '—' }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($count->isDraft())
                                        <select name="lines[{{ $line->id }}][reason]"
                                            class="w-full text-xs px-2 py-1 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                            <option value="">Sélectionner motif si écart...</option>
                                            @foreach($reasons as $code => $label)
                                                <option value="{{ $code }}" {{ $line->reason === $code ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="inline-flex items-center text-xs text-primary/80">
                                            {{ $line->reasonLabel() }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($count->isDraft())
                                        <input type="text" name="lines[{{ $line->id }}][notes]" value="{{ $line->notes }}" placeholder="Détail..."
                                            class="w-full px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                    @else
                                        <span class="text-xs text-primary/60 italic">{{ $line->notes ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Actions en bas de page --}}
            <div class="p-4 bg-gray-50 border-t border-secondary/15 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-primary/60">
                    @if($count->isSubmitted())
                        <span class="inline-flex items-center gap-1.5 text-blue-800 font-medium">
                            <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
                            Feuille soumise pour validation au RAF. Les comptages sont verrouillés.
                        </span>
                    @elseif($count->isClosed())
                        <span class="inline-flex items-center gap-1.5 text-emerald-800 font-medium">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                            Inventaire validé et stocks régularisés.
                        </span>
                    @elseif(!$count->canBeModified() && !$count->isCancelled())
                        <span class="inline-flex items-center gap-1.5 text-amber-800 font-medium">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                            Délai de saisie de 48h expiré. Veuillez soumettre ou abandonner cette feuille.
                        </span>
                    @else
                        <span class="text-primary/50">
                            <i data-lucide="info" class="w-3.5 h-3.5 inline mr-1"></i>
                            Les lignes laissées vides ne modifieront pas le stock de l'économat à la clôture.
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    {{-- Contrôleur : Sauvegarder & Soumettre --}}
                    @if($canCount)
                        <button type="submit" class="px-4 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
                            <i data-lucide="save" class="w-3.5 h-3.5 inline mr-1"></i>
                            Sauvegarder le comptage
                        </button>
                    @endif

                    @if($canSubmit && ($count->isDraft() || $count->isRejected()))
                        <button type="button" @click="confirmSubmit = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i>
                            Soumettre au RAF
                        </button>
                    @endif

                    {{-- RAF : Valider & Rejeter --}}
                    @if($canManage && ($count->isSubmitted() || $count->isDraft()))
                        @if($canReject && $count->isSubmitted())
                            <button type="button" @click="confirmReject = true"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-red-300 text-red-700 hover:bg-red-50 text-xs font-semibold rounded-lg transition-colors">
                                <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                                Rejeter
                            </button>
                        @endif

                        <button type="button" @click="confirmClose = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                            Valider et régulariser les stocks
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Modal de confirmation de soumission au RAF --}}
    <div x-show="confirmSubmit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmSubmit = false">
            <div class="flex items-center gap-3 text-blue-700 mb-4">
                <div class="p-2.5 rounded-xl bg-blue-100"><i data-lucide="send" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-primary">Soumettre au RAF pour validation ?</h3>
                    <p class="text-xs text-primary/60 font-mono">{{ $count->reference }}</p>
                </div>
            </div>

            <p class="text-xs text-primary/70 leading-relaxed mb-4">
                La feuille sera transmise au Responsable Administratif et Financier (RAF). Les comptages physiques saisis seront <strong>verrouillés</strong> jusqu'à la décision du RAF.
            </p>

            <form method="POST" action="{{ route('economat.stock_counts.submit', $count) }}">
                @csrf
                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmSubmit = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Confirmer la soumission
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal de rejet par le RAF --}}
    <div x-show="confirmReject" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmReject = false">
            <div class="flex items-center gap-3 text-red-700 mb-4">
                <div class="p-2.5 rounded-xl bg-red-100"><i data-lucide="x-circle" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-primary">Rejeter l'inventaire ?</h3>
                    <p class="text-xs text-primary/60 font-mono">{{ $count->reference }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('economat.stock_counts.reject', $count) }}">
                @csrf
                <p class="text-xs text-primary/70 leading-relaxed mb-3">
                    Indiquez le motif précis du rejet pour le contrôleur de gestion :
                </p>
                <textarea name="reason" required rows="3" placeholder="Ex: Écarts anormaux sur les bières et spiritueux non justifiés, recompter la cave..."
                    class="w-full text-xs p-2.5 border border-secondary/30 rounded-lg outline-none focus:border-red-500 mb-4"></textarea>

                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmReject = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Confirmer le rejet
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal de confirmation de clôture / validation par le RAF --}}
    <div x-show="confirmClose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmClose = false">
            <div class="flex items-center gap-3 text-green-700 mb-4">
                <div class="p-2.5 rounded-xl bg-green-100"><i data-lucide="shield-check" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-primary">Valider & régulariser l'inventaire ?</h3>
                    <p class="text-xs text-primary/60 font-mono">{{ $count->reference }}</p>
                </div>
            </div>

            <p class="text-xs text-primary/70 leading-relaxed mb-4">
                En tant que RAF, votre validation va <strong>générer immédiatement les mouvements d'ajustement de stock</strong> dans le journal de l'économat et sceller le Procès-Verbal officiel.
            </p>

            <form method="POST" action="{{ route('economat.stock_counts.close', $count) }}">
                @csrf
                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmClose = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Valider et régulariser
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal d'annulation par le RAF sous 48h avec contre-passation --}}
    <div x-show="confirmCancelValidated" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmCancelValidated = false">
            <div class="flex items-center gap-3 text-red-700 mb-4">
                <div class="p-2.5 rounded-xl bg-red-100"><i data-lucide="undo-2" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-primary">Annuler un inventaire validé (48h) ?</h3>
                    <p class="text-xs text-primary/60 font-mono">{{ $count->reference }}</p>
                </div>
            </div>

            <p class="text-xs text-primary/70 leading-relaxed mb-3">
                Cette annulation exceptionnelle va <strong>contre-passer tous les mouvements d'ajustement de stock</strong> générés lors de la clôture et rétablir le stock antérieur.
            </p>

            <form method="POST" action="{{ route('economat.stock_counts.cancel', $count) }}">
                @csrf
                <label class="block text-xs font-semibold text-primary/70 mb-1">Motif de l'annulation (obligatoire) :</label>
                <textarea name="reason" required rows="2" placeholder="Ex: Erreur matérielle sur le comptage des congélateurs..."
                    class="w-full text-xs p-2.5 border border-secondary/30 rounded-lg outline-none focus:border-red-500 mb-4"></textarea>

                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmCancelValidated = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Retour
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Confirmer la contre-passation
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal d'abandon de brouillon --}}
    <div x-show="confirmCancel" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmCancel = false">
            <h3 class="font-bold text-lg text-red-700 mb-2">Abandonner la feuille d'inventaire ?</h3>
            <p class="text-xs text-primary/70 leading-relaxed mb-4">
                L'inventaire sera marqué comme annulé. Aucun stock ne sera modifié et la feuille sera archivée.
            </p>
            <form method="POST" action="{{ route('economat.stock_counts.cancel', $count) }}">
                @csrf
                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmCancel = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Non, continuer
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Oui, abandonner la feuille
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function stockCountApp() {
    return {
        search: '',
        onlyVariances: false,
        confirmSubmit: false,
        confirmReject: false,
        confirmClose: false,
        confirmCancel: false,
        confirmCancelValidated: false,
        matchesRow(name, hasVariance) {
            if (this.onlyVariances && !hasVariance) return false;
            if (this.search === '') return true;
            return name.includes(this.search.toLowerCase());
        }
    };
}
</script>
@endsection
