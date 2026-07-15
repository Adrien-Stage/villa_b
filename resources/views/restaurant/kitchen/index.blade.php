@extends('layouts.hotel')

@section('title', 'Cuisine')

@php
    $labels = \App\Models\RestaurantCustomerOrder::STATUS_LABELS;
    $columnMeta = [
        'confirmed' => ['title' => 'Bons reçus', 'icon' => 'inbox', 'accent' => 'border-amber-300', 'head' => 'bg-amber-50 text-amber-800'],
        'preparing' => ['title' => 'En préparation', 'icon' => 'flame', 'accent' => 'border-blue-300', 'head' => 'bg-blue-50 text-blue-800'],
        'ready' => ['title' => 'Prêts à servir', 'icon' => 'bell-ring', 'accent' => 'border-green-300', 'head' => 'bg-green-50 text-green-800'],
    ];
@endphp

@section('content')
<div class="mb-6 flex items-start justify-between gap-4" x-data="{ auto: true, timer: null }" x-init="
    timer = setInterval(() => location.reload(), 20000);
    $watch('auto', v => { clearInterval(timer); if (v) timer = setInterval(() => location.reload(), 20000); });
">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Cuisine</h1>
        <p class="text-sm text-primary/60 mt-1">
            Les bons transmis par la salle. Préparez, puis signalez le plat prêt — le serveur est prévenu automatiquement.
        </p>
    </div>
    <label class="shrink-0 inline-flex items-center gap-2 text-xs text-primary/60 bg-white border border-secondary/20 rounded-lg px-3 py-2">
        <input type="checkbox" x-model="auto" class="rounded border-secondary/30 text-primary">
        Actualisation auto (20s)
    </label>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    @foreach($columnMeta as $statusKey => $meta)
        @php $orders = $columns[$statusKey] ?? collect(); @endphp
        <section class="rounded-xl border {{ $meta['accent'] }} bg-white overflow-hidden flex flex-col">
            <div class="px-4 py-3 border-b border-secondary/15 flex items-center justify-between {{ $meta['head'] }}">
                <h2 class="text-sm font-semibold flex items-center gap-2">
                    <i data-lucide="{{ $meta['icon'] }}" class="w-4 h-4"></i>
                    {{ $meta['title'] }}
                </h2>
                <span class="text-xs font-bold rounded-full bg-white/70 px-2 py-0.5">{{ $orders->count() }}</span>
            </div>

            <div class="p-3 space-y-3 flex-1 min-h-[6rem]">
                @forelse($orders as $order)
                    @php
                        $elapsed = $order->sent_to_kitchen_at ? $order->sent_to_kitchen_at->diffInMinutes(now()) : null;
                        // Au-delà de 20 min en cuisine, le bon vire au rouge : ça traîne.
                        $late = $elapsed !== null && $elapsed >= 20 && $statusKey !== 'ready';
                    @endphp
                    <div class="rounded-lg border {{ $late ? 'border-red-300 bg-red-50/50' : 'border-secondary/20 bg-gray-50/50' }} p-3">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-primary">
                                    {{ $order->table_number ? 'Table ' . $order->table_number : 'Sans table' }}
                                </span>
                                <span class="text-[11px] text-primary/40">#{{ $order->id }}</span>
                            </div>
                            @if($elapsed !== null)
                                <span class="text-[11px] font-medium {{ $late ? 'text-red-600' : 'text-primary/45' }}">
                                    <i data-lucide="clock" class="w-3 h-3 inline"></i> {{ $elapsed }} min
                                </span>
                            @endif
                        </div>

                        <ul class="space-y-0.5 mb-3">
                            @foreach($order->items as $item)
                                <li class="text-xs text-primary/75 flex items-baseline gap-1.5">
                                    <span class="font-bold text-primary">{{ $item->quantity }}×</span>
                                    <span>{{ $item->item_name }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if($order->notes)
                            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-2">
                                <i data-lucide="message-square" class="w-3 h-3 inline"></i> {{ $order->notes }}
                            </p>
                        @endif

                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] text-primary/45">
                                @if($order->assignedServer)
                                    <i data-lucide="user" class="w-3 h-3 inline"></i> {{ $order->assignedServer->name }}
                                @else
                                    <span class="text-red-500">Sans serveur</span>
                                @endif
                            </span>

                            @if($statusKey === 'confirmed')
                                <form method="POST" action="{{ route('restaurant.orders.preparing', $order) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                                        <i data-lucide="flame" class="w-3.5 h-3.5"></i> Prendre en préparation
                                    </button>
                                </form>
                            @elseif($statusKey === 'preparing')
                                <form method="POST" action="{{ route('restaurant.orders.ready', $order) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700">
                                        <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i> Marquer prêt
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-green-700 font-medium">
                                    <i data-lucide="check" class="w-3 h-3 inline"></i> En attente du serveur
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-primary/30">
                        <i data-lucide="{{ $meta['icon'] }}" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                        Aucun bon.
                    </div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
@endsection
