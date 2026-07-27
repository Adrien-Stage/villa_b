@extends('layouts.hotel')

@section('title', 'Fiche technique — ' . $roomType->name)

@section('content')
@php
    $a = $sheet['assumptions'];
    $pct = $sheet['contribution_pct'];
    $tone = $pct === null ? 'text-primary/40' : ($pct >= 60 ? 'text-green-700' : ($pct >= 40 ? 'text-amber-600' : 'text-red-600'));
    $money = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp
<div class="max-w-5xl mx-auto"
     x-data="costSheet({{ Js::from($stockItems) }}, {{ Js::from($categories) }}, {{ Js::from($bases) }})">
    <a href="{{ route('rooms.cost_sheets.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Toutes les fiches
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $roomType->name }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">Fiche technique — marge sur une nuitée occupée</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Ajouter un poste
        </button>
    </div>

    @include('economat.partials.flash')

    {{-- Synthèse de marge --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Prix {{ $sheet['reference_is_realized'] ? 'réalisé (ADR)' : 'de base' }}</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $money($sheet['reference_price']) }}</p>
            @if($sheet['reference_is_realized'])
                <p class="text-[10px] text-primary/40 mt-0.5">base : {{ $money($sheet['base_price']) }} F</p>
            @else
                <p class="text-[10px] text-amber-600 mt-0.5">aucune vente encore</p>
            @endif
        </div>
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Coût variable / nuitée</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $money($sheet['variable_cost']) }}</p>
            <p class="text-[10px] text-primary/40 mt-0.5">taux de coût : {{ $sheet['cost_ratio'] ?? '—' }}%</p>
        </div>
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Marge de contribution</p>
            <p class="text-2xl font-bold {{ $tone }} mt-1">{{ $money($sheet['contribution_margin']) }}</p>
            <p class="text-[10px] text-primary/40 mt-0.5">{{ $pct ?? '—' }}% du prix</p>
        </div>
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Marge nette</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $money($sheet['net_margin']) }}</p>
            <p class="text-[10px] text-primary/40 mt-0.5">après {{ $money($sheet['fixed_cost']) }} F fixe</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Postes de coût --}}
        <div class="lg:col-span-2 bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/20">
                <h2 class="text-sm font-semibold text-primary">Postes de coût variable</h2>
            </div>

            @if(empty($sheet['groups']))
                <p class="px-5 py-10 text-center text-sm text-primary/40">Aucun poste. Ajoutez l'électricité, l'eau, les consommables…</p>
            @else
                <div class="divide-y divide-secondary/10">
                    @foreach($sheet['groups'] as $group)
                        <div class="px-5 py-3">
                            <div class="flex items-center justify-between mb-1.5">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-primary/50">{{ $group['label'] }}</h3>
                                <span class="text-xs font-semibold text-primary">{{ $money($group['subtotal']) }} F</span>
                            </div>
                            <div class="space-y-1">
                                @foreach($group['lines'] as $line)
                                    <div class="flex items-center justify-between gap-3 group py-1">
                                        <div class="min-w-0">
                                            <p class="text-sm text-primary truncate">
                                                {{ $line['label'] }}
                                                @if($line['linked'])
                                                    <span class="inline-flex items-center gap-0.5 text-[10px] text-blue-600" title="Prix tiré de l'économat">
                                                        <i data-lucide="link" class="w-2.5 h-2.5"></i>{{ $line['stock_name'] }}
                                                    </span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-primary/40">
                                                {{ rtrim(rtrim(number_format($line['quantity'], 3, ',', ' '), '0'), ',') }}
                                                × {{ $money($line['unit_cost']) }} F · {{ $line['basis_label'] }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-sm font-medium text-primary">{{ $money($line['per_night']) }} F</span>
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                                <button type="button" @click="openEdit({{ Js::from($line) }})" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/50 hover:bg-accent/20">
                                                    <i data-lucide="pencil" class="w-3 h-3"></i>
                                                </button>
                                                <form method="POST" action="{{ route('rooms.cost_sheets.items.destroy', [$roomType, $line['id']]) }}" onsubmit="return confirm('Supprimer ce poste ?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-500 hover:bg-red-50"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 border-t border-secondary/20 bg-gray-50 flex items-center justify-between">
                    <span class="text-sm font-semibold text-primary">Coût variable total</span>
                    <span class="text-sm font-bold text-red-600">{{ $money($sheet['variable_cost']) }} F / nuitée</span>
                </div>
            @endif
        </div>

        {{-- Hypothèses --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 h-fit">
            <h2 class="text-sm font-semibold text-primary mb-4">Hypothèses de calcul</h2>
            <form method="POST" action="{{ route('rooms.cost_sheets.assumptions', $roomType) }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Occupants de référence</label>
                    <input type="number" min="1" max="20" name="reference_occupants" value="{{ $a['reference_occupants'] }}"
                        class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <p class="text-[10px] text-primary/40 mt-1">Pour les postes « par personne et nuitée ». Défaut : capacité de base.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Durée moyenne de séjour (nuits)</label>
                    <input type="number" step="0.1" min="0.1" name="avg_length_of_stay" value="{{ $a['avg_nights_is_manual'] ? $a['avg_length_of_stay'] : '' }}"
                        placeholder="{{ $a['avg_length_of_stay'] }} (auto)"
                        class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <p class="text-[10px] text-primary/40 mt-1">Amortit les postes « par séjour ». Vide = calculé sur les réservations.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Charge fixe / nuitée (FCFA)</label>
                    <input type="number" min="0" name="fixed_cost_per_night" value="{{ (int) ($a['fixed_cost_per_night'] / 100) ?: '' }}"
                        placeholder="0"
                        class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <p class="text-[10px] text-primary/40 mt-1">Amortissement, personnel fixe… Optionnel, pour la marge nette.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Notes</label>
                    <textarea name="notes" rows="2" maxlength="1000" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">{{ $a['notes'] }}</textarea>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Enregistrer</button>
            </form>
        </div>
    </div>

    {{-- Modal poste de coût --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);">
        <div class="absolute inset-0" @click="open = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier le poste' : 'Nouveau poste de coût'"></h3>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Catégorie <span class="text-red-500">*</span></label>
                            <select name="category" x-model="form.category" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <template x-for="(label, key) in categories" :key="key"><option :value="key" x-text="label"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Base de calcul <span class="text-red-500">*</span></label>
                            <select name="basis" x-model="form.basis" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <template x-for="(label, key) in bases" :key="key"><option :value="key" x-text="label"></option></template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Libellé <span class="text-red-500">*</span></label>
                        <input type="text" name="label" x-model="form.label" required maxlength="160" placeholder="Ex : Électricité, Kit d'accueil, Blanchisserie draps" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>

                    {{-- Lien économat --}}
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Article de l'économat <span class="text-primary/30">(optionnel)</span></label>
                        <select name="stock_item_id" x-model="form.stock_item_id" @change="onStockChange()" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                            <option value="">— Prix saisi manuellement —</option>
                            <template x-for="it in stockItems" :key="it.id"><option :value="it.id" x-text="it.name + ' (' + fmt(it.average_cost) + ' F/' + it.unit + ')'"></option></template>
                        </select>
                        <p class="text-[10px] text-primary/40 mt-1">Si lié, le prix unitaire suit automatiquement le coût moyen pondéré de l'article.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Quantité <span class="text-red-500">*</span></label>
                            <input type="number" step="0.001" min="0" name="quantity" x-model="form.quantity" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                            <p class="text-[10px] text-primary/40 mt-1">kWh, m³, nombre d'unités…</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Prix unitaire (FCFA)</label>
                            <input type="number" min="0" name="unit_cost" x-model="form.unit_cost" :disabled="!!form.stock_item_id"
                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary disabled:bg-gray-100 disabled:text-primary/40">
                            <p class="text-[10px] text-primary/40 mt-1" x-show="!!form.stock_item_id">Fourni par l'économat.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Note</label>
                        <input type="text" name="notes" x-model="form.notes" maxlength="500" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Ajouter'"></span></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function costSheet(stockItems, categories, bases) {
        const storeUrl = @js(route('rooms.cost_sheets.items.store', $roomType));
        const baseUrl = @js(url('/hebergement/fiches-techniques/' . $roomType->id . '/postes'));
        return {
            stockItems, categories, bases,
            open: false, editing: false, formAction: storeUrl, form: {},
            fmt(c) { return new Intl.NumberFormat('fr-FR').format(Math.round(c / 100)); },
            blank() {
                return { id: null, category: Object.keys(this.categories)[0], label: '', basis: 'per_night',
                    quantity: 1, unit_cost: 0, stock_item_id: '', notes: '' };
            },
            onStockChange() {
                // Prix repris de l'économat pour l'aperçu ; le serveur refait foi.
                const it = this.stockItems.find(s => s.id === parseInt(this.form.stock_item_id));
                if (it) this.form.unit_cost = Math.round(it.average_cost / 100);
            },
            openCreate() { this.form = this.blank(); this.editing = false; this.formAction = storeUrl; this.open = true; },
            openEdit(line) {
                this.form = {
                    id: line.id, category: line.category, label: line.label, basis: line.basis,
                    quantity: line.quantity, unit_cost: line.raw_unit_cost_fcfa,
                    stock_item_id: line.stock_item_id ?? '', notes: line.notes ?? '',
                };
                this.editing = true; this.formAction = `${baseUrl}/${line.id}`; this.open = true;
            },
        };
    }
</script>
@endpush
