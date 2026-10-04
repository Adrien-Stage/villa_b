@extends('layouts.hotel')

@section('title', 'Comptabilité Boutique')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Comptabilité Boutique</h1>
            <p class="text-secondary mt-1">Historique des sessions de caisse</p>
        </div>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif

    <x-table :rows="$sessions" empty="Aucune session de caisse n'a été trouvée." empty-icon="calculator" caption="Sessions de caisse de la boutique">
        <x-slot:head>
            <x-table.col>Session &amp; caissier</x-table.col>
            <x-table.col>État</x-table.col>
            <x-table.col align="right" hide="xl">Fond de départ</x-table.col>
            <x-table.col align="right" hide="lg">Attendu</x-table.col>
            <x-table.col align="right" hide="lg">Compté</x-table.col>
            <x-table.col align="right">Écart</x-table.col>
        </x-slot:head>

        @foreach($sessions as $session)
            <x-table.row>
                <x-table.cell>
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-accent/20 text-xs font-bold text-primary" aria-hidden="true">{{ strtoupper(substr($session->user->name, 0, 2)) }}</div>
                        <div>
                            <p class="text-sm font-medium text-primary">{{ $session->user->name }}</p>
                            <p class="text-xs text-primary/50">Ouverte : {{ $session->opened_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}</p>
                            @if($session->closed_at)
                                <p class="text-[10px] text-primary/50">Fermée : {{ $session->closed_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}</p>
                            @endif
                        </div>
                    </div>
                </x-table.cell>
                <x-table.cell nowrap>
                    @if($session->closed_at)
                        <span class="rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">Clôturée</span>
                    @else
                        <span class="rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">En cours</span>
                    @endif
                </x-table.cell>
                <x-table.cell align="right" hide="xl" nowrap class="text-primary/70">{{ number_format($session->opening_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span></x-table.cell>
                @if($session->closed_at)
                    @php $gap = $session->discrepancy_amount; @endphp
                    <x-table.cell align="right" hide="lg" nowrap class="font-semibold">{{ number_format($session->theoretical_closing_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span></x-table.cell>
                    <x-table.cell align="right" hide="lg" nowrap class="font-bold">{{ number_format($session->actual_closing_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span></x-table.cell>
                    <x-table.cell align="right" nowrap>
                        @if($gap === 0)
                            <span class="inline-flex items-center gap-1 text-sm font-medium text-green-600"><i data-lucide="check" class="h-3.5 w-3.5" aria-hidden="true"></i> Juste</span>
                        @elseif($gap > 0)
                            <span class="inline-flex items-center gap-1 text-sm font-medium text-yellow-600" title="{{ $session->closing_notes }}">+{{ number_format($gap / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span></span>
                        @else
                            <span class="inline-flex items-center gap-1 text-sm font-medium text-red-600" title="{{ $session->closing_notes }}">−{{ number_format(abs($gap) / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span></span>
                        @endif
                        @if($session->closing_notes)
                            <i data-lucide="info" class="ml-1 inline h-3 w-3 text-primary/40" title="{{ $session->closing_notes }}" aria-label="{{ $session->closing_notes }}"></i>
                        @endif
                    </x-table.cell>
                @else
                    <x-table.cell hide="lg" class="italic text-primary/45">—</x-table.cell>
                    <x-table.cell hide="lg" class="italic text-primary/45">—</x-table.cell>
                    <x-table.cell align="right" class="text-sm italic text-primary/45">En attente de fermeture</x-table.cell>
                @endif
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
