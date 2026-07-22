@extends('layouts.hotel')

@section('title', 'Nouvelle demande — Économat')

@section('content')
<div class="max-w-3xl mx-auto"
     x-data="requisitionForm({{ Js::from($items->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'stock' => (float) $i->current_stock])->values()) }})">
    <a href="{{ route('economat.requisitions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>

    <h1 class="text-xl font-heading font-semibold text-primary mb-1">Nouvelle demande à l'économat</h1>
    <p class="text-sm text-primary/60 mb-6">Elle sera transmise à l'économe pour validation avant la livraison.</p>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.requisitions.store') }}">
        @csrf
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Département <span class="text-red-500">*</span></label>
                    <select name="department" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        @foreach($departments as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Motif</label>
                    <input type="text" name="purpose" maxlength="500" placeholder="Ex : réassort chambres étage 2" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                </div>
            </div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-secondary/20 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter
                </button>
            </div>
            <div class="p-5 space-y-3">
                <template x-if="lines.length === 0"><p class="text-sm text-primary/40 text-center py-4">Aucun article.</p></template>
                <template x-for="(line, idx) in lines" :key="line.key">
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <div class="col-span-7">
                            <select :name="`lines[${idx}][stock_item_id]`" x-model.number="line.itemId" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">Article…</option>
                                <template x-for="it in items" :key="it.id">
                                    <option :value="it.id" x-text="`${it.name} (${formatStock(it.stock)} ${it.unit} en stock)`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-4">
                            <input type="number" step="0.001" min="0.001" :name="`lines[${idx}][quantity]`" x-model.number="line.qty" placeholder="Quantité" required class="w-full px-2 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                        </div>
                        <div class="col-span-1 text-right">
                            <button type="button" @click="removeLine(idx)" class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('economat.requisitions.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</a>
            <button type="submit" :disabled="lines.length === 0" class="px-5 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark disabled:opacity-50 disabled:cursor-not-allowed">
                Transmettre la demande
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
            addLine() { this.lines.push({ key: this.nextKey++, itemId: '', qty: 1 }); },
            removeLine(idx) { this.lines.splice(idx, 1); },
            formatStock(v) { return new Intl.NumberFormat('fr-FR').format(v); },
            init() { this.addLine(); },
        };
    }
</script>
@endpush
