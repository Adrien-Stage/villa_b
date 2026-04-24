@extends('layouts.hotel')

@section('title', 'Modifier article')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-primary">Modifier l'article</h1>
        <p class="text-secondary mt-1">{{ $product->name }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form action="{{ route('shop.products.update', $product) }}" method="POST" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Catégorie -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Catégorie *</label>
                    <select name="shop_category_id" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('shop_category_id') border-red-500 @enderror">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ $product->shop_category_id == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('shop_category_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SKU -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">SKU (Code) *</label>
                    <input type="text" name="sku" required value="{{ $product->sku }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('sku') border-red-500 @enderror">
                    @error('sku')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Nom -->
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Nom de l'article *</label>
                <input type="text" name="name" required value="{{ $product->name }}"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Description</label>
                <textarea name="description" rows="4"
                          class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('description') border-red-500 @enderror">{{ $product->description }}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Prix -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Prix (FCFA) *</label>
                    <input type="number" name="price" step="0.01" required value="{{ $product->display_price ?? ($product->price / 100) }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('price') border-red-500 @enderror">
                    @error('price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Quantité en stock -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Quantité en stock *</label>
                    <input type="number" name="stock_quantity" required value="{{ $product->stock_quantity }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('stock_quantity') border-red-500 @enderror">
                    @error('stock_quantity')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Niveau de réappro -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Niveau réappro *</label>
                    <input type="number" name="reorder_level" required value="{{ $product->reorder_level }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('reorder_level') border-red-500 @enderror">
                    @error('reorder_level')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Actif -->
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ $product->is_active ? 'checked' : '' }}
                       class="w-4 h-4 text-primary rounded border-gray-200 focus:ring-2 focus:ring-primary">
                <label for="is_active" class="ml-3 text-sm font-medium text-primary">Actif</label>
            </div>

            <!-- Boutons -->
            <div class="flex gap-4 pt-6">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="save" class="w-4 h-4 inline mr-2"></i> Mettre à jour
                </button>
                <a href="{{ route('shop.products.index') }}" class="flex-1 bg-gray-100 hover:bg-gray-200 text-secondary px-6 py-3 rounded-lg font-medium transition-colors text-center">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
