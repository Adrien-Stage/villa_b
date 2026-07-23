@extends('layouts.hotel')

@section('title', 'Articles — Économat')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="stockItems({{ Js::from($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) }}, {{ Js::from($suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()) }})">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Articles</h1>
            <p class="text-sm text-primary/60 mt-0.5">Catalogue du magasin central et niveaux de stock.</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvel article
        </button>
    </div>

    @include('economat.partials.flash')

    <div class="flex gap-2 mb-4">
        <a href="{{ route('economat.items.index') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ !$filter ? 'bg-primary text-white border-primary' : 'border-secondary/30 text-primary/60 hover:bg-accent/10' }}">Tous</a>
        <a href="{{ route('economat.items.index', ['filter' => 'alert']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $filter === 'alert' ? 'bg-amber-500 text-white border-amber-500' : 'border-secondary/30 text-primary/60 hover:bg-accent/10' }}">Sous le seuil</a>
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($items->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">
                @if($filter === 'alert') Aucun article sous le seuil. @else Aucun article. Créez-en un pour démarrer le magasin. @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Catégorie</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Coût moyen</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($items as $item)
                            @php
                                $level = $item->stockLevel();
                                // Payloads précalculés : un @json multi-clés inline dans un
                                // attribut @click casse le compilateur Blade.
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
                            @endphp
                            <tr class="{{ $item->is_active ? '' : 'opacity-50' }}">
                                <td class="px-5 py-3">
                                    <a href="{{ route('economat.items.show', $item) }}" class="font-medium text-primary hover:underline">{{ $item->name }}</a>
                                    @if($item->reference)<span class="block text-[10px] font-mono text-primary/40">{{ $item->reference }}</span>@endif
                                </td>
                                <td class="px-5 py-3 text-primary/60 text-xs">{{ $item->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full {{ $level === 'out' ? 'bg-red-500' : ($level === 'low' ? 'bg-amber-500' : 'bg-green-500') }}"></span>
                                        <span class="font-medium text-primary">{{ rtrim(rtrim(number_format($item->current_stock, 3, ',', ' '), '0'), ',') }}</span>
                                        <span class="text-primary/40 text-xs">{{ $item->unit }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ number_format($item->average_cost / 100, 0, ',', ' ') }}</td>
                                <td class="px-5 py-3 text-right font-medium text-primary">{{ number_format($item->stockValue() / 100, 0, ',', ' ') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="openAdjust({{ Js::from($adjustPayload) }})"
                                            class="h-8 px-2.5 inline-flex items-center gap-1 rounded-lg border border-secondary/20 text-primary/60 hover:bg-accent/20 text-xs" title="Ajuster le stock">
                                            <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:bg-accent/20">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

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
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Unité <span class="text-red-500">*</span></label>
                            <input type="text" name="unit" x-model="form.unit" required maxlength="20" placeholder="kg, litre, pièce…" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Référence</label>
                            <input type="text" name="reference" x-model="form.reference" @input="autoCode = false" maxlength="60" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
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
                    <div class="grid grid-cols-2 gap-4">
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
                    <p class="text-[11px] text-primary/40" x-show="!editing">Le stock démarre à 0 : utilisez « Ajuster » ou une réception de bon pour l'alimenter, afin que toute quantité ait une trace.</p>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Créer'"></span></button>
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
    function stockItems(categories, suppliers) {
        const storeUrl = @js(route('economat.items.store'));
        const baseUrl = @js(url('/economat/articles'));
        return {
            categories, suppliers,
            open: false, editing: false, formAction: storeUrl, form: {},
            adjustOpen: false, adjustAction: '', adjust: {},
            autoCode: true,
            blank() {
                return { id: null, name: '', reference: '', unit: 'pièce', description: '',
                    stock_category_id: '', supplier_id: '', min_stock: 0, average_cost: 0, is_active: true };
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
            openAdjust(item) {
                this.adjust = { ...item, counted: item.current };
                this.adjustAction = `${baseUrl}/${item.id}/ajustement`;
                this.adjustOpen = true;
            },
        };
    }
</script>
@endpush
