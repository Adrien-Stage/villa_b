@extends('layouts.hotel')

@section('title', 'Commande #' . $order->id)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    @php
        $statusStyles = [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
            'preparing' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'ready' => 'bg-green-50 text-green-700 border-green-200',
            'served' => 'bg-gray-100 text-gray-600 border-gray-200',
            'canceled' => 'bg-red-50 text-red-600 border-red-200',
        ];
    @endphp
    <div>
        <div class="flex items-center gap-3">
            <h1 class="font-heading text-2xl font-semibold text-primary">Commande #{{ $order->id }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusStyles[$order->status] ?? 'bg-white text-primary border-secondary/25' }}">
                {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
            </span>
        </div>
        <p class="text-sm text-primary/50 mt-0.5">
            @if($order->table_number) Table {{ $order->table_number }} · @endif
            {{ ($order->source ?? 'portal') === 'portal' ? 'Commande du portail' : 'Commande saisie en salle' }}
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

        {{-- Serveur responsable --}}
        <div class="px-4 py-4 border-t border-secondary/15">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/45 mb-2">Serveur</p>
            @if($order->assignedServer)
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-full bg-accent/30 flex items-center justify-center text-primary text-xs font-bold">
                        {{ strtoupper(mb_substr($order->assignedServer->name, 0, 2)) }}
                    </div>
                    <p class="text-sm font-semibold text-primary">{{ $order->assignedServer->name }}</p>
                </div>
            @else
                <p class="text-sm text-red-500 font-medium">Aucun serveur affecté</p>
                @if($isServer && $order->isActive())
                    <form method="POST" action="{{ route('restaurant.orders.claim', $order) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 text-xs font-semibold rounded-lg border border-secondary/25 bg-white text-primary hover:bg-accent/20">
                            Prendre cette commande en charge
                        </button>
                    </form>
                @endif
            @endif

            {{-- Le chef peut réaffecter à un serveur en service --}}
            @if($isChief && $order->isActive() && $onDutyServers->isNotEmpty())
                <form method="POST" action="{{ route('restaurant.orders.reassign', $order) }}" class="mt-3 flex items-center gap-2">
                    @csrf
                    <select name="assigned_server_id" class="flex-1 px-2 py-1.5 text-xs border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        @foreach($onDutyServers as $server)
                            <option value="{{ $server->id }}" @selected($order->assigned_server_id === $server->id)>{{ $server->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-secondary/25 bg-white text-primary hover:bg-accent/20">
                        Réaffecter
                    </button>
                </form>
            @endif
        </div>

        {{-- Parcours de la commande --}}
        @if($order->status !== 'canceled')
            <div class="px-4 py-4 border-t border-secondary/15">
                <p class="text-xs font-semibold uppercase tracking-widest text-primary/45 mb-3">Parcours</p>
                @php
                    $steps = [
                        ['label' => 'Reçue', 'at' => $order->placed_at, 'done' => true],
                        ['label' => 'Transmise en cuisine', 'at' => $order->sent_to_kitchen_at, 'done' => (bool) $order->sent_to_kitchen_at],
                        ['label' => 'Prête', 'at' => $order->ready_at, 'done' => (bool) $order->ready_at],
                        ['label' => 'Servie', 'at' => $order->served_at, 'done' => (bool) $order->served_at],
                    ];
                @endphp
                <ol class="space-y-2.5">
                    @foreach($steps as $step)
                        <li class="flex items-center gap-3">
                            <span class="h-5 w-5 rounded-full flex items-center justify-center shrink-0 {{ $step['done'] ? 'bg-green-500 text-white' : 'bg-secondary/15 text-primary/30' }}">
                                <i data-lucide="{{ $step['done'] ? 'check' : 'circle' }}" class="w-3 h-3"></i>
                            </span>
                            <span class="text-sm {{ $step['done'] ? 'text-primary' : 'text-primary/40' }}">{{ $step['label'] }}</span>
                            @if($step['at'])
                                <span class="ml-auto text-[11px] text-primary/40">{{ $step['at']->format('H:i') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        {{-- Actions contextuelles selon le rôle et l'étape --}}
        <div class="px-4 py-4 border-t border-secondary/15 bg-accent/10 space-y-2">
            @php $acted = false; @endphp

            @if($isServer && $order->status === 'pending')
                @php $acted = true; @endphp
                <form method="POST" action="{{ route('restaurant.orders.send_to_kitchen', $order) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-surface-dark">
                        <i data-lucide="send" class="w-4 h-4"></i> Transmettre le bon en cuisine
                    </button>
                </form>
            @endif

            @if($isCook && $order->status === 'confirmed')
                @php $acted = true; @endphp
                <form method="POST" action="{{ route('restaurant.orders.preparing', $order) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                        <i data-lucide="flame" class="w-4 h-4"></i> Prendre en préparation
                    </button>
                </form>
            @endif

            @if($isCook && $order->status === 'preparing')
                @php $acted = true; @endphp
                <form method="POST" action="{{ route('restaurant.orders.ready', $order) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                        <i data-lucide="bell-ring" class="w-4 h-4"></i> Signaler le plat prêt
                    </button>
                </form>
            @endif

            @if($isServer && $order->status === 'ready')
                @php $acted = true; @endphp
                <form method="POST" action="{{ route('restaurant.orders.served', $order) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                        <i data-lucide="check-check" class="w-4 h-4"></i> Marquer comme servie
                    </button>
                </form>
            @endif

            @if(!$acted && !in_array($order->status, ['served', 'canceled']))
                <p class="text-xs text-primary/40 text-center py-1">
                    @if($order->status === 'confirmed' || $order->status === 'preparing')
                        En attente de la cuisine.
                    @elseif($order->status === 'ready')
                        En attente du serveur.
                    @else
                        Aucune action pour votre rôle à cette étape.
                    @endif
                </p>
            @endif

            {{-- Le chef garde la main : annulation et correction de statut --}}
            @if($isChief)
                <details class="pt-2">
                    <summary class="text-[11px] text-primary/45 cursor-pointer hover:text-primary/70">Correction manuelle du statut (chef)</summary>
                    <form method="POST" action="{{ route('restaurant.orders.status', $order) }}" class="flex items-center gap-2 mt-2">
                        @csrf
                        <select name="status" class="flex-1 px-3 py-2 text-sm border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($order->status === $status)>{{ $statusLabels[$status] ?? ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">OK</button>
                    </form>
                </details>
            @endif
        </div>
    </aside>
</div>
@endsection

