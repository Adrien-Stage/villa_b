@php
    $walletInvoice = $walletInvoice ?? null;
    $walletBooking = $walletInvoice?->booking ?? $booking;
    $walletPartner = ($walletInvoice?->customer ?? $walletBooking->customer)?->partnerOrganization;
    $walletDue = $walletInvoice?->balance_due ?? $walletBooking->balance_due;
@endphp
@droit('partners.wallet.debit')
@if($walletPartner?->is_active && $walletDue > 0)
<details class="no-print my-5 border border-secondary/20 bg-white rounded-xl p-5">
    <summary class="cursor-pointer font-semibold text-primary">Régler avec le compte dépôt de {{ $walletPartner->name }}</summary>
    <p class="mt-3 text-sm text-primary/70">Disponible : <strong>{{ number_format(($walletPartner->wallet?->balance ?? 0) / 100, 0, ',', ' ') }} FCFA</strong>. Chaque règlement nécessite un accord du partenaire, même si le client appartient à cette structure.</p>
    @droit('partners.wallet.voir')<a href="{{ route('partners.wallet.show', $walletPartner) }}" class="inline-block mt-2 text-sm text-primary underline">Consulter les mouvements du compte dépôt</a>@enddroit
    <form method="POST" enctype="multipart/form-data" action="{{ route('partners.wallet.debit', $walletPartner) }}" class="mt-4 space-y-4">
        @csrf
        <input type="hidden" name="booking_id" value="{{ $walletBooking->id }}">
        @if($walletInvoice)<input type="hidden" name="invoice_id" value="{{ $walletInvoice->id }}">@endif
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="grid sm:grid-cols-2 gap-4">
            <label><span class="block text-sm mb-1">Montant à régler (FCFA)</span><input type="number" name="amount" min="1" max="{{ intdiv($walletDue, 100) }}" step="1" required value="{{ old('amount', intdiv($walletDue, 100)) }}" class="w-full border border-secondary/30 rounded-lg p-2"></label>
            <label><span class="block text-sm mb-1">Nom de l’autorisateur chez le partenaire</span><input name="authorized_by" maxlength="150" required value="{{ old('authorized_by') }}" class="w-full border border-secondary/30 rounded-lg p-2"></label>
            <label><span class="block text-sm mb-1">Référence de l’accord</span><input name="authorization_reference" maxlength="200" required value="{{ old('authorization_reference') }}" class="w-full border border-secondary/30 rounded-lg p-2"><span class="text-xs text-primary/60">Courriel, bon de prise en charge ou autre accord identifiable.</span></label>
            <label><span class="block text-sm mb-1">Justificatif de l’accord (facultatif)</span><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block max-w-full text-sm"><span class="text-xs text-primary/60">PDF ou image, 10 Mo maximum.</span></label>
        </div>
        <label class="block"><span class="block text-sm mb-1">Note</span><input name="notes" maxlength="1000" value="{{ old('notes') }}" class="w-full border border-secondary/30 rounded-lg p-2"></label>
        <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold">Débiter le compte dépôt et enregistrer le règlement</button>
    </form>
</details>
@endif
@enddroit
