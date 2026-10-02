@extends('layouts.hotel')

@section('title', 'Comptage de caisse — Restaurant')

@php $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA'; @endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-heading font-bold text-primary mb-1 flex items-center gap-2">
        <i data-lucide="lock" class="w-7 h-7 text-red-500"></i>
        Comptage de ma caisse — {{ $session->pointOfSale?->name ?? 'Restaurant' }}
    </h1>
    <p class="text-sm text-primary/60 mb-6">
        Comptez les espèces du tiroir. Une fois le comptage déclaré, la caisse n'encaisse plus ;
        la comptabilité le contresigne et la clôt.
    </p>

    @if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <section class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6">
                <h2 class="font-heading text-lg font-semibold text-primary mb-3">Espèces attendues</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-primary/70">Fond de caisse</dt><dd>{{ $fcfa($session->opening_amount) }}</dd></div>
                    <div class="flex justify-between text-green-700"><dt>+ Notes encaissées en espèces</dt><dd>{{ $fcfa($especes) }}</dd></div>
                    <div class="flex justify-between text-red-600"><dt>− Sorties de caisse</dt><dd>{{ $fcfa($decaissements->sum('amount')) }}</dd></div>
                    <div class="flex justify-between border-t border-secondary/15 pt-2 font-semibold text-primary"><dt>Solde théorique</dt><dd>{{ $fcfa($theorique) }}</dd></div>
                </dl>
                @if($autresModes->isNotEmpty())
                    <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-primary/40">Hors tiroir</p>
                    <ul class="mt-1 space-y-1 text-xs text-primary/70">
                        @foreach($autresModes as $mode)
                            <li class="flex justify-between"><span>{{ str_replace('_', ' ', $mode->payment_method) }} ({{ $mode->nombre }})</span><span>{{ $fcfa($mode->montant) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6">
                <h2 class="font-heading text-lg font-semibold text-primary mb-3">Sortie de caisse</h2>
                <form method="POST" action="{{ route('restaurant.cash_register.disbursements.store') }}" class="grid gap-2 sm:grid-cols-[8rem_1fr_auto]">
                    @csrf
                    <label class="sr-only" for="sortie-montant">Montant</label>
                    <input type="number" id="sortie-montant" name="amount" min="1" step="1" required placeholder="Montant" class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">
                    <label class="sr-only" for="sortie-motif">Motif</label>
                    <input type="text" id="sortie-motif" name="reason" required maxlength="255" placeholder="Motif" class="rounded-lg border border-secondary/30 px-3 py-2 text-sm">
                    <button type="submit" class="rounded-lg border border-secondary/30 px-3 py-2 text-xs font-semibold text-primary">Enregistrer</button>
                </form>
                @foreach($decaissements as $d)
                    <p class="mt-2 flex justify-between text-xs text-primary/70"><span>{{ $d->reason }}</span><span>{{ $fcfa($d->amount) }}</span></p>
                @endforeach
            </section>
        </div>

        <form method="POST" action="{{ route('restaurant.cash_register.close.store') }}" class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6 space-y-4"
              onsubmit="return confirm('Déclarer ce comptage ? La caisse n\'encaissera plus.');">
            @csrf
            <h2 class="font-heading text-lg font-semibold text-primary">Mon comptage</h2>
            <div>
                <label for="compte" class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Espèces comptées (FCFA) *</label>
                <input type="number" id="compte" name="actual_closing_amount" required min="0" step="1"
                       class="w-full px-4 py-3 text-lg border border-secondary/30 rounded-lg text-primary font-semibold">
            </div>
            <div>
                <label for="notes" class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Observations</label>
                <textarea id="notes" name="closing_notes" rows="3" maxlength="1000" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg"></textarea>
            </div>
            <button type="submit" class="w-full bg-primary text-white px-6 py-3 rounded-xl font-medium">Déclarer mon comptage</button>
        </form>
    </div>
</div>
@endsection
