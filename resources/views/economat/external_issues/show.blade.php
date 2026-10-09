@extends('layouts.hotel')

@section('title', 'Bon ' . $sortie->number . ' — Sortie hors établissement')

@section('content')
@include('economat.external_issues.partials.police-signature')
@php
    $fcfa = fn (int $v) => number_format($v / 100, 0, ',', ' ');
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('economat.external_issues.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux sorties
        </a>
        <a href="{{ route('economat.external_issues.print', $sortie) }}" target="_blank"
           class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-secondary/30 rounded-lg text-primary text-xs font-semibold hover:bg-gray-50 shadow-sm">
            <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le bon de sortie
        </a>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-heading font-bold text-primary font-mono">{{ $sortie->number }}</h1>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $sortie->isCancelled() ? 'border-gray-200 bg-gray-100 text-gray-600' : 'border-red-200 bg-red-50 text-red-700' }}">{{ $sortie->statusLabel() }}</span>
                </div>
                <p class="text-sm font-semibold text-primary/80 mt-1">{{ $sortie->reasonLabel() }}</p>
                <p class="text-xs text-primary/50 mt-0.5">
                    Sorti le {{ $sortie->issued_at->format('d/m/Y à H:i') }} · validé par <strong>{{ $sortie->issuedBy?->name ?? '—' }}</strong>
                </p>
                @if($sortie->expected_return_at)
                    <p class="text-xs mt-1 {{ $sortie->retourEnRetard() ? 'text-amber-800 font-semibold' : 'text-primary/60' }}">
                        Retour prévu le {{ $sortie->expected_return_at->format('d/m/Y') }}
                        @if($sortie->retourEnRetard()) — en retard @endif
                    </p>
                @endif
                @if($sortie->notes)
                    <p class="mt-3 rounded-lg border border-secondary/15 bg-surface-light px-3 py-2 text-xs text-primary/80">{{ $sortie->notes }}</p>
                @endif
            </div>
            <div class="text-right sm:border-l sm:border-secondary/15 sm:pl-6">
                <div class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Valeur sortie</div>
                <div class="text-2xl font-mono font-bold text-primary mt-0.5">{{ $fcfa((int) $sortie->total_value) }} <span class="text-sm font-sans font-normal text-primary/60">FCFA</span></div>
                <div class="text-[11px] text-primary/45">au coût moyen à la sortie</div>
            </div>
        </div>

        @if($sortie->isCancelled())
            <div class="mt-4 pt-3 border-t border-secondary/15 text-xs text-primary/70">
                Annulée par <strong>{{ $sortie->cancelledBy?->name ?? '—' }}</strong> le {{ $sortie->cancelled_at?->format('d/m/Y à H:i') }}
                — <em>« {{ $sortie->cancellation_reason }} »</em>. Le matériel est revenu en stock.
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-primary/70 mb-3">Emporté par</h2>
            <p class="text-sm font-semibold text-primary">{{ $sortie->beneficiary_name }}</p>
            @if($sortie->beneficiary_organisation)<p class="text-xs text-primary/70">{{ $sortie->beneficiary_organisation }}</p>@endif
            <dl class="mt-2 space-y-1 text-xs text-primary/70">
                <div class="flex gap-2"><dt class="text-primary/45">Téléphone :</dt><dd>{{ $sortie->beneficiary_phone ?? '—' }}</dd></div>
                <div class="flex gap-2"><dt class="text-primary/45">Pièce d'identité :</dt><dd>{{ $sortie->beneficiary_id_document ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm grid grid-cols-2 gap-3 text-center">
            <div>
                <p class="text-[10px] uppercase tracking-wider text-primary/45">Signature du demandeur</p>
                <p class="font-signature text-3xl text-blue-900 py-1 -rotate-3">{{ $sortie->beneficiary_signature }}</p>
                <p class="text-[11px] text-primary/70">{{ $sortie->beneficiary_name }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-wider text-primary/45">Visa de l'économe</p>
                <p class="font-signature text-3xl text-primary py-1">{{ $sortie->issuer_signature }}</p>
                <p class="text-[11px] text-primary/70">{{ $sortie->issuedBy?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/70">
            <h2 class="text-sm font-semibold text-primary">Articles sortis ({{ $sortie->lines->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Article</th>
                        <th class="px-4 py-2.5 text-right">Quantité</th>
                        <th class="px-4 py-2.5 text-right">Coût unitaire</th>
                        <th class="px-4 py-2.5 text-right">Valeur</th>
                        <th class="px-4 py-2.5 text-left">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($sortie->lines as $ligne)
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-primary">{{ $ligne->item?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ $qte($ligne->quantity) }} <span class="text-xs text-primary/50">{{ $ligne->item?->unit }}</span></td>
                            <td class="px-4 py-2.5 text-right font-mono text-primary/70">{{ $fcfa((int) $ligne->unit_cost) }} F</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold">{{ $fcfa((int) $ligne->total_cost) }} F</td>
                            <td class="px-4 py-2.5 text-xs text-primary/60">{{ $ligne->notes ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(!$sortie->isCancelled())
        @droit('economat.external_issues.cancel')
            <details class="bg-white border border-red-200 rounded-xl p-4">
                <summary class="cursor-pointer text-xs font-semibold text-red-700">Annuler cette sortie (le matériel revient en stock)</summary>
                <form method="POST" action="{{ route('economat.external_issues.cancel', $sortie) }}" class="mt-3 space-y-2">
                    @csrf
                    <label for="motif-annulation" class="block text-xs text-primary/70">Motif de l'annulation</label>
                    <textarea id="motif-annulation" name="cancellation_reason" rows="2" required maxlength="500"
                              class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary">{{ old('cancellation_reason') }}</textarea>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700">Confirmer l'annulation</button>
                </form>
            </details>
        @enddroit
    @endif
</div>
@endsection
