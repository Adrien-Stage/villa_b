@extends('layouts.hotel')

@section('title', 'Fiches techniques des chambres')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-heading font-semibold text-primary">Fiches techniques des chambres</h1>
        <p class="text-sm text-primary/60 mt-0.5">Marge sur une chambre louée : coût variable par nuitée comparé au prix pratiqué.</p>
    </div>

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
                    <p class="text-[11px] text-primary/50 mt-0.5">
                        <span x-show="toutes">Aucune fiche cochée : l'export prendra <strong>toutes les fiches</strong>.</span>
                        <span x-show="!toutes" x-cloak>
                            <strong x-text="selection.length"></strong> fiche(s) sélectionnée(s).
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="selection = []" x-show="!toutes" x-cloak
                            class="px-3 py-2 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
                        Tout décocher
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        <span x-show="toutes">Exporter toutes les fiches</span>
                        <span x-show="!toutes" x-cloak>Exporter la sélection</span>
                    </button>
                </div>
            </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($rows as $row)
                @php
                    $type = $row['type'];
                    $s = $row['summary'];
                    $pct = $s['contribution_pct'];
                    // Couleur de marge : sain > 60 %, à surveiller 40-60 %, faible < 40 %.
                    $tone = $pct === null ? 'slate' : ($pct >= 60 ? 'green' : ($pct >= 40 ? 'amber' : 'red'));
                    $toneClasses = [
                        'green' => 'text-green-700', 'amber' => 'text-amber-600',
                        'red' => 'text-red-600', 'slate' => 'text-primary/40',
                    ][$tone];
                @endphp
                {{-- La carte n'est plus un lien d'un bloc : une case à cocher à
                     l'intérieur d'un <a> déclencherait la navigation au clic.
                     Seul le titre porte le lien, la case reste indépendante. --}}
                <div class="bg-white border rounded-xl p-5 transition-colors"
                     :class="selection.includes({{ $type->id }}) ? 'border-primary/40 ring-1 ring-primary/20' : 'border-secondary/20 hover:border-secondary/40'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <input type="checkbox" name="types[]" value="{{ $type->id }}" x-model.number="selection"
                                   class="mt-0.5 w-4 h-4 rounded border-secondary/40 text-primary shrink-0 cursor-pointer"
                                   aria-label="Sélectionner la fiche {{ $type->name }}">
                            <div class="min-w-0">
                                <a href="{{ route('rooms.cost_sheets.show', $type) }}"
                                   class="text-sm font-semibold text-primary hover:text-secondary transition-colors">
                                    {{ $type->name }}
                                </a>
                                <p class="text-[11px] text-primary/40">
                                    {{ $s['is_configured'] ? $s['line_count'] . ' poste(s) de coût' : 'fiche à remplir' }}
                                </p>
                            </div>
                        </div>
                        @if($s['is_configured'])
                            <span class="text-lg font-bold {{ $toneClasses }}">{{ $pct }}%</span>
                        @else
                            {{-- Sans coût saisi, aucun pourcentage : afficher 100 % serait mensonger. --}}
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> À configurer
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-4 text-center">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-primary/40">Prix / nuit</p>
                            <p class="text-sm font-semibold text-primary mt-0.5">{{ number_format($s['reference_price'] / 100, 0, ',', ' ') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-primary/40">Coût</p>
                            <p class="text-sm font-semibold {{ $s['is_configured'] ? 'text-red-600' : 'text-primary/30' }} mt-0.5">
                                {{ $s['is_configured'] ? number_format($s['variable_cost'] / 100, 0, ',', ' ') : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-primary/40">Reste</p>
                            <p class="text-sm font-semibold {{ $s['is_configured'] ? $toneClasses : 'text-primary/30' }} mt-0.5">
                                {{ $s['is_configured'] ? number_format($s['contribution_margin'] / 100, 0, ',', ' ') : '—' }}
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('rooms.cost_sheets.show', $type) }}"
                       class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-secondary hover:text-primary transition-colors">
                        Ouvrir la fiche
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            @endforeach
        </div>
        </form>

        <p class="text-[11px] text-primary/40 mt-4">
            La marge affichée est la <strong>marge de contribution</strong> (prix − coût variable par nuitée). Ouvrez une fiche pour le détail et les charges fixes.
        </p>
    @endif
</div>
@endsection
