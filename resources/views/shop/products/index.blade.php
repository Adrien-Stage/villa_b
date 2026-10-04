@extends('layouts.hotel')

@section('title', 'Articles Boutique')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Articles Boutique</h1>
            <p class="text-secondary mt-1">Gestion des articles culturels</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('shop.products.export') }}" class="inline-flex items-center gap-2 px-3 py-3 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Exporter les articles en CSV">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </a>
            @droit('shop.products.import')
                <button type="button" onclick="document.getElementById('modal-import-products').classList.remove('hidden')" class="inline-flex items-center gap-2 px-3 py-3 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Importer des articles depuis un CSV">
                    <i data-lucide="upload" class="w-4 h-4"></i> Importer
                </button>
            @enddroit
            @droit('shop.products.creer')
            <a href="{{ route('shop.products.create') }}"
               class="bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i> Nouvel article
            </a>
            @enddroit
        </div>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif
    @if ($message = session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
            <i data-lucide="alert-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif
    <x-csv-import-errors />

    {{-- Barre outils --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Badge Toutes les catégories --}}
            <a href="{{ route('shop.products.index', request()->except(['category', 'page'])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ !request('category')
                          ? 'bg-primary text-white'
                          : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                Toutes
            </a>
            {{-- Badges par catégorie --}}
            @foreach ($categories as $cat)
                <a href="{{ route('shop.products.index', array_merge(request()->except(['category', 'page']), ['category' => $cat->id])) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                          {{ request('category') == $cat->id
                              ? 'bg-primary text-white'
                              : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            {{-- Badges statut --}}
            @php
                $statuses = [
                    '' => 'Tous',
                    'active' => 'Actif',
                    'inactive' => 'Inactif',
                ];
            @endphp
            @foreach($statuses as $value => $label)
                <a href="{{ route('shop.products.index', array_merge(request()->except(['status', 'page']), $value ? ['status' => $value] : [])) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                          {{ request('status', '') === $value
                              ? 'bg-primary text-white'
                              : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                    {{ $label }}
                </a>
            @endforeach

            {{-- Recherche --}}
            <form id="search-form" method="GET" action="{{ route('shop.products.index') }}" class="relative ml-2">
                <input type="hidden" name="category" value="{{ request('category') }}">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="text"
                       id="search-input"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Nom, SKU..."
                       autocomplete="off"
                       class="pl-9 pr-8 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-52 transition-all">
                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
                <span id="search-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-3.5 w-3.5 text-primary/30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
            </form>

            {{-- Toggle Vue --}}
            <div class="flex items-center bg-white border border-secondary/30 rounded-lg overflow-hidden ml-2">
                <a href="{{ route('shop.products.index', array_merge(request()->query(), ['view' => 'list'])) }}"
                   class="px-3 py-2 transition-colors {{ $view === 'list' ? 'bg-primary text-white' : 'text-primary/40 hover:text-primary' }}"
                   title="Vue liste">
                    <i data-lucide="list" class="w-4 h-4"></i>
                </a>
                <a href="{{ route('shop.products.index', array_merge(request()->query(), ['view' => 'card'])) }}"
                   class="px-3 py-2 transition-colors {{ $view === 'card' ? 'bg-primary text-white' : 'text-primary/40 hover:text-primary' }}"
                   title="Vue carte">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- ===== VUE LISTE ===== --}}
    @if($view === 'list')
    <x-table :rows="$products" empty="Aucun article trouvé." empty-icon="box" caption="Articles de la boutique">
        <x-slot:head>
            <x-table.col>Article</x-table.col>
            <x-table.col hide="xl">Catégorie</x-table.col>
            <x-table.col align="right">Prix</x-table.col>
            <x-table.col>Stock</x-table.col>
            <x-table.col hide="lg">Statut</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($products as $product)
            <x-table.row :muted="! $product->is_active">
                <x-table.cell>
                    <div class="flex items-center gap-3">
                        @if($product->image_path)
                            <img src="{{ asset('storage/' . $product->image_path) }}" alt="" class="h-10 w-10 shrink-0 rounded-full border-2 border-secondary/20 object-cover">
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-secondary/10 bg-primary/10" aria-hidden="true">
                                <i data-lucide="package" class="h-5 w-5 text-primary/40"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('shop.products.edit', $product) }}" class="block truncate font-medium text-primary hover:underline">{{ $product->name }}</a>
                            <p class="text-xs text-primary/50">{{ $product->sku }}</p>
                        </div>
                    </div>
                </x-table.cell>
                <x-table.cell hide="xl" class="text-primary/70">{{ $product->category->name }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-medium">{{ number_format($product->price / 100, 0, ',', ' ') }} FCFA</x-table.cell>
                <x-table.cell nowrap>
                    @if($product->stock_quantity <= 0)
                        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Rupture</span>
                    @elseif($product->stock_quantity <= $product->reorder_level)
                        <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700">{{ $product->stock_quantity }} unité(s)</span>
                    @else
                        <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ $product->stock_quantity }} unité(s)</span>
                    @endif
                </x-table.cell>
                <x-table.cell hide="lg" nowrap>
                    @if($product->is_active)
                        <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">Actif</span>
                    @else
                        <span class="rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700">Inactif</span>
                    @endif
                </x-table.cell>
                <x-table.actions :label="'Actions pour '.$product->name">
                    <x-table.action :href="route('shop.products.edit', $product)" icon="pencil">Modifier</x-table.action>
                    <x-table.action :action="route('shop.products.destroy', $product)" method="DELETE" icon="trash-2" tone="danger"
                        :confirm="'Supprimer '.$product->name.' ?'">Supprimer</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>

    {{-- ===== VUE CARTE ===== --}}
    @else
        @if($products->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-primary/30">
                <i data-lucide="box" class="w-12 h-12 mb-3 opacity-40"></i>
                <p class="text-sm">Aucun article trouvé</p>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                @foreach($products as $product)
                    <div class="bg-white rounded-xl border border-secondary/15 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden group {{ !$product->is_active ? 'opacity-60' : '' }}">
                        {{-- Image --}}
                        <div class="relative aspect-square bg-gray-50 overflow-hidden">
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-primary/20">
                                    <i data-lucide="package" class="w-12 h-12 mb-2"></i>
                                    <span class="text-[10px] uppercase tracking-wider font-medium">Pas d'image</span>
                                </div>
                            @endif

                            {{-- Badge statut --}}
                            <div class="absolute top-2 left-2">
                                @if(!$product->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 bg-gray-800/70 text-white text-[10px] font-bold rounded-md uppercase tracking-wider backdrop-blur-sm">
                                        Inactif
                                    </span>
                                @elseif($product->stock_quantity <= 0)
                                    <span class="inline-flex items-center px-2 py-0.5 bg-red-500/90 text-white text-[10px] font-bold rounded-md uppercase tracking-wider backdrop-blur-sm">
                                        Rupture
                                    </span>
                                @elseif($product->stock_quantity <= $product->reorder_level)
                                    <span class="inline-flex items-center px-2 py-0.5 bg-yellow-500/90 text-white text-[10px] font-bold rounded-md uppercase tracking-wider backdrop-blur-sm">
                                        Stock bas
                                    </span>
                                @endif
                            </div>

                            {{-- Actions overlay --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end justify-end p-3">
                                <div class="flex gap-1.5">
                                    <a href="{{ route('shop.products.edit', $product) }}"
                                       class="w-8 h-8 bg-white/90 backdrop-blur-sm rounded-lg flex items-center justify-center text-primary hover:bg-white transition-colors shadow-sm"
                                       title="Modifier">
                                        <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <form action="{{ route('shop.products.destroy', $product) }}" method="POST"
                                          onsubmit="return confirm('Supprimer {{ addslashes($product->name) }} ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="w-8 h-8 bg-white/90 backdrop-blur-sm rounded-lg flex items-center justify-center text-red-500 hover:bg-red-50 transition-colors shadow-sm"
                                                title="Supprimer">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Infos --}}
                        <div class="p-4">
                            <div class="mb-2">
                                <h3 class="font-semibold text-primary text-sm truncate">{{ $product->name }}</h3>
                                <p class="text-[11px] text-primary/40 mt-0.5">{{ $product->sku }} · {{ $product->category->name }}</p>
                            </div>

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-secondary/10">
                                <span class="font-heading font-bold text-primary text-sm">
                                    {{ number_format($product->price / 100, 0, ',', ' ') }} <span class="text-xs font-normal text-primary/50">FCFA</span>
                                </span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full
                                    {{ $product->stock_quantity <= 0 ? 'bg-red-50 text-red-700' : ($product->stock_quantity <= $product->reorder_level ? 'bg-yellow-50 text-yellow-700' : 'bg-green-50 text-green-700') }}">
                                    {{ $product->stock_quantity }} en stock
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Pagination de la vue carte ; la liste porte la sienne. --}}
    @if($view !== 'list' && $products->hasPages())
        <div class="mt-6">{{ $products->onEachSide(1)->links('components.table.pagination') }}</div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search-input');
        const searchForm = document.getElementById('search-form');
        const searchSpinner = document.getElementById('search-spinner');
        let debounceTimer = null;

        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                if (searchSpinner) searchSpinner.classList.remove('hidden');

                debounceTimer = setTimeout(function () {
                    searchForm.submit();
                }, 300);
            });

            // Focus the search input and place cursor at end if there's a value
            if (searchInput.value) {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }
        }
    });
</script>
@endpush

@droit('shop.products.import')
    <x-csv-import-modal
        id="modal-import-products"
        title="Importer des articles boutique (CSV)"
        :action="route('shop.products.import')"
        :template="route('shop.products.export', ['template' => 1])"
        structure="sku;nom;categorie;description;prix_fcfa;stock;seuil_reappro;actif"
        submit-label="Importer les articles">
        <li><strong>nom</strong> et <strong>categorie</strong> obligatoires — la catégorie doit exister</li>
        <li><strong>sku</strong> vide = généré automatiquement (ART-…) ; un SKU déjà présent est ignoré</li>
        <li><strong>prix_fcfa</strong> en FCFA (ex. 5000)</li>
    </x-csv-import-modal>
@enddroit

@endsection
