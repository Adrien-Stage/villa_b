<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commande #{{ $order->id }} — {{ $tenant->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-accent/25 font-body text-primary">
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-secondary/15">
        <div class="max-w-xl mx-auto px-4 py-4">
            <p class="text-[11px] uppercase tracking-widest text-primary/45 font-semibold">Commande</p>
            <h1 class="font-heading text-xl font-semibold">{{ $tenant->name }}</h1>
            <p class="text-xs text-primary/55 mt-0.5">
                Commande #{{ $order->id }}
                @if($order->table_number) · Table {{ $order->table_number }} @endif
            </p>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-4 py-6 space-y-4">
        <div class="rounded-2xl border border-secondary/15 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-primary">Commande envoyee</p>
                    <p class="text-xs text-primary/45 mt-1">Merci. Le restaurant va traiter ta demande.</p>
                </div>
                <i data-lucide="check-circle" class="w-7 h-7 text-green-600"></i>
            </div>
        </div>

        <div class="rounded-2xl border border-secondary/15 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-primary/45">Statut</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-accent/30
                    text-primary border border-secondary/15">
                    {{ strtoupper($order->status) }}
                </span>
            </div>
            <div class="mt-4 divide-y divide-secondary/10">
                @foreach($order->items as $line)
                    <div class="py-3 flex items-center justify-between gap-3">
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
            <div class="mt-4 pt-4 border-t border-secondary/15 flex items-center justify-between">
                <p class="text-sm font-semibold text-primary">Total</p>
                <p class="font-heading text-lg font-semibold text-primary">
                    {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                </p>
            </div>
        </div>

        <a href="{{ route('portal.restaurant.menu', ['tenant' => $tenant->slug]) }}"
            class="inline-flex items-center justify-center gap-2 w-full rounded-2xl bg-primary text-secondary px-5 py-3
                text-sm font-semibold hover:bg-surface-dark">
            <i data-lucide="book-open" class="w-4 h-4"></i>
            Retour au menu
        </a>
    </main>

    <script>
    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    </script>
</body>
</html>

