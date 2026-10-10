@extends('layouts.hotel')

@section('title', 'Transformation — ' . $item->name)

@section('content')
@php
    use App\Support\Conditionnement;
    $niveaux = $item->packagings->values();
    $saisie = old('niveaux', $niveaux->map(fn ($n, $i) => [
        'nom'        => $n->name,
        'contenance' => (int) round((float) $n->factor / ($i === 0 ? 1 : (float) $niveaux[$i - 1]->factor)),
        'fermes'     => (int) $n->closed_count,
    ])->all());
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary';
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <a href="{{ route('economat.items.show', $item) }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la fiche de l'article
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Transformation</p>
        <h1 class="text-xl font-heading font-semibold text-primary mt-0.5">{{ $item->name }}</h1>
        <div class="mt-3 flex flex-wrap items-baseline gap-x-6 gap-y-1">
            <p class="text-sm text-primary/70">Stock : <strong class="font-mono text-primary">{{ Conditionnement::libelle((float) $item->current_stock, $item->unit) }}</strong></p>
            @if($etat)
                <p class="text-sm text-primary/70">Soit : <strong class="text-primary">{{ Conditionnement::decomposition($etat, $item->unit) }}</strong></p>
            @endif
        </div>
    </div>

    {{-- Conditionnements --}}
    <div class="bg-white border border-secondary/20 rounded-xl shadow-sm"
         x-data="conditionnements({{ Js::from(array_values($saisie)) }}, @js($item->unit))">
        <div class="px-6 py-4 border-b border-secondary/15">
            <h2 class="font-heading font-semibold text-primary">Conditionnements</h2>
            <p class="text-xs text-primary/60 mt-1">
                Le stock reste compté en <strong>{{ $item->unit }}</strong>, la plus petite unité. Décrivez les conditionnements du plus petit au plus grand :
                chacun contient un nombre entier d'unités du niveau du dessous. Une sortie prend d'abord le vrac, puis les plus petites unités fermées,
                et n'ouvre un paquet ou un carton que quand il le faut.
            </p>
        </div>

        @droit('economat.items.transformation.packagings')
            <form method="POST" action="{{ route('economat.items.transformation.packagings', $item) }}" class="p-6 space-y-3">
                @csrf
                <template x-for="(niveau, i) in niveaux" :key="niveau.cle">
                    <div class="grid grid-cols-12 gap-3 items-end p-3 rounded-lg border border-secondary/15 bg-surface-light/40">
                        <div class="col-span-12 sm:col-span-4">
                            <label :for="'cond-nom-' + niveau.cle" class="block text-[11px] font-semibold uppercase text-primary/50 mb-1">Conditionnement</label>
                            <select :id="'cond-nom-' + niveau.cle" :name="`niveaux[${i}][nom]`" x-model="niveau.nom" required class="{{ $champ }}">
                                <option value="">Choisir…</option>
                                @foreach($unites as $u)
                                    @if(mb_strtolower($u) !== mb_strtolower($item->unit))
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-7 sm:col-span-4">
                            <label :for="'cond-contenance-' + niveau.cle" class="block text-[11px] font-semibold uppercase text-primary/50 mb-1">
                                Contient <span class="normal-case font-normal" x-text="'(en ' + dessous(i) + ')'"></span>
                            </label>
                            <input :id="'cond-contenance-' + niveau.cle" type="number" min="2" step="1" :name="`niveaux[${i}][contenance]`" x-model.number="niveau.contenance" required class="{{ $champ }} font-mono text-right">
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <label :for="'cond-fermes-' + niveau.cle" class="block text-[11px] font-semibold uppercase text-primary/50 mb-1">Encore fermés</label>
                            <input :id="'cond-fermes-' + niveau.cle" type="number" min="0" step="1" :name="`niveaux[${i}][fermes]`" x-model.number="niveau.fermes" class="{{ $champ }} font-mono text-right">
                        </div>
                        <div class="col-span-12 sm:col-span-1 text-right">
                            <button type="button" @click="niveaux.splice(i, 1)" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg" aria-label="Retirer ce conditionnement">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <p class="col-span-12 text-xs text-primary/60" x-show="niveau.nom && niveau.contenance >= 2" x-text="resume(i)"></p>
                    </div>
                </template>

                <p x-show="niveaux.length === 0" class="text-sm text-primary/50 py-2">
                    Aucun conditionnement : l'article est compté et servi en {{ $item->unit }} uniquement.
                </p>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <button type="button" @click="ajouter()" x-show="niveaux.length < 6"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-primary/10 hover:bg-primary/20 text-primary text-xs font-semibold rounded-lg">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un conditionnement
                    </button>
                    <div class="flex items-center gap-3">
                        <span class="text-xs" :class="fermeTotal() > {{ (float) $item->current_stock }} ? 'text-red-600 font-semibold' : 'text-primary/50'"
                              x-text="'Unités fermées : ' + quantite(fermeTotal(), unite) + ' sur ' + quantite({{ (float) $item->current_stock }}, unite) + ' en stock'"></span>
                        <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Enregistrer</button>
                    </div>
                </div>
            </form>
        @else
            <div class="p-6 text-sm text-primary/70">
                @forelse($niveaux as $n)
                    <p>1 {{ $n->name }} = {{ Conditionnement::libelle((float) $n->factor, $item->unit) }} · encore fermés : {{ Conditionnement::libelle($n->closed_count, $n->name) }}</p>
                @empty
                    <p>Aucun conditionnement.</p>
                @endforelse
            </div>
        @enddroit
    </div>

    {{-- Ouverture à la main --}}
    @if($niveaux->isNotEmpty())
        @droit('economat.items.transformation.open')
            <div class="bg-white border border-secondary/20 rounded-xl shadow-sm">
                <div class="px-6 py-4 border-b border-secondary/15">
                    <h2 class="font-heading font-semibold text-primary">Ouvrir des unités</h2>
                    <p class="text-xs text-primary/60 mt-1">
                        Pour ranger à l'avance des paquets ouverts : le contenu passe au conditionnement du dessous, ou en vrac. Le stock ne change pas.
                    </p>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($niveaux->sortByDesc('factor') as $n)
                        <form method="POST" action="{{ route('economat.items.transformation.open', $item) }}" class="rounded-lg border border-secondary/15 p-4 space-y-2">
                            @csrf
                            <input type="hidden" name="niveau" value="{{ $n->name }}">
                            <p class="text-sm font-semibold text-primary">{{ ucfirst(Conditionnement::accorde($n->name, 2)) }}</p>
                            <p class="text-xs text-primary/60">Encore fermés : {{ Conditionnement::libelle($n->closed_count, $n->name) }} · 1 {{ $n->name }} = {{ Conditionnement::libelle((float) $n->factor, $item->unit) }}</p>
                            <div class="flex items-center gap-2">
                                <label for="ouvrir-{{ $loop->index }}" class="sr-only">Nombre à ouvrir</label>
                                <input id="ouvrir-{{ $loop->index }}" type="number" name="nombre" min="1" max="{{ max(1, $n->closed_count) }}" value="1" required
                                       class="w-24 px-3 py-2 text-sm border border-secondary/30 rounded-lg font-mono text-right" @disabled($n->closed_count < 1)>
                                <button type="submit" @disabled($n->closed_count < 1)
                                        class="px-3 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark disabled:opacity-40 disabled:cursor-not-allowed">
                                    Ouvrir
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @enddroit
    @endif

    {{-- Historique --}}
    @if($historique->isNotEmpty())
        <div class="bg-white border border-secondary/20 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-3 border-b border-secondary/15"><h2 class="text-sm font-semibold text-primary">Dernières transformations</h2></div>
            <ul class="divide-y divide-secondary/10">
                @foreach($historique as $m)
                    <li class="px-6 py-2.5 text-xs flex flex-wrap justify-between gap-2">
                        <span class="text-primary/80">{{ $m->reason }}</span>
                        <span class="text-primary/50">{{ $m->occurred_at->format('d/m/Y H:i') }} · {{ $m->user?->name ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function conditionnements(saisie, unite) {
        let cle = 1;
        return {
            unite,
            niveaux: saisie.map(n => ({ cle: cle++, nom: n.nom || '', contenance: Number(n.contenance) || '', fermes: Number(n.fermes) || 0 })),
            ajouter() { this.niveaux.push({ cle: cle++, nom: '', contenance: '', fermes: 0 }); },
            dessous(i) { return i === 0 ? this.unite : (this.niveaux[i - 1].nom || 'niveau précédent'); },
            facteur(i) {
                let f = 1;
                for (let k = 0; k <= i; k++) { f *= Number(this.niveaux[k].contenance) || 0; }
                return f;
            },
            resume(i) {
                const n = this.niveaux[i];
                let texte = '1 ' + n.nom + ' = ' + this.quantite(n.contenance, this.dessous(i));
                if (i > 0) { texte += ' = ' + this.quantite(this.facteur(i), this.unite); }
                return texte;
            },
            // « 20 paquets », « 2,5 kg » : comme App\Support\Conditionnement::libelle.
            quantite(v, unite) {
                const invariables = ['kg', 'g', 'mg', 'l', 'cl', 'ml', 'dl', 'm', 'cm', 'mm', 'm2', 'm3', 'u'];
                let mot = unite || '';
                if (Math.abs(v) >= 2 && mot && !invariables.includes(mot.toLowerCase()) && !/[sxz]$/i.test(mot)) {
                    const mots = mot.split(' ');
                    mots[0] += /au$/.test(mots[0]) ? 'x' : 's';
                    mot = mots.join(' ');
                }
                return this.nombre(v) + ' ' + mot;
            },
            fermeTotal() {
                return this.niveaux.reduce((s, n, i) => s + (Number(n.fermes) || 0) * this.facteur(i), 0);
            },
            nombre(v) { return new Intl.NumberFormat('fr-FR').format(v || 0); },
        };
    }
</script>
@endpush
