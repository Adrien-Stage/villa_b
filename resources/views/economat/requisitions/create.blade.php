@extends('layouts.hotel')

@section('title', 'Nouveau bon de réquisition — Économat')

@section('content')
<div class="max-w-4xl mx-auto"
     x-data="requisitionForm({{ Js::from($items->map(fn($i) => [
         'id'       => $i->id,
         'name'     => $i->name,
         'unit'     => $i->unit,
         'stock'    => (float) $i->current_stock,
         'category' => $i->category?->name ?? 'Général',
         'price'    => (int) ($i->average_cost ?: $i->last_purchase_price ?: 0),
     ])->values()) }})">

    <div class="flex items-center justify-between gap-4 mb-4">
        <a href="{{ route('economat.requisitions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Retour à la liste des bons</span>
        </a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
            <i data-lucide="file-plus" class="w-6 h-6 text-primary"></i>
            <span>Émission d'un Bon de Réquisition interne</span>
        </h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Sollicitation d'articles auprès de l'économat : denrées alimentaires, produits consommables, linge, matériels et fournitures de bureau.
        </p>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.requisitions.store') }}">
        @csrf

        {{-- Cadre Informations du Bon --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-5 shadow-sm">
            <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Service émetteur & Motif</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
                 x-data="{ service: @js(old('department', array_key_first($departments))), depots: @js($stores), depot: @js(old('service_store_id', '')) }">
                <div>
                    <label for="requisition-service" class="block text-xs font-semibold text-primary/70 mb-1.5">
                        Service émetteur <span class="text-red-500">*</span>
                    </label>
                    <select id="requisition-service" name="department" required x-model="service" @change="depot = ''"
                        class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        @foreach($departments as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-primary/50 mt-1">Sélectionnez le service rattaché à ce besoin.</p>
                </div>

                <div x-show="depots.some(d => d.department === service)" x-cloak>
                    <label for="requisition-depot" class="block text-xs font-semibold text-primary/70 mb-1.5">Dépôt destinataire</label>
                    <select id="requisition-depot" name="service_store_id" x-model="depot"
                        class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        <option value="">Aucun dépôt</option>
                        <template x-for="d in depots.filter(d => d.department === service)" :key="d.id">
                            <option :value="d.id" x-text="d.name" :selected="String(d.id) === String(depot)"></option>
                        </template>
                    </select>
                    <p class="text-[10px] text-primary/50 mt-1">Les articles entreront dans le stock de ce dépôt et seront comptés à son inventaire.</p>
                </div>

                {{-- Chaque restaurant a sa cuisine : la livraison entre dans le garde-manger de celui qui la demande. --}}
                @if($restaurants->count() > 1)
                    <div x-show="service === 'restaurant' && !depot" x-cloak>
                        <label for="requisition-restaurant" class="block text-xs font-semibold text-primary/70 mb-1.5">Restaurant destinataire</label>
                        <select id="requisition-restaurant" name="point_of_sale_id"
                            class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            @foreach($restaurants as $restaurant)
                                <option value="{{ $restaurant->id }}" @selected((int) old('point_of_sale_id', $restaurantParDefaut?->id) === $restaurant->id)>{{ $restaurant->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-primary/50 mt-1">Les articles entreront dans le garde-manger de ce restaurant.</p>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Motif / Justification du besoin</label>
                    <input type="text" name="purpose" maxlength="500"
                        placeholder="Ex : Réassort fournitures bureau compta, réassort accueil chambres..."
                        class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    <p class="text-[10px] text-primary/50 mt-1">Précisez la destination ou l'opération concernée.</p>
                </div>
            </div>
        </div>

        {{-- Cadre Sélection des Articles --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-5 shadow-sm">
            <div class="px-5 py-3 border-b border-secondary/20 bg-surface-light flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-primary">Articles sollicités</h2>
                    <span class="text-xs text-primary/50" x-text="`(${lines.length} ligne(s))`"></span>
                </div>
                <button type="button" @click="addLine()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary text-xs font-semibold rounded-lg transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Ajouter une ligne</span>
                </button>
            </div>

            <div class="p-5">
                <template x-if="lines.length === 0">
                    <p class="text-xs text-primary/40 text-center py-6">Aucun article sélectionné. Cliquez sur « Ajouter une ligne » ci-dessus.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(line, idx) in lines" :key="line.key">
                        <div class="grid grid-cols-12 gap-3 items-center p-3 rounded-lg border border-secondary/15 bg-surface-light/30">
                            {{-- Sélection de l'article --}}
                            <div class="col-span-12 md:col-span-6">
                                <label class="block text-[10px] font-semibold uppercase text-primary/50 mb-1">Désignation de l'article</label>
                                <select :name="`lines[${idx}][stock_item_id]`" x-model.number="line.itemId" required
                                    class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                    <option value="">Sélectionner un article...</option>
                                    <template x-for="it in items" :key="it.id">
                                        <option :value="it.id" x-text="`[${it.category}] ${it.name} (${formatStock(it.stock)} ${it.unit} dispo)`"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Quantité demandée --}}
                            <div class="col-span-6 md:col-span-3">
                                <label class="block text-[10px] font-semibold uppercase text-primary/50 mb-1">Qté demandée</label>
                                <div class="inline-flex items-center w-full">
                                    <input type="number" step="0.001" min="0.001" :name="`lines[${idx}][quantity]`"
                                        x-model.number="line.qty" placeholder="Quantité" required
                                        class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary font-mono font-bold text-right outline-none focus:border-primary">
                                    <span class="ml-2 text-xs text-primary/60 font-medium" x-text="getItemUnit(line.itemId)"></span>
                                </div>
                            </div>

                            {{-- Estimation financière --}}
                            <div class="col-span-4 md:col-span-2 text-right">
                                <label class="block text-[10px] font-semibold uppercase text-primary/50 mb-1">Montant estimé</label>
                                <span class="font-mono text-xs font-bold text-primary block py-1.5" x-text="formatMoney(getLineTotal(line))"></span>
                            </div>

                            {{-- Suppression --}}
                            <div class="col-span-2 md:col-span-1 text-right">
                                <label class="block text-[10px] text-transparent mb-1">Action</label>
                                <button type="button" @click="removeLine(idx)"
                                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Supprimer la ligne">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Total de la demande --}}
            <div class="px-5 py-3 border-t border-secondary/20 bg-surface-light flex items-center justify-between text-xs">
                <span class="text-primary/70">Budget estimé du bon :</span>
                <strong class="font-mono text-sm text-primary" x-text="formatMoney(getTotal())"></strong>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('economat.requisitions.index') }}"
                class="px-4 py-2 text-xs font-semibold text-primary/60 hover:text-primary transition-colors">
                Annuler
            </a>
            <button type="submit" :disabled="lines.length === 0"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i data-lucide="send" class="w-4 h-4"></i>
                <span>Transmettre le bon à l'économat</span>
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function requisitionForm(items) {
        return {
            items,
            lines: [],
            nextKey: 1,
            addLine() {
                this.lines.push({ key: this.nextKey++, itemId: '', qty: 1 });
            },
            removeLine(idx) {
                if (this.lines.length > 1) {
                    this.lines.splice(idx, 1);
                }
            },
            getItem(id) {
                return this.items.find(i => i.id === id);
            },
            getItemUnit(id) {
                const it = this.getItem(id);
                return it ? it.unit : '';
            },
            getLineTotal(line) {
                const it = this.getItem(line.itemId);
                if (!it || !line.qty) return 0;
                return Math.round(line.qty * it.price);
            },
            getTotal() {
                return this.lines.reduce((sum, l) => sum + this.getLineTotal(l), 0);
            },
            formatStock(v) {
                return new Intl.NumberFormat('fr-FR').format(v);
            },
            formatMoney(centimes) {
                const val = Math.round(centimes / 100);
                return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " FCFA";
            },
            init() {
                this.addLine();
            },
        };
    }
</script>
@endpush
