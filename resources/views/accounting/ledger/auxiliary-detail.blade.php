@extends('layouts.hotel')

@section('title', 'Compte de tiers')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">{{ $nom }}</h1>
    <p class="text-sm text-primary/60 mt-1 font-mono">{{ $compte->code }} — {{ $compte->label }}</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<a href="{{ route('accounting.ledger.auxiliary', ['compte' => $compte->code]) }}"
   class="inline-flex items-center gap-1.5 mb-4 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
    Retour aux tiers
</a>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total débit</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($detail['debit']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total crédit</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($detail['credit']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Solde</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa(abs($detail['balance'])) }}
            <span class="text-[10px] font-normal text-primary/40">{{ $detail['balance'] >= 0 ? 'D' : 'C' }}</span>
        </p>
    </div>
    <div class="bg-white rounded-xl border p-3.5 {{ $detail['open'] !== 0 ? 'border-amber-300 bg-amber-50/60' : 'border-secondary/20' }}">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Reste dû</p>
        <p class="text-base font-heading font-bold {{ $detail['open'] !== 0 ? 'text-amber-700' : 'text-primary' }} mt-0.5">
            {{ $fcfa(abs($detail['open'])) }}
        </p>
        <p class="text-[10px] text-primary/45 mt-0.5">non lettré</p>
    </div>
</div>

<form method="POST" action="{{ route('accounting.ledger.reconcile') }}" x-data="lettrage()">
    @csrf

    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-primary">Mouvements du compte</h2>
                <p class="text-xs text-primary/55">Cochez les lignes qui se compensent pour les lettrer.</p>
            </div>

            {{-- Le service refuse un lettrage déséquilibré : autant le dire
                 avant l'envoi plutôt que par un message d'erreur. --}}
            <div class="flex items-center gap-3 shrink-0" x-show="nbSelection > 0" x-cloak>
                <span class="text-xs" :class="equilibre ? 'text-green-700' : 'text-amber-700'">
                    <span x-text="nbSelection"></span> ligne(s) —
                    <template x-if="equilibre">
                        <span class="font-semibold">équilibré</span>
                    </template>
                    <template x-if="!equilibre">
                        <span class="font-semibold">écart <span x-text="format(Math.abs(solde))"></span></span>
                    </template>
                </span>
                <button type="submit" :disabled="!equilibre"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg transition-colors
                               disabled:opacity-40 disabled:cursor-not-allowed bg-primary text-white hover:bg-surface-dark">
                    <i data-lucide="link" class="w-3.5 h-3.5"></i>
                    Lettrer
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                    <tr>
                        <th class="px-4 py-2.5 w-8"></th>
                        <th class="px-3 py-2.5 text-left font-semibold">Date</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Jnl</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Libellé</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Débit</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Crédit</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Solde</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Lettre</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @forelse($detail['lines'] as $l)
                        <tr class="hover:bg-accent/10 {{ $l['lettre'] ? 'text-primary/45' : '' }}">
                            <td class="px-4 py-2">
                                @unless($l['lettre'])
                                    <input type="checkbox" name="lines[]" value="{{ $l['id'] }}"
                                           @change="bascule($event, {{ $l['debit'] - $l['credit'] }})"
                                           class="rounded border-secondary/40 text-primary focus:ring-primary/30">
                                @endunless
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $l['date']->format('d/m/Y') }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-[10px] font-semibold">{{ $l['journal'] }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('accounting.ledger.entry', $l['entry_id']) }}" class="hover:underline">{{ $l['label'] }}</a>
                                @if($l['reference'])
                                    <span class="block text-[10px] text-primary/40 font-mono">{{ $l['reference'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $l['debit'] ? $fcfa($l['debit']) : '' }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $l['credit'] ? $fcfa($l['credit']) : '' }}</td>
                            <td class="px-3 py-2 text-right font-medium whitespace-nowrap">{{ $fcfa(abs($l['balance'])) }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($l['lettre'])
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-green-100 text-green-700 text-[10px] font-semibold font-mono">
                                        {{ $l['lettre'] }}
                                    </span>
                                @else
                                    <span class="text-primary/25">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-primary/50">Aucun mouvement pour ce tiers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@php
    $lettres = $detail['lines']->pluck('lettre')->filter()->unique()->values();
@endphp

@if($lettres->isNotEmpty())
    <div class="mt-4 bg-white rounded-xl border border-secondary/20 p-4">
        <h3 class="text-xs font-semibold uppercase tracking-widest text-primary/45 mb-2.5">Lettrages en place</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($lettres as $lettre)
                <form method="POST" action="{{ route('accounting.ledger.reconcile.undo') }}"
                      onsubmit="return confirm('Annuler le lettrage {{ $lettre }} ? Les lignes redeviendront des postes ouverts.')">
                    @csrf
                    <input type="hidden" name="code" value="{{ $lettre }}">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-secondary/25 bg-white text-[11px] font-mono font-semibold text-primary/70 hover:border-red-300 hover:text-red-600 transition-colors">
                        {{ $lettre }}
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </form>
            @endforeach
        </div>
        <p class="text-[11px] text-primary/50 mt-2.5">
            Un lettrage n'est pas une écriture : le défaire ne modifie aucun solde, seulement le suivi des impayés.
        </p>
    </div>
@endif

<script>
    function lettrage() {
        return {
            // Montants signés des lignes cochées, en centimes.
            montants: {},
            get nbSelection() {
                return Object.keys(this.montants).length;
            },
            get solde() {
                return Object.values(this.montants).reduce((s, m) => s + m, 0);
            },
            get equilibre() {
                return this.nbSelection >= 2 && this.solde === 0;
            },
            bascule(e, montant) {
                if (e.target.checked) {
                    this.montants[e.target.value] = montant;
                } else {
                    delete this.montants[e.target.value];
                }
            },
            format(centimes) {
                return new Intl.NumberFormat('fr-FR').format(centimes / 100) + ' FCFA';
            },
        };
    }
</script>
@endsection
