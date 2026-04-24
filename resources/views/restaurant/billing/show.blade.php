@extends('layouts.hotel')

@section('title', 'Facturation #' . $order->id)

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Facturation #{{ $order->id }}</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            Table {{ $order->table_number }}
            · {{ strtoupper($order->payment_status ?? 'unpaid') }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if($order->payment_status === 'paid')
            <a href="{{ route('restaurant.billing.receipt', $order) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                Recu
            </a>
        @endif
        <a href="{{ route('restaurant.billing.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            Retour
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-4">
    <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15">
            <p class="font-heading text-sm font-semibold text-primary">Articles</p>
        </div>

        <div class="divide-y divide-secondary/10">
            @foreach($order->items as $line)
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary truncate">{{ $line->item_name }}</p>
                        <p class="text-xs text-primary/45 mt-0.5">
                            {{ number_format($line->unit_price / 100, 0, ',', ' ') }} FCFA x {{ (int) $line->quantity }}
                        </p>
                    </div>
                    <p class="text-sm font-semibold text-primary flex-shrink-0">
                        {{ number_format($line->total_price / 100, 0, ',', ' ') }} FCFA
                    </p>
                </div>
            @endforeach
        </div>

        <div class="px-4 py-4 border-t border-secondary/15 flex items-center justify-between">
            <p class="text-sm font-semibold text-primary">Total</p>
            <p class="font-heading text-lg font-semibold text-primary">
                {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
            </p>
        </div>
    </section>

    <aside class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
        <div class="px-4 py-4 border-b border-secondary/15">
            <p class="font-heading text-sm font-semibold text-primary">Paiement</p>
        </div>

        <div class="p-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Statut</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $order->payment_status === 'paid' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                    {{ strtoupper($order->payment_status ?? 'unpaid') }}
                </span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Methode</p>
                <p class="text-primary font-semibold">{{ $order->payment_method ? str_replace('_', ' ', $order->payment_method) : '—' }}</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Payé</p>
                <p class="text-primary font-semibold">{{ number_format($order->amount_paid / 100, 0, ',', ' ') }} FCFA</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Date</p>
                <p class="text-primary font-semibold">{{ $order->paid_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
        </div>

        <div class="px-4 py-4 border-t border-secondary/15 bg-accent/10 space-y-3">
            @role('restaurant_chief','cashier')
            @if($order->payment_status !== 'paid')
                <form method="POST" action="{{ route('restaurant.billing.paid', $order) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45">Methode de paiement</label>
                    <select id="payment-method" name="payment_method" required class="w-full px-3 py-2 text-sm border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        <option value="">Choisir...</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method }}">{{ strtoupper(str_replace('_',' ', $method)) }}</option>
                        @endforeach
                    </select>

                    <div id="booking-select-wrapper" class="hidden">
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45 mt-2">Resident (sur chambre)</label>
                        <select name="booking_id" class="w-full px-3 py-2 text-sm border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                            <option value="">Selectionner un sejour en cours...</option>
                            @foreach($checkedInBookings as $booking)
                                <option value="{{ $booking->id }}">
                                    Chambre {{ $booking->room?->number ?? '?' }} · {{ $booking->customer?->name ?? 'Client' }} · {{ $booking->booking_number }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-primary/45 mt-1">La commande sera ajoutée au folio et apparaîtra sur la facture finale.</p>
                    </div>

                    <button type="submit" class="w-full px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Marquer payee</button>
                </form>
            @else
                <form method="POST" action="{{ route('restaurant.billing.unpaid', $order) }}" onsubmit="return confirm('Annuler le paiement ?');">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 text-xs font-semibold rounded-lg border border-secondary/25 bg-white text-primary hover:bg-accent/20">
                        Annuler paiement
                    </button>
                </form>
            @endif
            @else
                <p class="text-xs text-primary/40 italic text-center py-2">Consultation uniquement — actions réservées au personnel de caisse.</p>
            @endrole
        </div>
    </aside>
</div>

<script>
const paymentMethod = document.getElementById('payment-method');
const bookingWrapper = document.getElementById('booking-select-wrapper');

if (paymentMethod && bookingWrapper) {
    const sync = () => bookingWrapper.classList.toggle('hidden', paymentMethod.value !== 'room_charge');
    paymentMethod.addEventListener('change', sync);
    sync();
}
</script>
@endsection
