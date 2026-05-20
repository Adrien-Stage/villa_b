@extends('layouts.hotel')

@section('title', 'Menus')

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Menus</h1>
        <p class="text-sm text-primary/50 mt-0.5">Gestion des categories et des articles du restaurant</p>
    </div>

    @if($canManage)
        <button type="button"
            onclick="openCreateItemModal()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            Ajouter un article
        </button>
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

<div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4">
    <aside class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="font-heading text-sm font-semibold text-primary">Categories</p>
                <p class="text-xs text-primary/45 mt-0.5">Classement du menu</p>
            </div>
            @if($canManage)
                <button type="button"
                    onclick="openCreateCategoryModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent/30 text-primary text-xs font-semibold hover:bg-accent/40">
                    <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                    Ajouter
                </button>
            @endif
        </div>

        <div class="divide-y divide-secondary/10">
            @forelse($categories as $category)
                <div class="px-4 py-3 flex items-center justify-between gap-3 {{ $category->is_active ? '' : 'opacity-60' }}">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary truncate">{{ $category->name }}</p>
                        <p class="text-xs text-primary/45 mt-0.5">
                            {{ $category->items_count }} article{{ $category->items_count > 1 ? 's' : '' }}
                            @if(!$category->is_active) · Inactive @endif
                        </p>
                    </div>
                    @if($canManage)
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button"
                                onclick="openEditCategoryModal({{ $category->id }})"
                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" action="{{ route('restaurant.menus.categories.destroy', $category) }}"
                                onsubmit="return confirm('Supprimer cette categorie ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-primary/45">
                    Aucune categorie. Cree ta premiere categorie.
                </div>
            @endforelse
        </div>
    </aside>

    <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
                <p class="font-heading text-sm font-semibold text-primary">Articles</p>
                <p class="text-xs text-primary/45 mt-0.5">Plats, boissons et autres articles</p>
            </div>

            <form method="GET" action="{{ route('restaurant.menus.index') }}" class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <input type="text"
                        id="search-input"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Rechercher un article..."
                        autocomplete="off"
                        class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
                </div>

                <select name="category" onchange="this.form.submit()"
                    class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <option value="">Toutes les categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>

                <select name="type" onchange="this.form.submit()"
                    class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <option value="">Tous les types</option>
                    @foreach($itemTypes as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ strtoupper($type) }}</option>
                    @endforeach
                </select>

                <select name="status" onchange="this.form.submit()"
                    class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    <option value="">Tous</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
                </select>
            </form>
        </div>

        @if($items->isEmpty())
            <div class="py-16 text-center text-primary/35">
                <i data-lucide="book" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                <p class="text-sm font-medium">Aucun article</p>
                <p class="text-xs mt-1">Commence par creer un article de menu.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-secondary/10">
                    <thead class="bg-accent/20">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Article</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Categorie</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Type</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Prix</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Statut</th>
                            @if($canManage)
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($items as $item)
                            <tr class="{{ $item->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-primary">{{ $item->name }}</p>
                                    @if($item->description)
                                        <p class="text-xs text-primary/45 mt-0.5 truncate">{{ $item->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-primary/70">
                                    {{ $item->category?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-accent/30 text-primary">
                                        {{ strtoupper($item->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-primary">
                                    {{ number_format($item->price / 100, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $item->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                        {{ $item->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                @if($canManage)
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                onclick="openEditItemModal({{ $item->id }})"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <form method="POST" action="{{ route('restaurant.menus.items.destroy', $item) }}"
                                                onsubmit="return confirm('Supprimer cet article ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
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
</div>

@if($canManage)
    {{-- Create category modal --}}
    <div id="create-category-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" onclick="closeCreateCategoryModal()"></div>
        <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading text-lg text-primary">Nouvelle categorie</h2>
                <button type="button" onclick="closeCreateCategoryModal()" class="text-primary/50 hover:text-primary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('restaurant.menus.categories.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="form_type" value="create_category">

                <div>
                    <label class="text-xs text-primary/60">Nom</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-primary/60">Ordre</label>
                        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-primary/70 mt-6">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        Active
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="closeCreateCategoryModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Creer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit category modals --}}
    @foreach($categories as $category)
        <div id="edit-category-modal-{{ $category->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" onclick="closeEditCategoryModal({{ $category->id }})"></div>
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading text-lg text-primary">Modifier {{ $category->name }}</h2>
                    <button type="button" onclick="closeEditCategoryModal({{ $category->id }})" class="text-primary/50 hover:text-primary">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('restaurant.menus.categories.update', $category) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_type" value="edit_category_{{ $category->id }}">

                    <div>
                        <label class="text-xs text-primary/60">Nom</label>
                        <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-primary/60">Ordre</label>
                            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-primary/70 mt-6">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                            Active
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick="closeEditCategoryModal({{ $category->id }})" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    {{-- Create item modal --}}
    <div id="create-item-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" onclick="closeCreateItemModal()"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading text-lg text-primary">Nouvel article</h2>
                <button type="button" onclick="closeCreateItemModal()" class="text-primary/50 hover:text-primary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('restaurant.menus.items.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="form_type" value="create_item">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-primary/60">Nom</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                    </div>
                    <div>
                        <label class="text-xs text-primary/60">Categorie</label>
                        <select name="restaurant_menu_category_id" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                            <option value="">Aucune</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('restaurant_menu_category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs text-primary/60">Prix (FCFA)</label>
                        <input type="number" name="price" min="0" value="{{ old('price', 0) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                    </div>
                    <div>
                        <label class="text-xs text-primary/60">Type</label>
                        <select name="type" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                            @foreach($itemTypes as $type)
                                <option value="{{ $type }}" @selected(old('type', 'food') === $type)>{{ strtoupper($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-primary/60">Ordre</label>
                        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                    </div>
                </div>

                <div>
                    <label class="text-xs text-primary/60">Description (optionnel)</label>
                    <textarea name="description" rows="3" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">{{ old('description') }}</textarea>
                </div>

                <div x-data="singleImagePreview()">
                    <label class="text-xs text-primary/60">Image (optionnel)</label>
                    <div @click="$refs.fileInput.click()" @dragover.prevent="isDragging=true" @dragleave.prevent="isDragging=false" @drop.prevent="handleDrop($event)" :class="isDragging ? 'border-primary bg-primary/5' : 'border-secondary/30 hover:border-primary/40'" class="mt-1 border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all bg-white" x-show="!preview">
                        <i data-lucide="image-plus" class="w-6 h-6 mx-auto text-primary/30 mb-1"></i>
                        <p class="text-xs text-primary/50">Cliquez ou glissez · JPG, PNG, WebP · Max 2 Mo</p>
                    </div>
                    <input type="file" name="image" x-ref="fileInput" @change="handleFile($event)" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">
                    
                    <div x-show="preview" style="display: none;" class="mt-2 relative w-32 h-32 rounded-lg overflow-hidden border border-secondary/20 shadow-sm group">
                        <img :src="preview" class="w-full h-full object-cover">
                        <button type="button" @click="removeImage()" class="absolute top-1.5 right-1.5 w-6 h-6 bg-black/50 hover:bg-red-500 text-white rounded-full flex items-center justify-center transition-colors shadow-sm opacity-0 group-hover:opacity-100">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    Actif
                </label>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="closeCreateItemModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Creer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit item modals --}}
    @foreach($items as $item)
        <div id="edit-item-modal-{{ $item->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" onclick="closeEditItemModal({{ $item->id }})"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading text-lg text-primary">Modifier {{ $item->name }}</h2>
                    <button type="button" onclick="closeEditItemModal({{ $item->id }})" class="text-primary/50 hover:text-primary">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('restaurant.menus.items.update', $item) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_type" value="edit_item_{{ $item->id }}">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-primary/60">Nom</label>
                            <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-primary/60">Categorie</label>
                            <select name="restaurant_menu_category_id" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                                <option value="">Aucune</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('restaurant_menu_category_id', $item->restaurant_menu_category_id) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs text-primary/60">Prix (FCFA)</label>
                            <input type="number" name="price" min="0" value="{{ old('price', (int) ($item->price / 100)) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-primary/60">Type</label>
                            <select name="type" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                                @foreach($itemTypes as $type)
                                    <option value="{{ $type }}" @selected(old('type', $item->type) === $type)>{{ strtoupper($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-primary/60">Ordre</label>
                            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-primary/60">Description (optionnel)</label>
                        <textarea name="description" rows="3" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">{{ old('description', $item->description) }}</textarea>
                    </div>

                    <div x-data="singleImagePreview('{{ $item->image_path ? Storage::url($item->image_path) : '' }}')" :id="$id('image-preview')">
                        <label class="text-xs text-primary/60">Image (optionnel)</label>
                        <div @click="$refs.fileInput.click()" @dragover.prevent="isDragging=true" @dragleave.prevent="isDragging=false" @drop.prevent="handleDrop($event)" :class="isDragging ? 'border-primary bg-primary/5' : 'border-secondary/30 hover:border-primary/40'" class="mt-1 border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all bg-white" x-show="!preview">
                            <i data-lucide="image-plus" class="w-6 h-6 mx-auto text-primary/30 mb-1"></i>
                            <p class="text-xs text-primary/50">Cliquez ou glissez · JPG, PNG, WebP · Max 2 Mo</p>
                        </div>
                        <input type="file" name="image" x-ref="fileInput" @change="handleFile($event)" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">
                        <input type="hidden" name="remove_image" :id="'remove-image-flag-' + $id('image-preview')" value="0">
                        
                        <div x-show="preview" style="display: none;" class="mt-2 relative w-32 h-32 rounded-lg overflow-hidden border border-secondary/20 shadow-sm group">
                            <img :src="preview" class="w-full h-full object-cover">
                            <button type="button" @click="removeImage()" class="absolute top-1.5 right-1.5 w-6 h-6 bg-black/50 hover:bg-red-500 text-white rounded-full flex items-center justify-center transition-colors shadow-sm opacity-0 group-hover:opacity-100">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                        Actif
                    </label>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick="closeEditItemModal({{ $item->id }})" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
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

window.openCreateCategoryModal = function() {
    const modal = document.getElementById('create-category-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeCreateCategoryModal = function() {
    const modal = document.getElementById('create-category-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openEditCategoryModal = function(categoryId) {
    const modal = document.getElementById(`edit-category-modal-${categoryId}`);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeEditCategoryModal = function(categoryId) {
    const modal = document.getElementById(`edit-category-modal-${categoryId}`);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openCreateItemModal = function() {
    const modal = document.getElementById('create-item-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeCreateItemModal = function() {
    const modal = document.getElementById('create-item-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openEditItemModal = function(itemId) {
    const modal = document.getElementById(`edit-item-modal-${itemId}`);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeEditItemModal = function(itemId) {
    const modal = document.getElementById(`edit-item-modal-${itemId}`);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

@if($errors->any())
    @if(old('form_type') === 'create_category')
        openCreateCategoryModal();
    @elseif(old('form_type') === 'create_item')
        openCreateItemModal();
    @elseif(old('form_type') && str_starts_with(old('form_type'), 'edit_category_'))
        openEditCategoryModal('{{ str_replace('edit_category_', '', old('form_type')) }}');
    @elseif(old('form_type') && str_starts_with(old('form_type'), 'edit_item_'))
        openEditItemModal('{{ str_replace('edit_item_', '', old('form_type')) }}');
    @endif
@endif

document.addEventListener('alpine:init', () => {
    Alpine.data('singleImagePreview', (initialPreview = null) => ({
        preview: initialPreview,
        isDragging: false,

        handleFile(event) {
            const file = event.target.files[0];
            if (file) this.processFile(file);
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                this.processFile(file);
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.fileInput.files = dt.files;
            }
        },

        processFile(file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('Image trop volumineuse (max 2 Mo)');
                return;
            }
            this.preview = URL.createObjectURL(file);
            this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
        },

        removeImage() {
            this.preview = null;
            this.$refs.fileInput.value = '';
            const removeInput = document.getElementById('remove-image-flag-' + this.$id('image-preview'));
            if(removeInput) removeInput.value = '1';
        }
    }));
});
</script>
@endsection
