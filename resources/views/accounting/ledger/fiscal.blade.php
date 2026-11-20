@extends('layouts.hotel')

@section('title', 'Suivi fiscal des factures')

@section('content')
<h1 class="text-2xl font-semibold text-primary mb-4">Suivi fiscal des factures</h1>
@include('accounting.ledger.partials.nav')
@php
    $resolver = app(\App\Services\PermissionResolver::class);
    $canWrite = $resolver->allows(auth()->user(), 'accounting.ledger.fiscal.creer');
    $canDownload = $resolver->allows(auth()->user(), 'accounting.ledger.fiscal.export');
    $labels = ['prepared' => 'Dossier préparé', 'accepted' => 'Acceptation consignée', 'rejected' => 'Rejet consigné'];
@endphp
@foreach (array_filter([session('success'), session('error'), $errors->first()]) as $message)
    <p class="bg-accent/20 rounded-lg p-3 mb-4 text-sm" role="status">{{ $message }}</p>
@endforeach
<section class="bg-white border border-secondary/20 rounded-xl p-4">
    <form class="flex flex-wrap items-end gap-3">
        <label class="text-sm">Numéro de facture
            <input name="number" maxlength="60" required value="{{ is_string($number) ? $number : '' }}" class="block rounded-lg border-secondary/30">
        </label>
        <button class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Rechercher</button>
    </form>
    @if ($number && !$invoice)
        <p class="mt-4 text-sm">Aucune facture ne correspond à ce numéro.</p>
    @elseif ($invoice)
        <h2 class="font-semibold mt-5">{{ $invoice->invoice_number }} · {{ $invoice->invoice_date->format('d/m/Y') }}</h2>
        <p class="text-sm mt-2">Préparez les données de la facture et consignez la réponse reçue du canal fiscal autorisé.</p>
        @if ($canWrite && $events->first()?->status !== 'accepted')
            <form action="{{ route('accounting.ledger.fiscal.store') }}" method="post" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                @if ($events->first()?->status === 'prepared')
                    <label class="block text-sm">Référence portée sur l’accusé
                        <input name="authority_reference" required maxlength="120" class="block rounded-lg border-secondary/30" value="{{ old('authority_reference') }}">
                    </label>
                    <label class="block text-sm">Accusé reçu
                        <input type="file" name="proof" accept=".pdf,.xml,.txt,.json" class="block mt-1" required>
                    </label>
                    <label class="text-sm block">Observations ou motif du rejet
                        <textarea name="notes" maxlength="2000" class="block w-full rounded-lg border-secondary/30">{{ old('notes') }}</textarea>
                    </label>
                    <button name="status" value="accepted" class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Consigner l’acceptation</button>
                    <button name="status" value="rejected" class="border border-secondary/30 rounded-lg px-4 py-2 text-sm">Consigner le rejet</button>
                @else
                    <button name="status" value="prepared" class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Préparer le dossier de cette facture</button>
                @endif
            </form>
        @endif
        @foreach ($events as $event)
            <div class="mt-3 border-t border-secondary/15 pt-3 text-sm">
                <strong>{{ $labels[$event->status] }}</strong> · {{ $event->created_at->format('d/m/Y H:i') }}
                @if ($event->issuer_niu) · NIU {{ $event->issuer_niu }} @endif
                @if ($event->authority_reference) · {{ $event->authority_reference }} @endif
                @if ($canDownload)
                    <a href="{{ route('accounting.ledger.fiscal.export', $event) }}" class="ml-2 underline">Télécharger la pièce</a>
                @endif
                @if ($event->observations)
                    <p class="mt-1">{{ $event->observations }}</p>
                @endif
            </div>
        @endforeach
    @endif
</section>
<p class="mt-4 text-sm">
    <a href="https://www.impots.cm/fr/declarations-statistiques-et-fiscalesdsf" class="underline" target="_blank" rel="noopener noreferrer">Modèles de DSF publiés par la DGI</a>
</p>
@endsection
