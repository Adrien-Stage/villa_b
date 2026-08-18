@extends('layouts.hotel')

@section('title', 'Balance âgée')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
    // Plus la tranche est ancienne, plus elle doit sauter aux yeux.
    $teintes = [
        'current' => 'text-primary/70',
        'd30'     => 'text-amber-600',
        'd60'     => 'text-orange-600',
        'd90'     => 'text-red-600 font-semibold',
    ];
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Balance âgée</h1>
    <p class="text-sm text-primary/60 mt-1">Arrêtée au {{ $arrete->format('d/m/Y') }}</p>
</div>

@include('accounting.ledger.partials.nav')

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Depuis combien de temps ?</strong> La liste des créances dit qui doit ; la balance âgée dit
    depuis quand. Une facture de 90 jours et une facture d'hier ne se relancent pas de la même façon, et
    seule la seconde a des chances d'être encore recouvrable sans effort.
    <span class="block mt-1">
        Seules les lignes <strong>non lettrées</strong> sont comptées : une créance réglée n'a pas d'âge.
    </span>
</div>

@if($collectifs->isEmpty())
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center">
        <i data-lucide="hourglass" class="w-8 h-8 mx-auto mb-3 text-primary/25"></i>
        <p class="text-sm text-primary/70">Aucun compte collectif dans le plan de comptes.</p>
    </div>
@else
    <form method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
        <div class="flex-1">
            <label class="block text-xs font-medium text-primary/70 mb-1.5">Compte collectif</label>
            <select name="compte" class="w-full sm:max-w-md rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                @foreach($collectifs as $c)
                    <option value="{{ $c->code }}" @selected($compte && $compte->code === $c->code)>
                        {{ $c->code }} — {{ $c->label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/70 mb-1.5">Arrêtée au</label>
            <input type="date" name="au" value="{{ $arrete->toDateString() }}"
                   class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
        </div>
        <div class="flex items-end">
            <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                Afficher
            </button>
        </div>
    </form>

    @if($balance['rows']->isNotEmpty())
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            @foreach($buckets as $cle => $libelle)
                <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">{{ $libelle }}</p>
                    <p class="text-base font-heading font-bold mt-0.5 {{ $teintes[$cle] }}">
                        {{ $fcfa($balance['totals'][$cle] ?? 0) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15">
            <h2 class="text-sm font-semibold text-primary font-mono">{{ $compte?->code }}</h2>
            <p class="text-xs text-primary/55">{{ $compte?->label }} — postes ouverts par ancienneté</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Tiers</th>
                        @foreach($buckets as $libelle)
                            <th class="px-3 py-2.5 text-right font-semibold">{{ $libelle }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-right font-semibold">Total dû</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @forelse($balance['rows'] as $row)
                        <tr class="hover:bg-accent/10">
                            <td class="px-4 py-2 text-primary font-medium">{{ $row['label'] }}</td>
                            @foreach($buckets as $cle => $libelle)
                                <td class="px-3 py-2 text-right whitespace-nowrap {{ $row['buckets'][$cle] ? $teintes[$cle] : 'text-primary/25' }}">
                                    {{ $row['buckets'][$cle] ? $fcfa($row['buckets'][$cle]) : '—' }}
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-right font-semibold text-primary whitespace-nowrap">{{ $fcfa($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($buckets) + 2 }}" class="px-4 py-8 text-center text-primary/50">
                                Aucun poste ouvert : tout est réglé et lettré.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($balance['rows']->isNotEmpty())
                    <tfoot class="bg-accent/20 font-semibold text-primary">
                        <tr>
                            <td class="px-4 py-2.5 uppercase tracking-widest text-[10px]">Totaux</td>
                            @foreach($buckets as $cle => $libelle)
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($balance['totals'][$cle] ?? 0) }}</td>
                            @endforeach
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">{{ $fcfa($balance['totals']['total'] ?? 0) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endif
@endsection
