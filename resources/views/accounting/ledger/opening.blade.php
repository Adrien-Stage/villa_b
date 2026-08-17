@extends('layouts.hotel')

@section('title', 'Reprise des à-nouveaux')

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Reprise des à-nouveaux</h1>
    <p class="text-sm text-primary/60 mt-1">Soldes d'ouverture de l'exercice</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Continuité d'exploitation.</strong> Un établissement en activité ne démarre pas sa comptabilité à zéro :
    les soldes de bilan de l'exercice précédent (classes 1 à 5) sont reportés en ouverture. Les comptes de gestion,
    eux, repartent à zéro chaque année — ils ne figurent donc pas dans la liste ci-dessous.
    <span class="block mt-1">La reprise ne se fait <strong>qu'une fois</strong> par exercice.</span>
</div>

@if(!$exercice)
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center">
        <p class="text-sm text-primary/70 mb-3">Aucun exercice n'est ouvert.</p>
        <a href="{{ route('accounting.ledger.periods') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
            Ouvrir un exercice
        </a>
    </div>
@elseif($exercice->hasOpeningBalance())
    <div class="bg-white rounded-xl border border-green-200 bg-green-50/40 p-6 text-center">
        <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto mb-3 text-green-600"></i>
        <p class="text-sm font-semibold text-primary">Les à-nouveaux de l'{{ $exercice->label }} ont déjà été repris.</p>
        <p class="text-xs text-primary/55 mt-1">
            Repris le {{ $exercice->opening_posted_at->format('d/m/Y à H:i') }}.
            Toute correction passe désormais par une contre-passation.
        </p>
        <a href="{{ route('accounting.ledger.balance') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
            Voir la balance
        </a>
    </div>
@else
    <form method="POST" action="{{ route('accounting.ledger.opening.store') }}"
          x-data="anouveaux()" @submit="if (!equilibre) { $event.preventDefault(); }">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $exercice->id }}">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <p class="text-sm font-semibold text-primary">{{ $exercice->label }}</p>
                <p class="text-xs text-primary/50">Écriture datée du {{ $exercice->starts_on->format('d/m/Y') }}</p>
            </div>
            <button type="button" @click="ajouter()" class="inline-flex items-center gap-1.5 px-3 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
            </button>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-4">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Compte</th>
                            <th class="px-3 py-2.5 text-right font-semibold w-40">Débit (FCFA)</th>
                            <th class="px-3 py-2.5 text-right font-semibold w-40">Crédit (FCFA)</th>
                            <th class="px-4 py-2.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        <template x-for="(l, i) in lignes" :key="i">
                            <tr>
                                <td class="px-4 py-2">
                                    <select :name="`lines[${i}][account]`" x-model="l.account"
                                            class="w-full rounded-lg border border-secondary/25 bg-white text-xs p-2">
                                        <option value="">— Choisir un compte —</option>
                                        @foreach($comptes as $c)
                                            <option value="{{ $c->code }}">{{ $c->code }} — {{ $c->label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="1" min="0" :name="`lines[${i}][debit]`" x-model.number="l.debit"
                                           @input="if (l.debit > 0) l.credit = 0"
                                           class="w-full text-right rounded-lg border border-secondary/25 bg-white text-xs p-2">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="1" min="0" :name="`lines[${i}][credit]`" x-model.number="l.credit"
                                           @input="if (l.credit > 0) l.debit = 0"
                                           class="w-full text-right rounded-lg border border-secondary/25 bg-white text-xs p-2">
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button type="button" @click="retirer(i)" class="text-primary/35 hover:text-red-600 transition-colors" title="Retirer">
                                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="bg-accent/20 font-semibold text-primary">
                        <tr>
                            <td class="px-4 py-2.5 text-right uppercase tracking-widest text-[10px]">Totaux</td>
                            <td class="px-3 py-2.5 text-right" x-text="format(totalDebit)"></td>
                            <td class="px-3 py-2.5 text-right" x-text="format(totalCredit)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Contrôle d'équilibre en direct : une balance d'ouverture
             déséquilibrée fausserait le grand livre dès le premier jour. --}}
        <div class="rounded-xl border px-4 py-3 mb-4 flex items-center gap-3"
             :class="equilibre ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
            <i :data-lucide="equilibre ? 'check-circle-2' : 'alert-triangle'"
               class="w-5 h-5 shrink-0" :class="equilibre ? 'text-green-600' : 'text-amber-600'"></i>
            <p class="text-xs" :class="equilibre ? 'text-green-800' : 'text-amber-800'">
                <template x-if="equilibre"><span class="font-semibold">Balance équilibrée.</span></template>
                <template x-if="!equilibre">
                    <span><span class="font-semibold">Balance déséquilibrée</span> — écart de
                        <span x-text="format(Math.abs(totalDebit - totalCredit))"></span> FCFA.
                        La reprise ne pourra pas être enregistrée en l'état.</span>
                </template>
            </p>
        </div>

        <div class="flex flex-col sm:flex-row justify-end gap-2">
            <a href="{{ route('accounting.ledger.index') }}" class="px-5 py-2.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors text-center">
                Annuler
            </a>
            <button type="submit" :disabled="!equilibre"
                    class="px-5 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                Enregistrer les à-nouveaux
            </button>
        </div>
    </form>

    <script>
        function anouveaux() {
            return {
                lignes: [
                    { account: '', debit: 0, credit: 0 },
                    { account: '', debit: 0, credit: 0 },
                ],
                ajouter() {
                    this.lignes.push({ account: '', debit: 0, credit: 0 });
                    this.$nextTick(() => window.lucide?.createIcons());
                },
                retirer(i) {
                    if (this.lignes.length > 1) this.lignes.splice(i, 1);
                },
                get totalDebit() {
                    return this.lignes.reduce((s, l) => s + (Number(l.debit) || 0), 0);
                },
                get totalCredit() {
                    return this.lignes.reduce((s, l) => s + (Number(l.credit) || 0), 0);
                },
                get equilibre() {
                    return this.totalDebit > 0 && this.totalDebit === this.totalCredit;
                },
                format(n) {
                    return new Intl.NumberFormat('fr-FR').format(n || 0);
                },
            };
        }
    </script>
@endif
@endsection
