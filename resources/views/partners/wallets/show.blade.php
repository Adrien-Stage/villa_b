@extends('layouts.hotel')
@section('title', 'Compte dépôt · '.$partner->name)
@section('content')
<a href="{{ route('partners.wallet.index') }}" class="text-sm text-primary/60 underline">Tous les comptes dépôt</a>
<div class="my-5 flex flex-wrap items-start justify-between gap-5">
    <div><h1 class="font-heading text-2xl font-semibold text-primary">{{ $partner->name }}</h1><p class="text-sm text-primary/60 mt-1">Compte dépôt · {{ $partner->has_convention ? 'Avec convention' : 'Sans convention tarifaire' }}{{ $partner->is_active ? '' : ' · Partenaire désactivé' }}</p></div>
    <div class="w-full sm:w-auto text-left sm:text-right"><p class="text-xs text-primary/60">Solde disponible</p><p class="text-3xl font-heading font-semibold tabular-nums text-primary" data-wallet-balance>{{ number_format(($partner->wallet?->balance ?? 0) / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span></p></div>
</div>
@if(session('success'))<p role="status" class="mb-4 p-3 bg-green-50 text-green-800 rounded-lg">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-4 p-3 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="flex flex-wrap gap-4 mb-6 text-sm">
    <a href="{{ route('editions.show', ['edition' => 'comptes-depot-partenaires', 'partner' => $partner->id]) }}" class="text-primary underline">Relevé imprimable et exports</a>
    <p class="text-primary/60">Un règlement se demande depuis le séjour ou la facture, avec l’accord du partenaire.</p>
</div>
@droit('partners.wallet.credit')
@if($partner->is_active)
<details class="mb-6 border border-secondary/20 rounded-xl bg-white p-5" @if($errors->any()) open @endif>
    <summary class="cursor-pointer font-semibold text-primary">Approvisionner le compte dépôt</summary>
    <form method="POST" enctype="multipart/form-data" action="{{ route('partners.wallet.credit', $partner) }}" class="mt-4 space-y-4">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label><span class="block mb-1 text-sm">Montant reçu (FCFA)</span><input type="number" name="amount" min="1" max="1000000000000" step="1" value="{{ old('amount') }}" required class="w-full border border-secondary/30 rounded-lg px-3 py-2"></label>
            <label><span class="block mb-1 text-sm">Mode d’encaissement</span><select name="method" class="w-full border border-secondary/30 rounded-lg px-3 py-2">@foreach($methods as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="block mb-1 text-sm">Référence du versement</span><input name="reference" maxlength="200" value="{{ old('reference') }}" required class="w-full border border-secondary/30 rounded-lg px-3 py-2"></label>
        </div>
        <label class="block"><span class="block mb-1 text-sm">Note</span><input name="notes" maxlength="1000" value="{{ old('notes') }}" class="w-full border border-secondary/30 rounded-lg px-3 py-2"></label>
        <label class="block"><span class="block mb-1 text-sm">Justificatif du versement (facultatif)</span><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block max-w-full text-sm"><span class="text-xs text-primary/60">PDF ou image, 10 Mo maximum. Accès réservé au personnel habilité.</span></label>
        <p class="text-xs text-primary/60">Enregistrez uniquement un versement effectivement reçu. Les espèces sont enregistrées dans la trésorerie comptable ; elles ne sont pas ajoutées au tiroir d’un réceptionniste.</p>
        <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold">Enregistrer l’approvisionnement</button>
    </form>
</details>
@endif
@enddroit
<x-table :rows="$entries" empty="Aucun mouvement. Le solde du compte dépôt est nul." caption="Mouvements du compte dépôt">
    <x-slot:head><x-table.col>Date / pièce</x-table.col><x-table.col>Opération et accord</x-table.col><x-table.col align="right">Mouvement</x-table.col><x-table.col align="right">Solde après</x-table.col><x-table.col actions /></x-slot:head>
    @foreach($entries as $entry)
    @php $payment = $payments[$entry->id] ?? null; @endphp
    <x-table.row>
        <x-table.cell><p>{{ $entry->created_at->format('d/m/Y H:i') }}</p><p class="text-xs text-primary/60">{{ $payment?->reference }} · {{ $entry->createdBy?->name ?? '—' }}</p></x-table.cell>
        <x-table.cell><p class="font-medium">{{ match($entry->kind) { 'credit' => 'Approvisionnement', 'debit' => 'Règlement sur accord', default => 'Contre-opération' } }}</p>
            @if($payment?->invoice)<a class="text-sm underline" href="{{ route('invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>
            @elseif($payment?->booking)<a class="text-sm underline" href="{{ route('bookings.show', $payment->booking) }}">{{ $payment->booking->booking_number }}</a>@endif
            @if($entry->metadata['authorized_by'] ?? null)<p class="text-xs text-primary/60">Accord : {{ $entry->metadata['authorized_by'] }} · {{ $entry->metadata['authorization_reference'] }}</p>@endif
            <p class="text-xs text-primary/60">{{ $entry->metadata['reference'] ?? $entry->metadata['reason'] ?? $entry->metadata['notes'] ?? '' }}</p>
            @if($entry->reversal)<p class="text-xs text-red-700">Annulé par la pièce DEP-{{ str_pad($entry->reversal->id, 8, '0', STR_PAD_LEFT) }}</p>@endif
        </x-table.cell>
        <x-table.cell align="right" class="tabular-nums">{{ $entry->amount > 0 ? '+' : '−' }}{{ number_format(abs($entry->amount) / 100, 0, ',', ' ') }} FCFA</x-table.cell>
        <x-table.cell align="right" class="tabular-nums">{{ number_format($entry->balance_after / 100, 0, ',', ' ') }} FCFA</x-table.cell>
        <x-table.cell>
            @if($entry->attachment)@droit('partners.wallet.proof')<a class="text-sm underline" href="{{ route('partners.wallet.proof', [$partner, $entry]) }}">Justificatif</a>@enddroit @endif
            @droit('partners.wallet.reverse')
            @if(!$entry->reversal && !$entry->reverses_id)
            <details class="mt-2"><summary class="text-xs cursor-pointer text-red-700">Annuler ce mouvement</summary><form method="POST" action="{{ route('partners.wallet.reverse', [$partner, $entry]) }}" class="mt-2 space-y-2">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><label class="block"><span class="text-xs">Motif de l’annulation</span><input name="reason" required maxlength="500" class="w-full border border-secondary/30 rounded-lg p-2 text-sm"></label><button class="text-xs font-semibold underline text-red-700">Créer la contre-opération</button></form></details>
            @endif
            @enddroit
        </x-table.cell>
    </x-table.row>
    @endforeach
</x-table>
{{ $entries->links() }}
@endsection
