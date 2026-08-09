{{-- Champs partagés du formulaire de dépense (création + édition). --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="text-xs text-primary/60">Date *</label>
        <input type="date" name="occurred_at" required
            value="{{ old('occurred_at', optional($expense?->occurred_at)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
            class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
    </div>
    <div>
        <label class="text-xs text-primary/60">Catégorie *</label>
        <select name="category" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
            @foreach($categories as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $expense?->category) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label class="text-xs text-primary/60">Libellé *</label>
    <input type="text" name="label" required maxlength="180"
        value="{{ old('label', $expense?->label) }}"
        placeholder="Ex : Facture ENEO juillet, achat gaz…"
        class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none placeholder-primary/30">
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="text-xs text-primary/60">Montant (FCFA) *</label>
        <input type="number" name="amount" required min="1"
            value="{{ old('amount', $expense ? (int) ($expense->amount / 100) : '') }}"
            class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
    </div>
    <div>
        <label class="text-xs text-primary/60">Moyen de paiement</label>
        <select name="payment_method" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
            <option value="">—</option>
            @foreach($methods as $m)
                <option value="{{ $m }}" @selected(old('payment_method', $expense?->payment_method) === $m)>{{ $methodLabels[$m] ?? ucfirst($m) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label class="text-xs text-primary/60">Pièce justificative (optionnel)</label>
    <input type="file" name="receipt" accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
        class="mt-1 w-full text-sm text-primary/70 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent/30 file:text-primary file:text-xs file:font-medium">
    @if($expense?->receipt_path)
        <p class="text-[11px] text-primary/40 mt-1">Pièce actuelle conservée si aucun nouveau fichier n'est choisi.</p>
    @endif
</div>

<div>
    <label class="text-xs text-primary/60">Notes (optionnel)</label>
    <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">{{ old('notes', $expense?->notes) }}</textarea>
</div>
