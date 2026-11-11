@extends('layouts.hotel')
@section('title', 'Comptes dépôt partenaires')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div><h1 class="font-heading text-2xl font-semibold text-primary">Comptes dépôt partenaires</h1>
        <p class="mt-1 text-sm text-primary/60">Soldes disponibles et règlements autorisés des entreprises et organismes, avec ou sans convention.</p></div>
    <a href="{{ route('editions.show', 'comptes-depot-partenaires') }}" class="text-sm font-medium text-primary underline">Éditer le registre</a>
</div>
<form method="GET" class="mb-5 flex gap-2">
    <x-live-search
        name="q"
        target="#wallets-table-container"
        placeholder="Nom du partenaire..."
        width="w-full max-w-md"
    />
</form>
<div id="wallets-table-container">
<x-table :rows="$partners" empty="Aucun partenaire trouvé." caption="Comptes dépôt partenaires">
    <x-slot:head><x-table.col>Partenaire</x-table.col><x-table.col>Convention</x-table.col><x-table.col align="right">Solde disponible</x-table.col><x-table.col actions /></x-slot:head>
    @foreach($partners as $partner)
    <x-table.row>
        <x-table.cell><a class="font-medium text-primary underline" href="{{ route('partners.wallet.show', $partner) }}">{{ $partner->name }}</a><p class="text-xs text-primary/60">{{ $partner->typeLabel() }} · {{ $partner->customers_count }} membre(s){{ $partner->is_active ? '' : ' · Désactivé' }}</p></x-table.cell>
        <x-table.cell>{{ $partner->has_convention ? ($partner->isValidOn() ? 'Applicable' : 'Hors période ou inactive') : 'Sans convention' }}</x-table.cell>
        <x-table.cell align="right" class="font-semibold tabular-nums">{{ number_format(($partner->wallet?->balance ?? 0) / 100, 0, ',', ' ') }} FCFA</x-table.cell>
        <x-table.actions :label="'Compte de '.$partner->name"><x-table.action :href="route('partners.wallet.show', $partner)" icon="book-open">Consulter le compte</x-table.action></x-table.actions>
    </x-table.row>
    @endforeach
</x-table>
{{ $partners->links() }}
</div>
@endsection
