@extends('layouts.hotel')

@section('title', 'Dépôts de service — Économat')

@section('content')
<div class="max-w-5xl mx-auto" x-data="serviceStores()">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Dépôts de service</h1>
            <p class="text-sm text-primary/60 mt-0.5">Stocks détenus par les services hors du magasin central : étages, mini-bar, bar, pâtisserie…</p>
        </div>
        @if($canManage)
            <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouveau dépôt
            </button>
        @endif
    </div>

    @include('economat.partials.flash')

    <div class="mb-4 px-4 py-3 bg-accent/10 border border-secondary/20 text-primary/70 text-xs rounded-lg flex gap-2">
        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
        <p>
            Une demande à l'économat peut désigner un dépôt : ce qui est livré y entre en stock, sans charge.
            La consommation du dépôt — et sa charge — ressortira de son inventaire.
        </p>
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($stores->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">Aucun dépôt. Créez-en un par service qui détient du stock.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Dépôt</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Service</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur en stock</th>
                            @if($canManage)<th class="px-5 py-3"><span class="sr-only">Actions</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($stores as $store)
                            @php
                                $editPayload = [
                                    'id' => $store->id, 'name' => $store->name, 'department' => $store->department,
                                    'sort_order' => $store->sort_order, 'is_active' => $store->is_active,
                                ];
                            @endphp
                            <tr class="{{ $store->is_active ? '' : 'opacity-50' }}">
                                <td class="px-5 py-3">
                                    <a href="{{ route('economat.stores.show', $store) }}" class="font-medium text-primary hover:underline">{{ $store->name }}</a>
                                    @unless($store->is_active)<span class="ml-1 text-[10px] text-primary/50">(inactif)</span>@endunless
                                </td>
                                <td class="px-5 py-3 text-primary/70 text-xs">{{ $store->departmentLabel() }}</td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $store->stocks_count }}</td>
                                <td class="px-5 py-3 text-right font-medium text-primary">{{ number_format($store->stockValue() / 100, 0, ',', ' ') }}</td>
                                @if($canManage)
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:bg-accent/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary"
                                                aria-label="Modifier {{ $store->name }}">
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                            @if($store->stocks_count === 0)
                                                <form method="POST" action="{{ route('economat.stores.destroy', $store) }}"
                                                      onsubmit="return confirm('Supprimer le dépôt « {{ addslashes($store->name) }} » ?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600/70 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary"
                                                        aria-label="Supprimer {{ $store->name }}">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);"
         @keydown.escape.window="open = false">
        <div class="absolute inset-0" @click="open = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10" role="dialog" aria-modal="true" aria-labelledby="titre-depot">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 id="titre-depot" class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier le dépôt' : 'Nouveau dépôt'"></h3>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary" aria-label="Fermer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label for="depot-nom" class="block text-xs font-medium text-primary/70 mb-1.5">Nom <span class="text-red-500">*</span></label>
                        <input id="depot-nom" type="text" name="name" x-model="form.name" required maxlength="80" placeholder="Mini-bar, Bar piscine, Pâtisserie…"
                               class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="depot-service" class="block text-xs font-medium text-primary/70 mb-1.5">Service <span class="text-red-500">*</span></label>
                            <select id="depot-service" name="department" x-model="form.department" required
                                    class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                @foreach($departments as $cle => $libelle)
                                    <option value="{{ $cle }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-primary/45 mt-1">Ses responsables demandent pour ce dépôt ; sa consommation est portée sur ce service.</p>
                        </div>
                        <div>
                            <label for="depot-ordre" class="block text-xs font-medium text-primary/70 mb-1.5">Ordre</label>
                            <input id="depot-ordre" type="number" min="0" max="9999" name="sort_order" x-model="form.sort_order"
                                   class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                    </div>
                    <label x-show="editing" class="flex items-center gap-2.5 px-3 py-2.5 border border-secondary/30 rounded-lg cursor-pointer">
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded border-secondary/40 text-primary">
                        <span class="text-xs text-primary/80">Dépôt actif (proposé dans les demandes)</span>
                    </label>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Créer'"></span></button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function serviceStores() {
        const storeUrl = @js(route('economat.stores.store'));
        const updateUrl = @js(route('economat.stores.update', ['store' => '__ID__']));
        const vide = { id: null, name: '', department: 'housekeeping', sort_order: 0, is_active: true };

        return {
            open: false,
            editing: false,
            form: { ...vide },
            get formAction() {
                return this.editing ? updateUrl.replace('__ID__', this.form.id) : storeUrl;
            },
            openCreate() {
                this.editing = false;
                this.form = { ...vide };
                this.open = true;
            },
            openEdit(depot) {
                this.editing = true;
                this.form = { ...vide, ...depot };
                this.open = true;
            },
        };
    }
</script>
@endpush
