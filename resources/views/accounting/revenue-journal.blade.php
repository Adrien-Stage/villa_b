@extends('layouts.hotel')

@section('title', 'Journal des encaissements')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';

    // Noms des modes de règlement. Ceux qui n'y figurent pas s'affichent tels
    // qu'ils sont enregistrés : inventer un libellé masquerait un mode entré
    // par erreur, qu'il vaut mieux voir.
    $modes = [
        'cash'          => 'Espèces',
        'check'         => 'Chèque',
        'bank_transfer' => 'Virement bancaire',
        'orange_money'  => 'Orange Money',
        'mtn_momo'      => 'MTN Mobile Money',
        'stripe'        => 'Carte bancaire',
        'credit'        => 'Crédit',
    ];
    $libelle = fn (string $m) => $modes[$m] ?? $m;

    // Colonnes : les modes réellement rencontrés sur la période, pas la liste
    // entière — un journal de quinze colonnes vides ne se lit pas.
    $colonnes = array_keys($journal['par_mode']);
    sort($colonnes);

    $parPointEtMode = [];
    foreach ($journal['lignes'] as $ligne) {
        $parPointEtMode[$ligne['point_de_vente']][$ligne['mode']] = $ligne['montant'];
    }
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Journal des encaissements</h1>
        <p class="text-sm text-primary/60 mt-1">
            Ce qui est entré sur la période, par point de vente et par mode de règlement.
        </p>
    </div>
    @include('accounting.partials.period')
</div>

@include('accounting.partials.nav')

@if($journal['total'] === 0)
    <div class="bg-white rounded-xl border border-secondary/20 p-10 text-center shadow-sm">
        <p class="text-sm text-primary/50">Aucun encaissement sur {{ $period['label'] }}.</p>
    </div>
@else
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total encaissé</p>
            <p class="text-2xl font-heading font-bold text-primary mt-1">{{ $fcfa($journal['total']) }}</p>
        </div>
        @foreach(array_slice($journal['par_point_de_vente'], 0, 3, true) as $point => $montant)
            <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">{{ $point }}</p>
                <p class="text-xl font-heading font-bold text-primary mt-1">{{ $fcfa($montant) }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-secondary/20 bg-accent/10">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Point de vente</th>
                        @foreach($colonnes as $mode)
                            <th class="px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50 whitespace-nowrap">{{ $libelle($mode) }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/70">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($journal['par_point_de_vente'] as $point => $totalPoint)
                        <tr class="{{ $point === 'Non rattaché' ? 'bg-amber-50/60' : '' }}">
                            <td class="px-4 py-2.5 font-medium text-primary">
                                {{ $point }}
                                @if($point === 'Non rattaché')
                                    <span class="ml-1 text-[10px] font-normal text-amber-700">recettes sans point de vente</span>
                                @endif
                            </td>
                            @foreach($colonnes as $mode)
                                <td class="px-3 py-2.5 text-right tabular-nums text-primary/70">
                                    {{ isset($parPointEtMode[$point][$mode]) ? $fcfa($parPointEtMode[$point][$mode]) : '—' }}
                                </td>
                            @endforeach
                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-primary">{{ $fcfa($totalPoint) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-secondary/30 bg-accent/10">
                        <td class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-primary/60">Total période</td>
                        @foreach($colonnes as $mode)
                            <td class="px-3 py-3 text-right font-semibold tabular-nums text-primary">{{ $fcfa($journal['par_mode'][$mode]) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-heading font-bold tabular-nums text-primary">{{ $fcfa($journal['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="mt-3 text-[11px] leading-relaxed text-primary/40">
        Une vente de réception rattachée à un règlement n'est comptée qu'une fois : c'est le même
        argent, enregistré deux fois. Les recettes sans point de vente apparaissent sous
        « Non rattaché » plutôt que d'être tues, pour que le total tombe juste.
    </p>
@endif
@endsection
