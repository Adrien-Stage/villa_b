@extends('layouts.hotel')

@section('title', 'Garde-manger')

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Garde-manger</h1>
        <p class="text-sm text-primary/50 mt-0.5">Inventaire restaurant (séparé de l'inventaire hôtel)</p>
    </div>

    @if($canManage)
        <div class="flex items-center gap-2">
            <button type="button"
                onclick="openCreateCategoryModal()"
                class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20">
                <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                Catégorie
            </button>
            <button type="button"
                onclick="openCreateItemModal()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                Nouvel article
            </button>
        </div>
    @endif
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold mb-1">Validation impossible :</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-2 gap-4 mb-5">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-secondary/15">
        <p class="text-2xl font-heading font-semibold text-primary">{{ $stats['total_items'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Articles suivis</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-secondary/15">
        <p class="text-2xl font-heading font-semibold text-red-600">{{ $stats['low_stock'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Stocks bas</p>
    </div>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('restaurant.pantry.index', array_merge(request()->except('low','page'), [])) }}"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('low') ? 'bg-white text-primary/60 hover:text-primary border border-secondary/30' : 'bg-primary text-white' }}">
            Tous
        </a>
        <a href="{{ route('restaurant.pantry.index', array_merge(request()->except('low','page'), ['low' => 1])) }}"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('low') ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
            Stock bas
        </a>
    </div>

    <form method="GET" action="{{ route('restaurant.pantry.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="low" value="{{ request('low') }}">

        <select name="category" onchange="this.form.submit()"
            class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Toutes les catégories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>

        <select name="status" onchange="this.form.submit()"
            class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous</option>
            <option value="active" @selected(request('status') === 'active')>Actifs</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
        </select>

        <div class="relative">
            <input type="text"
                id="search-input"
                name="search"
                value="{{ request('search') }}"
                placeholder="Rechercher..."
                autocomplete="off"
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] gap-4">
    <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15">
            <p class="font-heading text-sm font-semibold text-primary">Stocks</p>
        </div>

        @if($items->isEmpty())
            <div class="py-16 text-center text-primary/35">
                <i data-lucide="package" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                <p class="text-sm font-medium">Aucun article</p>
                <p class="text-xs mt-1">Ajoute des articles au garde-manger pour suivre le stock.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-secondary/10">
                    <thead class="bg-accent/20">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Article</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Catégorie</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Stock</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Min</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($items as $item)
                            @php
                                $isLow = (float) $item->current_stock <= (float) $item->min_stock;
                            @endphp
                            <tr class="{{ $item->is_active ? '' : 'opacity-60' }} {{ $isLow ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-primary">{{ $item->name }}</p>
                                    <p class="text-xs text-primary/45 mt-0.5">
                                        {{ strtoupper($item->unit) }}
                                        @if($isLow) · <span class="text-red-700 font-semibold">Stock bas</span> @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-sm text-primary/70">
                                    {{ $item->category?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-primary">
                                    {{ rtrim(rtrim(number_format((float) $item->current_stock, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-primary/70">
                                    {{ rtrim(rtrim(number_format((float) $item->min_stock, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @role('restaurant_chief', 'restaurant_staff')
                                        <button type="button"
                                            onclick="openMovementModal({{ $item->id }}, 'in')"
                                            class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700">
                                            Entrée
                                        </button>
                                        <button type="button"
                                            onclick="openMovementModal({{ $item->id }}, 'out')"
                                            class="px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-surface-dark">
                                            Sortie
                                        </button>
                                        @endrole
                                        @if($canManage)
                                            <button type="button"
                                                onclick="openMovementModal({{ $item->id }}, 'adjust')"
                                                class="px-3 py-1.5 rounded-lg border border-secondary/25 bg-white text-primary text-xs font-semibold hover:bg-accent/20">
                                                Ajuster
                                            </button>
                                            <button type="button"
                                                onclick="openEditItemModal({{ $item->id }})"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-4 border-t border-secondary/15">
                {{ $items->links() }}
            </div>
        @endif
    </section>

    <aside class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15">
            <p class="font-heading text-sm font-semibold text-primary">Derniers mouvements</p>
        </div>
        <div class="divide-y divide-secondary/10">
            @forelse($recentMovements as $move)
                <div class="px-4 py-3">
                    <p class="text-sm font-semibold text-primary truncate">{{ $move->item?->name ?? 'Article' }}</p>
                    <p class="text-xs text-primary/45 mt-0.5">
                        {{ strtoupper($move->type) }}
                        · {{ rtrim(rtrim(number_format((float) $move->quantity, 3, '.', ''), '0'), '.') }}
                        · {{ strtoupper($move->reason) }}
                        · {{ $move->occurred_at?->format('d/m H:i') }}
                    </p>
                    @if($move->notes)
                        <p class="text-xs text-primary/55 mt-1">{{ $move->notes }}</p>
                    @endif
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-primary/45">
                    Aucun mouvement enregistré.
                </div>
            @endforelse
        </div>
    </aside>
</div>

{{-- Movement modal --}}
<x-modal id="movement-modal" title="Mouvement" title-id="movement-title" max-width="max-w-xl" formAction="#" closeAction="closeMovementModal()">
    <x-slot:form-attributes>
        id="movement-form"
    </x-slot:form-attributes>
    
    <input type="hidden" name="type" id="movement-type">

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-xs text-primary/60">Quantité</label>
            <input type="number" name="quantity" step="0.001" min="0.001" required
                class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label class="text-xs text-primary/60">Raison</label>
            <select name="reason" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                @foreach($moveReasons as $reason)
                    <option value="{{ $reason }}">{{ strtoupper($reason) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="text-xs text-primary/60">Notes (optionnel)</label>
        <textarea name="notes" rows="2" maxlength="2000"
            class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none"></textarea>
    </div>

    <x-slot:footer>
        <button type="button" onclick="closeMovementModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
    </x-slot:footer>
</x-modal>

@if($canManage)
    {{-- Create category modal --}}
    <x-modal id="create-category-modal" title="Nouvelle categorie" formAction="{{ route('restaurant.pantry.categories.store') }}" closeAction="closeCreateCategoryModal()">
        <input type="hidden" name="form_type" value="create_category">

        <div>
            <label class="text-xs text-primary/60">Nom</label>
            <input type="text" name="name" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-primary/60">Ordre</label>
                <input type="number" name="sort_order" min="0" value="0" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <label class="inline-flex items-center gap-2 text-xs text-primary/70 mt-6">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>
        </div>

        <x-slot:footer>
            <button type="button" onclick="closeCreateCategoryModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Creer</button>
        </x-slot:footer>
    </x-modal>

    {{-- Create item modal --}}
    <x-modal id="create-item-modal" title="Nouvel article" max-width="max-w-2xl" formAction="{{ route('restaurant.pantry.items.store') }}" closeAction="closeCreateItemModal()">
        <input type="hidden" name="form_type" value="create_item">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-primary/60">Nom</label>
                <input type="text" name="name" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <div>
                <label class="text-xs text-primary/60">Categorie</label>
                <select name="restaurant_pantry_category_id" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                    <option value="">Aucune</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="text-xs text-primary/60">Unité</label>
                <select name="unit" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                    @foreach($units as $unit)
                        <option value="{{ $unit }}">{{ strtoupper($unit) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-primary/60">Stock min</label>
                <input type="number" step="0.001" min="0" name="min_stock" value="0" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <div>
                <label class="text-xs text-primary/60">Prix achat (FCFA)</label>
                <input type="number" min="0" name="cost_price" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
        </div>

        <label class="inline-flex items-center gap-2 text-xs text-primary/70">
            <input type="checkbox" name="is_active" value="1" checked>
            Actif
        </label>

        <x-slot:footer>
            <button type="button" onclick="closeCreateItemModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Creer</button>
        </x-slot:footer>
    </x-modal>

    {{-- Edit item modals --}}
    @foreach($items as $item)
        <x-modal id="edit-item-modal-{{ $item->id }}" title="Modifier {{ $item->name }}" max-width="max-w-2xl" formAction="{{ route('restaurant.pantry.items.update', $item) }}" closeAction="closeEditItemModal({{ $item->id }})">
            @method('PUT')
            <input type="hidden" name="form_type" value="edit_item_{{ $item->id }}">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-primary/60">Nom</label>
                    <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                </div>
                <div>
                    <label class="text-xs text-primary/60">Categorie</label>
                    <select name="restaurant_pantry_category_id" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                        <option value="">Aucune</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $item->restaurant_pantry_category_id === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="text-xs text-primary/60">Unité</label>
                    <select name="unit" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                        @foreach($units as $unit)
                            <option value="{{ $unit }}" @selected($item->unit === $unit)>{{ strtoupper($unit) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-primary/60">Stock min</label>
                    <input type="number" step="0.001" min="0" name="min_stock" value="{{ (float) $item->min_stock }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                </div>
                <div>
                    <label class="text-xs text-primary/60">Prix achat (FCFA)</label>
                    <input type="number" min="0" name="cost_price" value="{{ $item->cost_price ? (int) ($item->cost_price / 100) : '' }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                <input type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                Actif
            </label>

            <x-slot:footer>
                <button type="button" onclick="closeEditItemModal({{ $item->id }})" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endif

<script>
let searchTimer;
const searchInput = document.getElementById('search-input');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => this.closest('form').submit(), 400);
    });
}

window.openMovementModal = function(itemId, type) {
    const modal = document.getElementById('movement-modal');
    const form = document.getElementById('movement-form');
    const title = document.getElementById('movement-title');
    const typeInput = document.getElementById('movement-type');
    if (!modal || !form || !typeInput) return;

    form.action = `{{ url('/restaurant/pantry/items') }}/${itemId}/movements`;
    typeInput.value = type;
    if (title) {
        title.textContent = type === 'in' ? 'Entrée de stock' : (type === 'out' ? 'Sortie de stock' : 'Ajustement de stock');
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeMovementModal = function() {
    const modal = document.getElementById('movement-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openCreateCategoryModal = function() {
    document.getElementById('create-category-modal')?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.closeCreateCategoryModal = function() {
    document.getElementById('create-category-modal')?.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openCreateItemModal = function() {
    document.getElementById('create-item-modal')?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.closeCreateItemModal = function() {
    document.getElementById('create-item-modal')?.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openEditItemModal = function(itemId) {
    document.getElementById(`edit-item-modal-${itemId}`)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.closeEditItemModal = function(itemId) {
    document.getElementById(`edit-item-modal-${itemId}`)?.classList.add('hidden');
    document.body.style.overflow = '';
};
</script>
@endsection

