@extends('layouts.hotel')

@section('title', 'Fiches techniques des chambres')

@section('content')
<div class="max-w-6xl mx-auto">
    @php
        // Consolidation : ce qu'on lit d'abord, avant d'ouvrir une fiche.
        // La marge globale se pondère par le prix, non par le nombre de types :
        // faire la moyenne des pourcentages donnerait autant de poids à une
        // suite qu'à une chambre standard.
        $renseignees = $rows->filter(fn ($r) => $r['summary']['is_configured']);
        $prixCumule  = $renseignees->sum(fn ($r) => $r['summary']['reference_price']);
        $coutCumule  = $renseignees->sum(fn ($r) => $r['summary']['variable_cost']);
        $margeCumulee = $prixCumule - $coutCumule;
        $margeGlobale = $prixCumule > 0 ? round($margeCumulee * 100 / $prixCumule) : null;
        $aRemplir     = $rows->count() - $renseignees->count();

        $fcfa = fn ($centimes) => number_format(((int) $centimes) / 100, 0, ',', ' ');
        $teinte = fn ($pct) => $pct === null ? 'text-primary/40'
            : ($pct >= 60 ? 'text-green-700' : ($pct >= 40 ? 'text-amber-600' : 'text-red-600'));
    @endphp

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Marges par type de chambre</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                Vue consolidée. Ouvrez une fiche pour le détail de ses postes de coût.
            </p>
        </div>

        @droit('rooms.cost_sheets.voir')
            <x-barre-export route="rooms.cost_sheets.document" />
        @enddroit
    </div>

    @if($renseignees->isNotEmpty())
        <div class="mb-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3">
                <p class="text-[10px] uppercase tracking-wider text-primary/40">Marge globale</p>
                <p class="text-2xl font-heading font-bold {{ $teinte($margeGlobale) }} mt-0.5">{{ $margeGlobale }}%</p>
                <p class="text-[10px] text-primary/40">pondérée par le prix</p>
            </div>
            <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3">
                <p class="text-[10px] uppercase tracking-wider text-primary/40">Prix cumulé / nuit</p>
                <p class="text-lg font-semibold text-primary mt-1">{{ $fcfa($prixCumule) }}</p>
            </div>
            <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3">
                <p class="text-[10px] uppercase tracking-wider text-primary/40">Coût cumulé</p>
                <p class="text-lg font-semibold text-red-600 mt-1">{{ $fcfa($coutCumule) }}</p>
            </div>
            <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3 {{ $aRemplir > 0 ? 'border-amber-300 bg-amber-50/40' : '' }}">
                <p class="text-[10px] uppercase tracking-wider text-primary/40">Fiches</p>
                <p class="text-lg font-semibold text-primary mt-1">{{ $renseignees->count() }} / {{ $rows->count() }}</p>
                @if($aRemplir > 0)
                    {{-- Une marge globale calculée sur la moitié des types
                         n'est pas la marge de l'hôtel : on le dit. --}}
                    <p class="text-[10px] font-medium text-amber-700">{{ $aRemplir }} à remplir</p>
                @endif
            </div>
        </div>
    @endif

    @include('economat.partials.flash')

    @if($rows->isEmpty())
        <div class="border border-dashed border-secondary/30 rounded-xl px-6 py-12 text-center">
            <i data-lucide="calculator" class="w-8 h-8 mx-auto text-primary/20 mb-3"></i>
            <p class="text-sm text-primary/50">Aucun type de chambre actif.</p>
        </div>
    @else
        {{-- Export tableur. Pensé pour le déploiement : le personnel remplit les
             fiches dans Excel, qu'il maîtrise déjà, avant de prendre en main la
             plateforme. Le formulaire enveloppe la grille pour que les cases des
             cartes lui appartiennent. --}}
        <form method="GET" action="{{ route('rooms.cost_sheets.export') }}"
              x-data="{ selection: [], get toutes() { return this.selection.length === 0; } }">

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 bg-white border border-secondary/20 rounded-xl px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-primary">Exporter vers Excel</p>
                    <p class="text-[11px] text-primary/60">Classeur complet : synthèse, coûts unitaires et une fiche par type de chambre.</p>
                    <p class="text-[11px] text-primary/50 mt-0.5">
                        <span x-show="toutes">Aucune fiche cochée : l'export prendra <strong>toutes les fiches</strong>.</span>
                        <span x-show="!toutes" x-cloak>
                            <strong x-text="selection.length"></strong> fiche(s) sélectionnée(s).
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="document.getElementById('modal-import-cost-sheets').classList.remove('hidden')"
                            class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors">
                        <i data-lucide="file-up" class="w-4 h-4 text-secondary"></i>
                        <span>Importer (CSV)</span>
                    </button>
                    <button type="button" @click="selection = []" x-show="!toutes" x-cloak
                            class="px-3 py-2 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
                        Tout décocher
                    </button>
                    {{-- Le CSV à plat n'est pas un doublon du classeur : c'est le
                         seul format que l'import sait relire. --}}
                    <button type="submit" name="format" value="csv"
                            class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors">
                        <i data-lucide="file-text" class="w-4 h-4 text-secondary"></i>
                        <span>CSV (ré-importable)</span>
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        <span x-show="toutes">Exporter toutes les fiches</span>
                        <span x-show="!toutes" x-cloak>Exporter la sélection</span>
                    </button>
                </div>
            </div>

        {{-- Tableau plutôt que cartes : on vient ici pour comparer les types
             entre eux, et des cartes côte à côte se comparent mal. Les lignes
             sont triées par marge croissante — ce qu'on cherche d'abord, c'est
             la chambre qui rapporte le moins. --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-accent/20">
                        <tr>
                            <th class="w-10 px-4 py-3"></th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Type de chambre</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Prix / nuit</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Coût variable</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Marge</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">%</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Postes</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($rows->sortBy(fn ($r) => $r['summary']['contribution_pct'] ?? -1) as $row)
                            @php
                                $type = $row['type'];
                                $s    = $row['summary'];
                                $pct  = $s['contribution_pct'];
                            @endphp
                            <tr class="hover:bg-accent/10 transition-colors">
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" name="types[]" value="{{ $type->id }}" x-model.number="selection"
                                           class="w-4 h-4 rounded border-secondary/40 text-primary cursor-pointer"
                                           aria-label="Sélectionner la fiche {{ $type->name }}">
                                </td>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('rooms.cost_sheets.show', $type) }}"
                                       class="font-medium text-primary hover:text-secondary transition-colors">{{ $type->name }}</a>
                                    @unless($s['is_configured'])
                                        <span class="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i> à remplir
                                        </span>
                                    @endunless
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-primary/80">
                                    {{ $fcfa($s['reference_price']) }}
                                    @if($s['reference_is_realized'])
                                        {{-- Prix réellement pratiqué, non tarif affiché : la marge
                                             calculée sur un tarif jamais appliqué serait fictive. --}}
                                        <span class="text-[10px] text-primary/40" title="Prix moyen réellement pratiqué">réel</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $s['is_configured'] ? 'text-red-600' : 'text-primary/30' }}">
                                    {{ $s['is_configured'] ? $fcfa($s['variable_cost']) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-semibold {{ $s['is_configured'] ? $teinte($pct) : 'text-primary/30' }}">
                                    {{ $s['is_configured'] ? $fcfa($s['contribution_margin']) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($s['is_configured'])
                                        <span class="font-bold {{ $teinte($pct) }}">{{ $pct }}%</span>
                                    @else
                                        {{-- Sans coût saisi, aucun pourcentage : 100 % serait mensonger. --}}
                                        <span class="text-primary/30">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-primary/50">{{ $s['line_count'] ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('rooms.cost_sheets.show', $type) }}"
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-secondary hover:text-primary transition-colors">
                                        Fiche <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    @if($renseignees->isNotEmpty())
                        <tfoot class="bg-accent/20">
                            <tr class="font-semibold text-primary">
                                <td></td>
                                <td class="px-4 py-3 text-[11px] uppercase tracking-wider">
                                    Ensemble — {{ $renseignees->count() }} fiche(s) renseignée(s)
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $fcfa($prixCumule) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-red-700">{{ $fcfa($coutCumule) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $teinte($margeGlobale) }}">{{ $fcfa($margeCumulee) }}</td>
                                <td class="px-4 py-3 text-right {{ $teinte($margeGlobale) }}">{{ $margeGlobale }}%</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
        </form>

        <p class="text-[11px] text-primary/40 mt-4">
            La marge affichée est la <strong>marge de contribution</strong> (prix − coût variable par nuitée). Ouvrez une fiche pour le détail et les charges fixes.
        </p>

        <x-csv-import-modal
            id="modal-import-cost-sheets"
            title="Importer des fiches techniques (CSV)"
            :action="route('rooms.cost_sheets.import')"
            :template="route('rooms.cost_sheets.export', ['template' => 1])"
            structure="type_chambre;code_type;occupants_reference;sejour_moyen_nuits;charge_fixe_par_nuitee_fcfa;categorie;poste;base_calcul;quantite;cout_unitaire_fcfa;actif;notes"
            submit-label="Importer les fiches">
            <li><strong>type_chambre</strong> ou <strong>code_type</strong> doit correspondre à un type de chambre existant.</li>
            <li>Si le poste existe déjà (même libellé sous la fiche), il sera mis à jour. Sinon, un nouveau poste sera créé.</li>
            <li><strong>categorie</strong> (ex. <em>Électricité, Consommables, Blanchisserie…</em>) et <strong>base_calcul</strong> (ex. <em>Par nuitée, Par séjour, Par personne et nuitée</em>).</li>
        </x-csv-import-modal>
    @endif
</div>
@endsection
