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

            @if($count->isDraft() && $canManage)
                <button type="button" @click="confirmCancel = true" class="px-3 py-2 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                    Annuler l'inventaire
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
                            'closed'    => 'bg-green-100 text-green-900 border-green-300',
                            'cancelled' => 'bg-gray-100 text-gray-700 border-gray-300',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $badgeStyles[$count->status] ?? 'bg-gray-100' }}">
                        {{ $count->statusLabel() }}
                    </span>
                </div>
                <p class="text-sm text-primary/70 mt-1">
                    Périmètre : <strong>{{ $count->category?->name ?? 'Tout le magasin central (Général)' }}</strong>
                    · Date de comptage : {{ $count->count_date->format('d/m/Y') }}
                </p>
                <p class="text-xs text-primary/45 mt-0.5">
                    Ouvert par {{ $count->openedBy?->name ?? '—' }} le {{ $count->created_at->format('d/m/Y à H:i') }}
                    @if($count->closedBy)
                        · Clôturé et régularisé par {{ $count->closedBy->name }} le {{ $count->closed_at?->format('d/m/Y à H:i') }}
                    @endif
                </p>
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
                                        {{ $line->item?->category?->name ?? 'Épicerie' }} · Unité : <span class="font-mono">{{ $line->item?->unit }}</span>
                                    </p>
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
                                    @else
                                        <span class="font-mono font-medium text-xs {{ $line->isCounted() ? 'text-primary' : 'text-primary/30 italic' }}">
                                            {{ $line->isCounted() ? rtrim(rtrim(number_format((float) $line->counted_quantity, 3, ',', ' '), '0'), ',') : 'Non compté' }}
                                        </span>
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

            {{-- Actions de validation en bas de page pour le mode draft --}}
            @if($count->isDraft() && $canManage)
                <div class="p-4 bg-gray-50 border-t border-secondary/15 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-xs text-primary/50">
                        <i data-lucide="info" class="w-3.5 h-3.5 inline text-primary/40 mr-1"></i>
                        Les lignes laissées vides ne modifieront pas le stock de l'économat à la clôture.
                    </p>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
                            <i data-lucide="save" class="w-3.5 h-3.5 inline mr-1"></i>
                            Sauvegarder le comptage
                        </button>

                        <button type="button" @click="confirmClose = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                            Clôturer & régulariser les stocks
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </form>

    {{-- Modal de confirmation de clôture --}}
    <div x-show="confirmClose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmClose = false">
            <div class="flex items-center gap-3 text-green-700 mb-4">
                <div class="p-2.5 rounded-xl bg-green-100"><i data-lucide="shield-alert" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-primary">Clôturer l'inventaire ?</h3>
                    <p class="text-xs text-primary/60 font-mono">{{ $count->reference }}</p>
                </div>
            </div>

            <p class="text-xs text-primary/70 leading-relaxed mb-4">
                Cette action est <strong>définitive et irréversible</strong>. Les écarts constatés seront immédiatement traduits en mouvements d'ajustement de stock dans le journal de l'économat, et le Procès-Verbal officiel (PV) sera scellé.
            </p>

            <form method="POST" action="{{ route('economat.stock_counts.close', $count) }}">
                @csrf
                <div class="flex justify-end gap-2 pt-3 border-t border-secondary/15">
                    <button type="button" @click="confirmClose = false" class="px-4 py-2 text-xs text-primary/60 hover:text-primary">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                        Confirmer la clôture et l'ajustement
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal d'annulation --}}
    <div x-show="confirmCancel" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-secondary/20" @click.outside="confirmCancel = false">
            <h3 class="font-bold text-lg text-red-700 mb-2">Annuler la feuille d'inventaire ?</h3>
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
                        Oui, annuler la feuille
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
        confirmClose: false,
        confirmCancel: false,
        matchesRow(name, hasVariance) {
            if (this.onlyVariances && !hasVariance) return false;
            if (this.search === '') return true;
            return name.includes(this.search.toLowerCase());
        }
    };
}
</script>
@endsection
