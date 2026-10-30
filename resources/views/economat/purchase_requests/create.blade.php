@extends('layouts.hotel')

@section('title', 'Nouvelle demande d\'achat — Économat')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="purchaseRequestForm({{ Js::from($items->map(fn ($it) => [
    'id' => $it->id, 'name' => $it->name, 'reference' => $it->reference, 'unit' => $it->shortUnit(),
    'stock' => (float) $it->current_stock,
])->values()) }}, {{ Js::from($suppliers->map(fn ($s) => ['id' => $s->id, 'nom' => $s->name])->values()) }})">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.purchase_requests.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour aux demandes d'achat">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Exprimer un besoin d'approvisionnement</h1>
            <p class="text-sm text-primary/60 mt-0.5">Saisissez les articles, choisissez le fournisseur de chacun dans la carte de droite : chaque fournisseur reçoit sa propre demande, vérifiée par le responsable des achats.</p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.purchase_requests.store') }}" @submit="verifier($event)"
          class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        @csrf

        <div class="lg:col-span-2 space-y-6 min-w-0">
            {{-- Paramètres généraux --}}
            <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
                <div class="grid grid-cols-1 {{ $choisitDepartement ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4">
                    @if($choisitDepartement)
                        <div>
                            <label for="demande-departement" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Département demandeur *</label>
                            <select id="demande-departement" name="department" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                                @foreach($departments as $key => $lbl)
                                    <option value="{{ $key }}" @selected(old('department', 'cuisine') === $key)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="demande-urgence" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Degré d'urgence *</label>
                        <select id="demande-urgence" name="priority" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                            <option value="low" @selected(old('priority') === 'low')>Basse (Anticipation)</option>
                            <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normale</option>
                            <option value="urgent" @selected(old('priority') === 'urgent')>Urgente (Rupture imminente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Demandeur</label>
                        <input type="text" readonly disabled value="{{ auth()->user()->name }}" class="w-full px-3 py-2 text-sm border border-secondary/20 bg-gray-50 rounded-lg text-primary/70">
                    </div>
                </div>

                <div>
                    <label for="demande-motif" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Motif / Justification du besoin</label>
                    <textarea id="demande-motif" name="purpose" rows="2" placeholder="Ex: Réapprovisionnement hebdomadaire cuisine + banquet samedi soir..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">{{ old('purpose') }}</textarea>
                </div>
            </div>

            {{-- Lignes d'articles --}}
            <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm flex flex-col">
                <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
                        <p class="text-xs text-primary/50">Chaque article prend le prix de son fournisseur à la dernière livraison.</p>
                    </div>
                    <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                    </button>
                </div>

                <div x-ref="linesBody" class="overflow-x-auto max-h-[560px] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50/95 sticky top-0 z-10 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10 backdrop-blur-sm">
                            <tr>
                                <th class="px-3 py-2.5 text-left min-w-[12rem]">Article</th>
                                <th class="px-3 py-2.5 text-right w-16">Stock</th>
                                <th class="px-3 py-2.5 text-right w-24">Quantité</th>
                                <th class="px-3 py-2.5 text-left min-w-[12rem]">Fournisseur et prix (F)</th>
                                <th class="px-3 py-2.5 text-right w-24">Total (F)</th>
                                <th class="px-2 py-2.5 text-center w-10"><span class="sr-only">Retirer</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary/10">
                            <template x-for="(line, index) in lines" :key="line.uid">
                                <tr :class="active === line.uid ? 'bg-accent/20' : 'hover:bg-gray-50/40'">
                                    <td class="px-3 py-2.5">
                                        <x-article-picker source="catalogue" model="line.stock_item_id" name-bind="'lines[' + index + '][stock_item_id]'" size="sm" required
                                            on-select="onItemChange(line, $event.detail.article)" aria-label="Article" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs text-primary/60 whitespace-nowrap">
                                        <span x-text="line.current_stock !== null ? formatNumber(line.current_stock) + ' ' + line.unit : '—'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <input type="number" step="0.001" min="0.001" :name="'lines[' + index + '][quantity_requested]'" x-model="line.quantity" required
                                               :aria-label="'Quantité de la ligne ' + (index + 1)"
                                               class="w-20 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="0">
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <template x-if="line.supplier_id">
                                            <div>
                                                <input type="hidden" :name="'lines[' + index + '][supplier_id]'" :value="line.supplier_id">
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="font-semibold text-primary text-xs leading-tight" x-text="line.supplier_nom"></span>
                                                    <button type="button" @click="activer(line)" class="text-[11px] text-primary/50 underline hover:text-primary">changer</button>
                                                </div>
                                                <template x-if="line.prix_connu">
                                                    <div>
                                                        <input type="hidden" :name="'lines[' + index + '][estimated_unit_price]'" :value="line.unit_price">
                                                        <span class="font-mono font-bold text-primary whitespace-nowrap" x-text="formatMoney(line.unit_price) + ' F / ' + line.unit"></span>
                                                        <span class="block text-[10px] text-primary/50" x-text="line.prix_source"></span>
                                                    </div>
                                                </template>
                                                {{-- Aucun prix connu chez ce fournisseur : il se saisit. --}}
                                                <template x-if="!line.prix_connu">
                                                    <div class="mt-1">
                                                        <input type="number" min="1" step="1" :name="'lines[' + index + '][estimated_unit_price]'" x-model="line.prix_saisi"
                                                               @input="line.unit_price = parseFloat(line.prix_saisi) || 0" required
                                                               :aria-label="'Prix estimé de la ligne ' + (index + 1)"
                                                               class="w-28 px-2 py-1 text-sm border border-amber-300 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="Prix">
                                                        <span class="block text-[10px] text-amber-700">Jamais livré par ce fournisseur : prix estimé</span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!line.supplier_id">
                                            <button type="button" @click="activer(line)" :disabled="!line.stock_item_id"
                                                    class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                                    :class="line.stock_item_id ? 'border-primary/30 text-primary hover:bg-accent/30' : 'border-secondary/20 text-primary/30 cursor-not-allowed'">
                                                <span x-text="line.stock_item_id ? 'Choisir le fournisseur →' : 'Saisissez d\'abord l\'article'"></span>
                                            </button>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-primary whitespace-nowrap">
                                        <span x-text="formatMoney(lineTotal(line))"></span> F
                                    </td>
                                    <td class="px-2 py-2.5 text-center">
                                        <button type="button" @click="removeLine(index)"
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                                                :aria-label="'Retirer la ligne ' + (index + 1)" title="Retirer cet article">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            <span class="sr-only">Retirer</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-secondary/15 bg-gray-50/70 space-y-2 text-sm shrink-0">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                        </button>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-primary/60">Montant estimé total :</span>
                            <span class="font-mono text-base font-bold text-primary" x-text="formatMoney(grandTotal()) + ' FCFA'"></span>
                        </div>
                    </div>
                    {{-- Ce que l'envoi va créer : une demande par fournisseur. --}}
                    <template x-if="demandes().length > 0">
                        <p class="text-xs text-primary/70" role="status">
                            <span class="font-semibold" x-text="demandes().length > 1 ? demandes().length + ' demandes d\'achat seront créées, une par fournisseur :' : 'Une demande d\'achat sera créée :'"></span>
                            <span x-text="demandes().map(d => d.nom + ' (' + d.lignes + ' article' + (d.lignes > 1 ? 's' : '') + ', ' + formatMoney(d.total) + ' F)').join(' · ')"></span>
                        </p>
                    </template>
                    <p x-show="erreur" x-text="erreur" class="text-xs font-semibold text-red-700" role="alert"></p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('economat.purchase_requests.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">
                    Annuler
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span x-text="demandes().length > 1 ? 'Soumettre les ' + demandes().length + ' demandes d\'achat' : 'Soumettre la demande d\'achat'">Soumettre la demande d'achat</span>
                </button>
            </div>
        </div>

        {{-- Carte des fournisseurs de l'article saisi --}}
        <aside class="lg:sticky lg:top-4 bg-white border border-secondary/20 rounded-xl shadow-sm overflow-hidden" aria-labelledby="titre-carte-fournisseurs" aria-live="polite">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15">
                <h2 id="titre-carte-fournisseurs" class="text-sm font-semibold text-primary">Fournisseurs de l'article</h2>
                <p class="text-xs text-primary/50" x-text="carte ? carte.article.nom + ' — prix par ' + carte.article.unite : 'Saisissez un article : ses fournisseurs s\'affichent ici.'"></p>
            </div>

            <template x-if="!ligneActive()">
                <div class="px-5 py-8 text-center text-sm text-primary/40">
                    <i data-lucide="truck" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                    Le prix et la date de la dernière livraison de chaque fournisseur apparaîtront ici.
                </div>
            </template>

            <template x-if="ligneActive()">
                <div class="p-4 space-y-4">
                    {{-- Comparer sur une période --}}
                    <fieldset class="rounded-lg border border-secondary/20 p-3">
                        <legend class="px-1 text-[11px] font-semibold uppercase tracking-wider text-primary/50">Comparer sur une période</legend>
                        <div class="flex items-end gap-2">
                            <label class="flex-1 text-[11px] text-primary/60">Du
                                <input type="date" x-model="du" class="mt-0.5 w-full px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                            </label>
                            <label class="flex-1 text-[11px] text-primary/60">Au
                                <input type="date" x-model="au" class="mt-0.5 w-full px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                            </label>
                            <button type="button" @click="charger()" class="px-2.5 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">Comparer</button>
                        </div>
                    </fieldset>

                    <p x-show="chargement" class="text-xs text-primary/50">Chargement des fournisseurs…</p>

                    <template x-if="carte && !chargement">
                        <div class="space-y-2">
                            <template x-if="carte.fournisseurs.length === 0">
                                <p class="text-xs text-primary/60">Aucun fournisseur ne livre encore cet article. Choisissez-en un ci-dessous ; son prix se saisira sur la ligne.</p>
                            </template>
                            <template x-for="f in carte.fournisseurs" :key="f.id">
                                <div class="rounded-lg border border-secondary/20 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-primary" x-text="f.nom"></p>
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                <span x-show="f.moinsCher" class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-sky-200 bg-sky-50 text-sky-700">Moins cher</span>
                                                <span x-show="f.habituel" class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-700">Habituel</span>
                                            </div>
                                        </div>
                                        <button type="button" @click="choisir(f)" class="shrink-0 px-2.5 py-1 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">Choisir</button>
                                    </div>
                                    <p class="mt-1.5 text-xs text-primary/80">
                                        <template x-if="f.derniere">
                                            <span>Dernière livraison : <strong class="font-mono" x-text="formatMoney(f.derniere.prix / 100) + ' F'"></strong>
                                                le <span x-text="formatDate(f.derniere.date)"></span></span>
                                        </template>
                                        <template x-if="!f.derniere">
                                            <span class="text-primary/50" x-text="f.tarif ? 'Pas encore livré — tarif ' + formatMoney(f.tarif / 100) + ' F' : 'Pas encore livré, aucun prix connu'"></span>
                                        </template>
                                    </p>
                                    <template x-if="f.periode">
                                        <p class="mt-1 text-[11px] text-primary/60" x-text="f.periode.livraisons === 0
                                            ? 'Aucune livraison sur la période'
                                            : f.periode.livraisons + ' livraison(s) sur la période : de ' + formatMoney(f.periode.min / 100) + ' à ' + formatMoney(f.periode.max / 100) + ' F, dernière à ' + formatMoney(f.periode.dernier / 100) + ' F'"></p>
                                    </template>
                                </div>
                            </template>

                            {{-- Un fournisseur qui ne livre pas encore cet article --}}
                            <div class="flex items-center gap-2 pt-1">
                                <select x-model="autre" aria-label="Autre fournisseur" class="flex-1 px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                                    <option value="">Autre fournisseur…</option>
                                    <template x-for="s in autresFournisseurs()" :key="s.id"><option :value="s.id" x-text="s.nom"></option></template>
                                </select>
                                <button type="button" @click="choisirAutre()" :disabled="!autre" class="px-2.5 py-1.5 border border-primary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/30 disabled:opacity-40">Choisir</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </aside>
    </form>
</div>

<script>
function purchaseRequestForm(catalogue, fournisseurs) {
    const baseUrl = @js(url('/economat/demandes-achat/articles'));
    const ligneVide = (uid) => ({ uid, stock_item_id: '', unit: '', current_stock: null, quantity: 1, supplier_id: '', supplier_nom: '',
        unit_price: 0, prix_connu: false, prix_source: '', prix_saisi: '', notes: '' });

    return {
        catalogue,
        fournisseurs,
        prochain: 1,
        lines: [ligneVide(0)],
        active: null,
        carte: null,
        chargement: false,
        du: '',
        au: '',
        autre: '',
        erreur: '',
        nouvelleLigne() {
            return ligneVide(this.prochain++);
        },
        addLine() {
            this.lines.push(this.nouvelleLigne());
            this.$nextTick(() => {
                window.refreshLucideIcons?.();
                if (this.$refs.linesBody) {
                    this.$refs.linesBody.scrollTop = this.$refs.linesBody.scrollHeight;
                }
            });
        },
        // Chaque ligne s'efface ; la dernière laisse une ligne vide à remplir.
        removeLine(idx) {
            if (this.lines[idx]?.uid === this.active) this.fermer();
            this.lines.splice(idx, 1);
            if (this.lines.length === 0) {
                this.lines.push(this.nouvelleLigne());
            }
        },
        ligneActive() {
            return this.lines.find(l => l.uid === this.active && l.stock_item_id) || null;
        },
        onItemChange(line, article) {
            line.supplier_id = '';
            line.supplier_nom = '';
            line.unit_price = 0;
            line.prix_connu = false;
            line.prix_saisi = '';
            if (article) {
                line.unit = article.unit || '';
                line.current_stock = parseFloat(article.stock || 0);
                this.activer(line);
            } else {
                line.unit = '';
                line.current_stock = null;
                if (this.active === line.uid) this.fermer();
            }
        },
        // La carte montre les fournisseurs de l'article de cette ligne.
        activer(line) {
            if (!line.stock_item_id) return;
            this.active = line.uid;
            this.autre = '';
            this.charger();
        },
        fermer() {
            this.active = null;
            this.carte = null;
        },
        async charger() {
            const line = this.ligneActive();
            if (!line) return;
            const params = new URLSearchParams();
            if (this.du) params.set('du', this.du);
            if (this.au) params.set('au', this.au);
            this.chargement = true;
            try {
                const reponse = await fetch(`${baseUrl}/${line.stock_item_id}/fournisseurs?${params}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                this.carte = reponse.ok ? await reponse.json() : { article: { nom: '', unite: line.unit }, fournisseurs: [] };
            } catch (e) {
                this.carte = { article: { nom: '', unite: line.unit }, fournisseurs: [] };
            } finally {
                this.chargement = false;
                this.$nextTick(() => window.refreshLucideIcons?.());
            }
        },
        // Le fournisseur choisi passe sur la ligne avec son prix ; la carte s'efface pour la suivante.
        choisir(f) {
            const line = this.ligneActive();
            if (!line) return;
            const prix = f.derniere ? f.derniere.prix : f.tarif;
            line.supplier_id = f.id;
            line.supplier_nom = f.nom;
            line.prix_connu = !!prix;
            line.unit_price = prix ? Math.round(prix / 100) : (parseFloat(line.prix_saisi) || 0);
            line.prix_source = f.derniere ? 'Livré le ' + this.formatDate(f.derniere.date) : (f.tarif ? 'Tarif au catalogue, pas encore livré' : '');
            this.erreur = '';
            this.fermer();
        },
        autresFournisseurs() {
            const connus = (this.carte?.fournisseurs || []).map(f => f.id);
            return this.fournisseurs.filter(s => !connus.includes(s.id));
        },
        choisirAutre() {
            const s = this.fournisseurs.find(x => String(x.id) === String(this.autre));
            if (s) this.choisir({ id: s.id, nom: s.nom, derniere: null, tarif: null });
        },
        // Une demande par fournisseur : ce que l'envoi va créer.
        demandes() {
            const groupes = {};
            this.lines.filter(l => l.supplier_id).forEach(l => {
                const g = groupes[l.supplier_id] ??= { nom: l.supplier_nom, lignes: 0, total: 0 };
                g.lignes++;
                g.total += this.lineTotal(l);
            });
            return Object.values(groupes);
        },
        verifier(event) {
            const sansFournisseur = this.lines.filter(l => l.stock_item_id && !l.supplier_id).length;
            this.erreur = sansFournisseur > 0
                ? (sansFournisseur > 1 ? sansFournisseur + ' articles n\'ont' : 'Un article n\'a') + ' pas encore de fournisseur : choisissez-le dans la carte de droite.'
                : '';
            if (this.erreur) event.preventDefault();
        },
        lineTotal(line) {
            const q = parseFloat(line.quantity) || 0;
            const p = parseFloat(line.unit_price) || 0;
            return Math.round(q * p);
        },
        grandTotal() {
            return this.lines.reduce((sum, l) => sum + this.lineTotal(l), 0);
        },
        formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR').format(Math.round(amount || 0));
        },
        formatNumber(num) {
            if (num === null || num === undefined) return '—';
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(num);
        },
        formatDate(iso) {
            if (!iso) return '';
            const [a, m, j] = String(iso).slice(0, 10).split('-');
            return `${j}/${m}/${a}`;
        }
    };
}
</script>
@endsection
