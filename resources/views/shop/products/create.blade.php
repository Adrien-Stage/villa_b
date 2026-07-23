@extends('layouts.hotel')

@section('title', 'Créer un article')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-primary">Nouvel article</h1>
        <p class="text-secondary mt-1">Ajouter un article à la boutique</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                <i data-lucide="alert-circle" class="w-5 h-5 inline mr-2"></i>
                <strong>Des erreurs sont survenues :</strong>
                <ul class="mt-2 list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('shop.products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Catégorie -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Catégorie *</label>
                    <select name="shop_category_id" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('shop_category_id') border-red-500 @enderror">
                        <option value="">Sélectionner une catégorie</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('shop_category_id') == $category->id ? 'selected' : '' }}>
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
                    <input type="text" name="sku" required value="{{ old('sku', $nextSku) }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('sku') border-red-500 @enderror">
                    <p class="text-xs text-secondary mt-1">Généré automatiquement — modifiable avant création</p>
                    @error('sku')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Nom -->
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Nom de l'article *</label>
                <input type="text" name="name" required value="{{ old('name') }}"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Description</label>
                <textarea name="description" rows="4"
                          class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Image Upload -->
            <div x-data="imageUploader()">
                <label class="block text-sm font-medium text-primary mb-2">Image du produit</label>
                <div class="relative">
                    <!-- Zone de drop / sélection -->
                    <div x-show="!preview"
                         @click="$refs.fileInput.click()"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event)"
                         :class="isDragging ? 'border-primary bg-primary/5' : 'border-gray-300 bg-gray-50/50 hover:border-primary/50 hover:bg-primary/5'"
                         class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all duration-200">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center">
                                <i data-lucide="image-plus" class="w-7 h-7 text-primary/60"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-primary">
                                    <span class="text-primary/80 underline decoration-primary/30 underline-offset-2">Cliquez pour choisir</span>
                                    ou glissez-déposez
                                </p>
                                <p class="text-xs text-secondary mt-1">JPG, PNG ou WebP • Max 2 Mo</p>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div x-show="preview" x-transition class="relative" style="display: none;">
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 bg-gray-50 shadow-sm">
                            <img :src="preview" alt="Aperçu" class="w-full h-56 object-contain bg-white p-2">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 hover:opacity-100 transition-opacity"></div>
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-center gap-2 text-sm text-secondary">
                                <i data-lucide="file-image" class="w-4 h-4 text-primary/50"></i>
                                <span x-text="fileName" class="truncate max-w-[200px]"></span>
                                <span class="text-xs text-secondary/60" x-text="fileSize"></span>
                            </div>
                            <button type="button" @click="removeImage()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors border border-red-100">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                Supprimer
                            </button>
                        </div>
                    </div>

                    <input type="file" name="image" x-ref="fileInput" @change="handleFileSelect($event)"
                           accept="image/jpeg,image/png,image/webp" class="hidden">
                </div>
                @error('image')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Prix -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Prix (FCFA) *</label>
                    <input type="number" name="price" step="0.01" required value="{{ old('price') }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('price') border-red-500 @enderror">
                    @error('price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Quantité en stock -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Quantité en stock *</label>
                    <input type="number" name="stock_quantity" required value="{{ old('stock_quantity', 0) }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('stock_quantity') border-red-500 @enderror">
                    @error('stock_quantity')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Niveau de réappro -->
                <div>
                    <label class="block text-sm font-medium text-primary mb-2">Niveau réappro *</label>
                    <input type="number" name="reorder_level" required value="{{ old('reorder_level', 5) }}"
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('reorder_level') border-red-500 @enderror">
                    @error('reorder_level')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Actif -->
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked
                       class="w-4 h-4 text-primary rounded border-gray-200 focus:ring-2 focus:ring-primary">
                <label for="is_active" class="ml-3 text-sm font-medium text-primary">Actif</label>
            </div>

            <!-- Boutons -->
            <div class="flex gap-4 pt-6">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="save" class="w-4 h-4 inline mr-2"></i> Créer l'article
                </button>
                <a href="{{ route('shop.products.index') }}" class="flex-1 bg-gray-100 hover:bg-gray-200 text-secondary px-6 py-3 rounded-lg font-medium transition-colors text-center">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('imageUploader', () => ({
        preview: null,
        fileName: '',
        fileSize: '',
        isDragging: false,

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.processFile(file);
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                // Set the file to the input
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.fileInput.files = dt.files;
                this.processFile(file);
            }
        },

        processFile(file) {
            // Validate
            const maxSize = 2 * 1024 * 1024; // 2MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!allowedTypes.includes(file.type)) {
                alert('Format non supporté. Utilisez JPG, PNG ou WebP.');
                return;
            }
            if (file.size > maxSize) {
                alert('L\'image est trop volumineuse (max 2 Mo).');
                return;
            }

            this.fileName = file.name;
            this.fileSize = this.formatBytes(file.size);

            const reader = new FileReader();
            reader.onload = (e) => {
                this.preview = e.target.result;
                this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
            };
            reader.readAsDataURL(file);
        },

        removeImage() {
            this.preview = null;
            this.fileName = '';
            this.fileSize = '';
            this.$refs.fileInput.value = '';
            this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 o';
            const k = 1024;
            const sizes = ['o', 'Ko', 'Mo'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    }));
});
</script>
@endpush
@endsection
