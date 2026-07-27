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
                <a href="{{ route('rooms.cost_sheets.show', $type) }}" class="block bg-white border border-secondary/20 rounded-xl p-5 hover:border-secondary/40 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-primary">{{ $type->name }}</h2>
                            <p class="text-[11px] text-primary/40">
                                {{ $s['is_configured'] ? $s['line_count'] . ' poste(s) de coût' : 'fiche à remplir' }}
                            </p>
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
                </a>
            @endforeach
        </div>

        <p class="text-[11px] text-primary/40 mt-4">
            La marge affichée est la <strong>marge de contribution</strong> (prix − coût variable par nuitée). Ouvrez une fiche pour le détail et les charges fixes.
        </p>
    @endif
</div>
@endsection
