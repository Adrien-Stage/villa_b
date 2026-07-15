@extends('layouts.hotel')

@section('title', 'Commande #' . $order->id)

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Commande #{{ $order->id }}</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ strtoupper($order->status) }}
            @if($order->table_number) · Table {{ $order->table_number }} @endif
            · {{ strtoupper($order->source ?? 'portal') }}
        </p>
    </div>
    <a href="{{ route('restaurant.orders.index') }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        Retour
    </a>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if(session('stock_warning'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <span>{{ session('stock_warning') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4">
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
            <p class="font-heading text-sm font-semibold text-primary">Infos</p>
        </div>

        <div class="p-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Date</p>
                <p class="text-primary font-semibold">{{ $order->placed_at?->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Table</p>
                <p class="text-primary font-semibold">{{ $order->table_number ?? '—' }}</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Client</p>
                <p class="text-primary font-semibold">{{ $order->customer_name ?? '—' }}</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-primary/50">Téléphone</p>
                <p class="text-primary font-semibold">{{ $order->customer_phone ?? '—' }}</p>
            </div>
            @if($order->notes)
                <div class="pt-2 border-t border-secondary/15">
                    <p class="text-primary/50 text-xs font-semibold uppercase tracking-widest">Note</p>
                    <p class="text-primary/80 mt-1">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        <div class="px-4 py-4 border-t border-secondary/15 bg-accent/10">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/45 mb-2">Statut</p>
            @role('restaurant_chief', 'restaurant_staff')
            <form id="status-form" method="POST" action="{{ route('restaurant.orders.status', $order) }}" class="flex items-center gap-2">
                @csrf
                <select name="status" class="flex-1 px-3 py-2 text-sm border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ strtoupper($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">OK</button>
            </form>
            <p id="status-hint" class="text-[11px] text-primary/45 mt-2 hidden">Statut mis a jour.</p>
            @else
                <p class="text-xs text-primary/40 italic text-center py-2">Consultation uniquement — actions réservées au personnel du restaurant.</p>
            @endrole
        </div>
    </aside>
</div>

<script>
document.getElementById('status-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: new FormData(this),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) return;
        const payload = await response.json();
        if (!payload || !payload.ok) return;

        const hint = document.getElementById('status-hint');
        if (hint) {
            hint.classList.remove('hidden');
            setTimeout(() => hint.classList.add('hidden'), 1200);
        }
    } catch (err) {
        console.error(err);
    }
});
</script>
@endsection

