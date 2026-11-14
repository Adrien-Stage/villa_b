@extends('layouts.hotel')

@section('title', 'Historique de la bouteille')

@section('content')
<h1 class="mb-2 text-2xl font-semibold text-primary font-heading">Bouteille nº {{ $bottle->id }} · {{ $bottle->item->name }}</h1>
<p class="mb-4 text-sm text-primary/70">Ouverte le {{ $bottle->created_at->format('d/m/Y à H:i') }} @if($bottle->opener) par {{ $bottle->opener->name }} @endif</p>
@include('restaurant.bar._nav')

<section class="mb-5 rounded-xl border border-secondary/20 bg-white p-4 text-primary">
    <p class="text-xl font-semibold">{{ $bottle->remaining_ml }} / {{ $bottle->capacity_ml }} mL restants</p>
    <p class="mt-1 text-sm text-primary/70">{{ intdiv($bottle->remaining_ml, \App\Models\RestaurantBarDrink::GLASS_ML) }} verres complets de 5 cl</p>
    @droit('restaurant.bar.count_bottle')
        <details class="mt-4 border-t border-secondary/20 pt-3">
            <summary class="cursor-pointer text-sm font-medium">Enregistrer un comptage ou une perte</summary>
            <form method="POST" action="{{ route('restaurant.bar.count_bottle', $bottle) }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <label class="grid gap-1 text-sm">Volume mesuré (mL)
                    <input type="number" name="volume_ml" min="0" max="{{ $bottle->capacity_ml }}" required class="w-40 rounded-lg border-secondary/30">
                </label>
                <label class="grid grow gap-1 text-sm">Motif
                    <input name="reason" maxlength="255" required class="rounded-lg border-secondary/30">
                </label>
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Enregistrer</button>
            </form>
        </details>
    @enddroit
    @droit('restaurant.bar.allocate_volume')
        @if($shortages->isNotEmpty() && $bottle->remaining_ml > 0)
            <details class="mt-4 border-t border-secondary/20 pt-3">
                <summary class="cursor-pointer text-sm font-medium">Attribuer un prélèvement à cette bouteille</summary>
                <form method="POST" action="{{ route('restaurant.bar.allocate_volume', $bottle) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="allocation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <label class="grid gap-1 text-sm">Prélèvement
                        <select name="movement_id" required class="rounded-lg border-secondary/30">
                            <option value="">Choisir un prélèvement</option>
                            @foreach($shortages as $row)
                                <option value="{{ $row['movement']->id }}">Commande nº {{ $row['movement']->source_id }} · {{ $row['movement']->details['sale_label'] }} · {{ $row['volume'] }} mL</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">Volume prélevé (mL)
                        <input type="number" name="volume_ml" min="1" max="{{ $bottle->remaining_ml }}" required class="w-40 rounded-lg border-secondary/30">
                    </label>
                    <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Attribuer</button>
                </form>
            </details>
        @endif
    @enddroit
</section>

<section class="overflow-x-auto rounded-xl border border-secondary/20 bg-white">
    <h2 class="px-4 py-3 font-semibold text-primary">Historique des mouvements</h2>
    <table class="w-full text-left text-sm text-primary">
        <thead class="bg-primary/5">
            <tr><th class="p-3">Date</th><th class="p-3">Origine</th><th class="p-3">Mouvement</th><th class="p-3">Restant après ce mouvement</th></tr>
        </thead>
        <tbody class="divide-y divide-secondary/10">
            @foreach($history as $movement)
                @php $details = $movement->details; @endphp
                <tr>
                    <td class="p-3 whitespace-nowrap">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                    <td class="p-3">
                        <p class="font-medium">{{ $movement->reason }}</p>
                        @if(isset($details['order_line_id']))
                            <p class="text-primary/70">
                                @if(app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'restaurant.orders.voir'))
                                    <a class="underline" href="{{ route('restaurant.orders.show', $movement->source_id) }}">Commande nº {{ $movement->source_id }}</a>
                                @else
                                    Commande nº {{ $movement->source_id }}
                                @endif
                                · {{ \App\Models\RestaurantBarDrink::FORMATS[$details['format']] }}
                                @if(isset($details['recipe_id']))
                                    ·
                                    @if(isset($details['menu_item_id']) && app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'restaurant.bar.menu.voir'))
                                        <a class="underline" href="{{ route('restaurant.bar.menu.index', ['item' => $details['menu_item_id']]) }}">Fiche technique nº {{ $details['recipe_id'] }}</a>
                                    @else
                                        Fiche technique nº {{ $details['recipe_id'] }}
                                    @endif
                                @endif
                            </p>
                        @elseif($movement->user)
                            <p class="text-primary/70">{{ $movement->user->name }}</p>
                        @endif
                    </td>
                    <td class="p-3 whitespace-nowrap">
                        @if(isset($details['volume_ml']))
                            {{ $details['volume_ml'] > 0 ? '+' : '' }}{{ $details['volume_ml'] }} mL
                        @else
                            Ouverture
                        @endif
                    </td>
                    <td class="p-3 whitespace-nowrap">{{ $details['remaining_ml'] }} mL</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-3">{{ $history->links() }}</div>
</section>
@endsection
