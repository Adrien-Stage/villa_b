@extends('layouts.hotel')

@section('title', 'Ventes Bar')

@section('content')
<h1 class="mb-4 text-2xl font-semibold text-primary font-heading">Ventes Bar</h1>
@include('restaurant.bar._nav')

@if(!$store)
    @include('restaurant.bar._depot-unavailable')
@else
    <p class="mb-4 text-sm text-primary/70">Boissons des additions réglées ou reportées sur un séjour, par date de règlement ou de report.</p>
    <form method="GET" action="{{ route('restaurant.bar.sales.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <label class="grid gap-1 text-sm text-primary">Du
            <input name="from" type="date" value="{{ $from }}" class="rounded-lg border-secondary/30">
        </label>
        <label class="grid gap-1 text-sm text-primary">Au
            <input name="to" type="date" value="{{ $to }}" class="rounded-lg border-secondary/30">
        </label>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Afficher</button>
    </form>

    <section class="overflow-x-auto rounded-xl border border-secondary/20 bg-white">
        <table class="w-full text-left text-sm text-primary">
            <thead class="bg-primary/5">
                <tr><th class="p-3">Boisson</th><th class="p-3 text-right">Quantité</th><th class="p-3 text-right">Montant (FCFA)</th></tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($sales as $line)
                    <tr>
                        <td class="p-3 font-medium">{{ $line->item_name }}</td>
                        <td class="p-3 text-right">{{ (float) $line->quantity }}</td>
                        <td class="p-3 text-right">{{ number_format($line->total / 100, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-4 text-primary/60">Aucune vente sur cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $sales->links() }}</div>
    </section>
@endif
@endsection
