@extends('layouts.hotel')

@section('title', 'Articles — Économat')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="stockItems({{ Js::from($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) }}, {{ Js::from($suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()) }}, {{ Js::from($unites) }})">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Articles</h1>
            <p class="text-sm text-primary/60 mt-0.5">Catalogue du magasin central et niveaux de stock.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('economat.items.export') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Exporter les articles en CSV">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </a>
            <button type="button" onclick="document.getElementById('modal-import-stock').classList.remove('hidden')" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Importer des articles depuis un CSV">
                <i data-lucide="upload" class="w-4 h-4"></i> Importer
            </button>
            <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouvel article
            </button>
        </div>
    </div>

    @include('economat.partials.flash')
    <x-csv-import-errors />

    <x-csv-import-modal
        id="modal-import-stock"
        title="Importer des articles (CSV)"
        :action="route('economat.items.import')"
        :template="route('economat.items.export', ['template' => 1])"
        structure="nom;reference;unite;categorie;fournisseur;stock_min;cout_moyen_fcfa;actif;stock_initial"
        submit-label="Importer les articles">
        <li><strong>nom</strong> obligatoire — les noms déjà existants sont ignorés (pas de doublon)</li>
        <li><strong>categorie</strong> et <strong>fournisseur</strong> optionnels, mais doivent exister s'ils sont renseignés</li>
        <li><strong>stock_initial</strong> facultatif : quantité déjà en magasin, reprise au <strong>cout_moyen_fcfa</strong> (obligatoire dans ce cas). Sans elle, le stock démarre à 0</li>
    </x-csv-import-modal>

    <div class="flex gap-2 mb-4">
        <a href="{{ route('economat.items.index') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ !$filter ? 'bg-primary text-white border-primary' : 'border-secondary/30 text-primary/60 hover:bg-accent/10' }}">Tous</a>
        <a href="{{ route('economat.items.index', ['filter' => 'alert']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $filter === 'alert' ? 'bg-amber-500 text-white border-amber-500' : 'border-secondary/30 text-primary/60 hover:bg-accent/10' }}">Sous le seuil</a>
    </div>

    <x-table :rows="$items" :empty="$filter === 'alert' ? 'Aucun article sous le seuil.' : 'Aucun article. Créez-en un pour démarrer le magasin.'" empty-icon="boxes" caption="Articles du magasin central">
        <x-slot:head>
            <x-table.col>Article</x-table.col>
            <x-table.col hide="xl">Catégorie</x-table.col>
            <x-table.col align="right">Stock</x-table.col>
            <x-table.col align="right" hide="lg">Coût moyen</x-table.col>
            <x-table.col align="right">Valeur</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($items as $item)
            @php
                $level = $item->stockLevel();
                // Payloads précalculés : un @json multi-clés inline dans un
                // attribut casse le compilateur Blade.
                $editPayload = [
                    'id' => $item->id, 'name' => $item->name, 'reference' => $item->reference,
                    'unit' => $item->unit, 'description' => $item->description,
                    'stock_category_id' => $item->stock_category_id, 'supplier_id' => $item->supplier_id,
                    'min_stock' => (float) $item->min_stock, 'is_active' => (bool) $item->is_active,
                ];
                $adjustPayload = [
                    'id' => $item->id, 'name' => $item->name,
                    'unit' => $item->unit, 'current' => (float) $item->current_stock,
                ];
                $openingPayload = [
                    'id' => $item->id, 'name' => $item->name, 'unit' => $item->unit,
                    'unit_cost' => (int) round($item->average_cost / 100),
                ];
            @endphp
            <x-table.row :muted="! $item->is_active">
                <x-table.cell>
                    <a href="{{ route('economat.items.show', $item) }}" class="font-medium text-primary hover:underline">{{ $item->name }}</a>
                    @if($item->reference)<span class="block font-mono text-[10px] text-primary/45">{{ $item->reference }}</span>@endif
                </x-table.cell>
                <x-table.cell hide="xl" class="text-xs text-primary/60">{{ $item->category?->name ?? '—' }}</x-table.cell>
                <x-table.cell align="right" nowrap>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $level === 'out' ? 'bg-red-500' : ($level === 'low' ? 'bg-amber-500' : 'bg-green-500') }}" aria-hidden="true"></span>
                        <span class="font-medium text-primary">{{ rtrim(rtrim(number_format($item->current_stock, 3, ',', ' '), '0'), ',') }}</span>
                        <span class="text-xs text-primary/45">{{ $item->unit }}</span>
                    </span>
                </x-table.cell>
                <x-table.cell align="right" hide="lg" nowrap class="text-primary/70">{{ number_format($item->average_cost / 100, 0, ',', ' ') }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-medium">{{ number_format($item->stockValue() / 100, 0, ',', ' ') }}</x-table.cell>
                <x-table.actions :label="'Actions pour '.$item->name">
                    <x-table.action :href="route('economat.items.show', $item)" icon="eye">Fiche</x-table.action>
                    @if($item->movements_count === 0)
                        @droit('economat.items.opening')
                            <x-table.action icon="package-plus" x-on:click="openOpening({{ Js::from($openingPayload) }})" title="Reprendre le stock déjà en magasin">Reprise du stock</x-table.action>
                        @enddroit
                    @endif
                    <x-table.action icon="scale" x-on:click="openAdjust({{ Js::from($adjustPayload) }})">Ajuster le stock</x-table.action>
                    <x-table.action icon="pencil" x-on:click="openEdit({{ Js::from($editPayload) }})">Modifier</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>

    {{-- Modal création / édition --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);">
        <div class="absolute inset-0" @click="open = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier l\'article' : 'Nouvel article'"></h3>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4 overflow-y-auto">
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" @input="applyAutoCode()" required maxlength="160" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="article-unite" class="block text-xs font-medium text-primary/70 mb-1.5">Unité <span class="text-red-500">*</span></label>
                            <select id="article-unite" name="unit" x-model="form.unit" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">Choisir…</option>
                                <template x-for="u in unitesProposees" :key="u"><option :value="u" x-text="u" :selected="u === form.unit"></option></template>
                            </select>
                            @if(\App\Support\SettingsTabs::peutRegler(auth()->user(), 'economat'))
                                <a href="{{ route('settings.index', ['tab' => 'economat']) }}" class="mt-1 inline-block text-[11px] text-primary/50 underline hover:text-primary">Gérer les unités</a>
                            @endif
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Référence</label>
                            <input type="text" name="reference" x-model="form.reference" @input="autoCode = false" maxlength="60" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Catégorie</label>
                            <select name="stock_category_id" x-model="form.stock_category_id" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">—</option>
                                <template x-for="c in categories" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Fournisseur habituel</label>
                            <select name="supplier_id" x-model="form.supplier_id" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">—</option>
                                <template x-for="s in suppliers" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Seuil d'alerte</label>
                            <input type="number" step="0.001" min="0" name="min_stock" x-model="form.min_stock" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                        <div x-show="!editing">
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Coût moyen initial (FCFA)</label>
                            <input type="number" min="0" name="average_cost" x-model="form.average_cost" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Description</label>
                        <textarea name="description" x-model="form.description" rows="2" maxlength="500" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary"></textarea>
                    </div>
                    <label class="flex items-center gap-2.5 px-3 py-2.5 border border-secondary/30 rounded-lg cursor-pointer">
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded border-secondary/40 text-primary">
                        <span class="text-xs text-primary/80">Article actif</span>
                    </label>
                    <p class="text-[11px] text-primary/40" x-show="!editing">Le stock démarre à 0. Marchandise déjà en magasin : utilisez « Reprise » ; sinon, une réception de bon l'alimente.</p>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Créer'"></span></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal reprise du stock initial --}}
    <div x-show="openingOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);"
         @keydown.escape.window="openingOpen = false">
        <div class="absolute inset-0" @click="openingOpen = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10" role="dialog" aria-modal="true" aria-labelledby="titre-reprise">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 id="titre-reprise" class="font-heading font-semibold text-primary">Reprise du stock initial</h3>
                <button type="button" @click="openingOpen = false" class="text-primary/30 hover:text-primary" aria-label="Fermer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="openingAction">
                @csrf
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-primary/70">Article : <strong x-text="opening.name"></strong></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="reprise-quantite" class="block text-xs font-medium text-primary/70 mb-1.5">Quantité en magasin <span class="text-red-500">*</span></label>
                            <input id="reprise-quantite" type="number" step="0.001" min="0.001" name="quantity" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                            <p class="text-[11px] text-primary/40 mt-1" x-text="opening.unit"></p>
                        </div>
                        <div>
                            <label for="reprise-cout" class="block text-xs font-medium text-primary/70 mb-1.5">Coût unitaire (FCFA) <span class="text-red-500">*</span></label>
                            <input id="reprise-cout" type="number" step="1" min="1" name="unit_cost" x-model="opening.unit_cost" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                    </div>
                    <p class="text-[11px] text-primary/50">
                        Une seule fois, avant tout autre mouvement de l'article. Ce coût devient son coût moyen.
                        Au grand livre, la valeur entre par les à-nouveaux du comptable.
                    </p>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="openingOpen = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Reprendre</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal ajustement de stock --}}
    <div x-show="adjustOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);">
        <div class="absolute inset-0" @click="adjustOpen = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 class="font-heading font-semibold text-primary">Ajuster le stock</h3>
                <button type="button" @click="adjustOpen = false" class="text-primary/30 hover:text-primary"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="adjustAction">
                @csrf
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-primary/70">Article : <strong x-text="adjust.name"></strong></p>
                    <p class="text-xs text-primary/50">Stock actuel : <span x-text="adjust.current"></span> <span x-text="adjust.unit"></span></p>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Quantité constatée <span class="text-red-500">*</span></label>
                        <input type="number" step="0.001" min="0" name="counted_quantity" x-model="adjust.counted" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        <p class="text-[11px] text-primary/40 mt-1">Le stock sera fixé à cette valeur ; l'écart est journalisé.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Motif</label>
                        <input type="text" name="reason" maxlength="255" placeholder="Inventaire, casse, péremption…" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="adjustOpen = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Ajuster</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function stockItems(categories, suppliers, unites) {
        const storeUrl = @js(route('economat.items.store'));
        const baseUrl = @js(url('/economat/articles'));
        return {
            categories, suppliers, unites,
            open: false, editing: false, formAction: storeUrl, form: {},
            adjustOpen: false, adjustAction: '', adjust: {},
            openingOpen: false, openingAction: '', opening: {},
            autoCode: true,
            blank() {
                return { id: null, name: '', reference: '', unit: this.unites.includes('pièce') ? 'pièce' : (this.unites[0] ?? ''), description: '',
                    stock_category_id: '', supplier_id: '', min_stock: 0, average_cost: 0, is_active: true };
            },
            // L'unité d'un article mise hors service depuis reste proposée pour lui.
            get unitesProposees() {
                return this.form.unit && !this.unites.includes(this.form.unit) ? [...this.unites, this.form.unit] : this.unites;
            },
            // La référence suit le nom tant qu'elle n'a pas été saisie à la main.
            applyAutoCode() { if (this.autoCode) this.form.reference = window.suggestCode(this.form.name || ''); },
            openCreate() { this.form = this.blank(); this.autoCode = true; this.editing = false; this.formAction = storeUrl; this.open = true; },
            openEdit(item) {
                this.form = { ...this.blank(), ...item,
                    reference: item.reference ?? '', description: item.description ?? '',
                    stock_category_id: item.stock_category_id ?? '', supplier_id: item.supplier_id ?? '' };
                this.autoCode = false;
                this.editing = true; this.formAction = `${baseUrl}/${item.id}`; this.open = true;
            },
            openOpening(item) {
                this.opening = { ...item, unit_cost: item.unit_cost || '' };
                this.openingAction = `${baseUrl}/${item.id}/reprise`;
                this.openingOpen = true;
            },
            openAdjust(item) {
                this.adjust = { ...item, counted: item.current };
                this.adjustAction = `${baseUrl}/${item.id}/ajustement`;
                this.adjustOpen = true;
            },
        };
    }
</script>
@endpush
