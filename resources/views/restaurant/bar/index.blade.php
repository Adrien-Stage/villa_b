@extends('layouts.hotel')

@section('title', 'Bar')

@php
    // Vue d'ensemble de plusieurs restaurants : chaque bon dit d'où il vient.
    $vueEnsemble = app(\App\Services\RestaurantContext::class)->plusieurs()
        && app(\App\Services\RestaurantContext::class)->courant(auth()->user()) === null;
@endphp

@section('content')
@include('restaurant.partials.service-absent', ['service' => \App\Models\PointOfSale::SERVICE_BAR])
<div class="mb-6 flex items-start justify-between gap-4" x-data="{ auto: true, timer: null }" x-init="
    timer = setInterval(() => location.reload(), 20000);
    $watch('auto', v => { clearInterval(timer); if (v) timer = setInterval(() => location.reload(), 20000); });
">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Bar</h1>
        <p class="text-sm text-primary/60 mt-1">Les boissons des bons transmis par la salle. Préparez-les, puis signalez-les prêtes — le serveur est prévenu.</p>
    </div>
    <label class="shrink-0 inline-flex items-center gap-2 text-xs text-primary/60 bg-white border border-secondary/20 rounded-lg px-3 py-2">
        <input type="checkbox" x-model="auto" class="rounded border-secondary/30 text-primary">
        Actualisation auto (20s)
    </label>
</div>

@include('restaurant.bar._nav')

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse($orders as $order)
        @php $attente = $order->sent_to_kitchen_at?->diffInMinutes(now()); @endphp
        <article class="rounded-xl border {{ $attente !== null && $attente >= 10 ? 'border-red-300 bg-red-50/50' : 'border-secondary/20 bg-white' }} p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-primary">{{ $order->table_number ? 'Table ' . $order->table_number : 'Sans table' }}</span>
                <span class="text-[11px] text-primary/45">#{{ $order->id }}@if($attente !== null) · {{ $attente }} min @endif</span>
            </div>
            @if($vueEnsemble && $order->pointOfSale)
                <p class="mb-1 text-[11px] font-semibold text-primary/50">{{ $order->pointOfSale->name }}</p>
            @endif
            <ul class="mb-3 space-y-0.5">
                @foreach($order->items as $item)
                    @php
                        $portions = $preparations->get($item->id, collect());
                        $instructions = $portions->first()?->details['instructions'] ?? null;
                    @endphp
                    <li class="mb-2 text-xs text-primary/80">
                        <span class="font-bold text-primary">{{ (float) $item->quantity }}×</span> {{ $item->item_name }}
                        @if($item->special_requests)
                            <p class="pl-4">{{ $item->special_requests }}</p>
                        @endif
                        @if($instructions)
                            <p class="whitespace-pre-line pl-4">{{ $instructions }}</p>
                        @endif
                        @foreach($portions as $portion)
                            @php
                                $detail = $portion->details;
                                $volume = isset($detail['volume_ml']) ? abs($detail['volume_ml']) : null;
                                $toAllocate = ($detail['unallocated'] ?? false) ? $volume - ($allocations->get($portion->id)?->sum('details.volume_ml') ?? 0) : 0;
                            @endphp
                            @if($toAllocate > 0)
                                <p class="pl-4 text-amber-800">{{ $portion->item?->name }} · {{ $toAllocate }} mL à attribuer</p>
                            @elseif(isset($detail['bottle_id']))
                                <p class="pl-4">{{ $portion->item?->name }} · {{ $volume }} mL · bouteille nº {{ $detail['bottle_id'] }}</p>
                            @elseif(! isset($detail['volume_ml']))
                                <p class="pl-4">
                                    {{ $portion->item?->name }} · {{ abs((float) $portion->quantity) }} {{ $portion->item?->unit }}
                                    @if(isset($detail['shortage_quantity']))
                                        <span class="text-amber-800"> · manque signalé de {{ $detail['shortage_quantity'] }} {{ $portion->item?->unit }}</span>
                                    @endif
                                </p>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] text-primary/45">{{ $order->assignedServer?->name ?? 'Sans serveur' }}</span>
                @droit('restaurant.orders.bar_ready')
                    <form method="POST" action="{{ route('restaurant.orders.bar_ready', $order) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                            <i data-lucide="glass-water" class="w-3.5 h-3.5"></i> Prêtes
                        </button>
                    </form>
                @enddroit
            </div>
        </article>
    @empty
        <p class="col-span-full rounded-xl bg-white px-4 py-10 text-center text-sm text-primary/50 shadow-sm">Aucune boisson en attente.</p>
    @endforelse
</div>
@endsection
