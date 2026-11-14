<div class="grid gap-2 sm:grid-cols-[2fr_1fr_1fr]">
    <label class="grid gap-1 text-sm">Article
        <select @if($index === null) :name="'ingredients[' + (index + {{ $startingRows - 1 }}) + '][stock_item_id]'" @else name="ingredients[{{ $index }}][stock_item_id]" @endif
            :disabled="!recipe()" class="rounded-lg border-secondary/30">
            <option value="">Choisir un article</option>
            @foreach($items as $stockItem)
                <option value="{{ $stockItem->id }}" @selected(($ingredient['stock_item_id'] ?? null) == $stockItem->id)>{{ $stockItem->name }} ({{ $stockItem->unit }})</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">Mesure
        <select @if($index === null) :name="'ingredients[' + (index + {{ $startingRows - 1 }}) + '][measure]'" @else name="ingredients[{{ $index }}][measure]" @endif
            :disabled="!recipe()" class="rounded-lg border-secondary/30">
            <option value="ml" @selected(($ingredient['measure'] ?? null) === 'ml')>mL de bouteille ouverte</option>
            <option value="stock" @selected(($ingredient['measure'] ?? null) === 'stock')>Unité de stock</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">Quantité
        <input @if($index === null) :name="'ingredients[' + (index + {{ $startingRows - 1 }}) + '][quantity]'" @else name="ingredients[{{ $index }}][quantity]" @endif
            :disabled="!recipe()" type="number" min="0.001" max="100000" step="0.001" value="{{ $ingredient['quantity'] ?? '' }}" class="rounded-lg border-secondary/30">
    </label>
</div>
