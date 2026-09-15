@extends('layouts.hotel')

@section('title', 'Contrôle des comptages')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $moduleLabels = ['reception' => 'Hébergement', 'shop' => 'Boutique'];
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Contrôle des comptages de caisse</h1>
    <p class="text-sm text-primary/60 mt-1">
        Caisses comptées par leur titulaire et closes seulement après contresignature.
        Votre établissement a confié ce contrôle à <strong>{{ $witnessLabel }}</strong>.
    </p>
</div>

@include('accounting.partials.nav')

@if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@error('session')
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        {{ $message }}
    </div>
@enderror

@if ($sessions->isEmpty())
    <div class="bg-white rounded-xl border border-secondary/20 p-10 text-center shadow-sm">
        <p class="text-sm text-primary/60">Aucun comptage n'attend de contrôle.</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($sessions as $session)
            @php $ecart = (int) $session->discrepancy_amount; @endphp
            <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wide text-primary/40">
                            {{ $moduleLabels[$session->module] ?? $session->module }}
                        </div>
                        <div class="mt-0.5 text-base font-semibold text-primary">
                            {{ $session->user?->name ?? 'Agent inconnu' }}
                        </div>
                        <div class="mt-1 text-xs text-primary/50">
                            Ouverte le {{ $session->opened_at?->format('d/m/Y à H:i') }} —
                            comptage déclaré le {{ $session->updated_at?->format('d/m/Y à H:i') }}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-right">
                        <div>
                            <div class="text-[11px] text-primary/50">Théorique</div>
                            <div class="text-sm font-semibold text-primary tabular-nums">{{ $fcfa($session->theoretical_closing_amount) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-primary/50">Compté</div>
                            <div class="text-sm font-semibold text-primary tabular-nums">{{ $fcfa($session->actual_closing_amount) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-primary/50">Écart</div>
                            <div class="text-sm font-bold tabular-nums {{ $ecart === 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $ecart > 0 ? '+' : '' }}{{ $fcfa($ecart) }}
                            </div>
                        </div>
                    </div>
                </div>

                @if ($session->closing_notes)
                    <div class="mt-4 rounded-lg bg-secondary/5 px-3 py-2 text-xs text-primary/70">
                        <span class="font-semibold">Note du déclarant :</span> {{ $session->closing_notes }}
                    </div>
                @endif

                <form method="POST" action="{{ route('accounting.cash_reviews.store', $session) }}"
                      class="mt-4 flex flex-col gap-3 border-t border-secondary/15 pt-4 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <label for="notes-{{ $session->id }}" class="block text-[11px] font-semibold text-primary/60">
                            Observation du contrôle (facultatif)
                        </label>
                        <input type="text" id="notes-{{ $session->id }}" name="witness_notes" maxlength="1000"
                               placeholder="Recomptage effectué en présence de l'agent…"
                               class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs text-primary focus:border-primary focus:outline-none">
                    </div>
                    <button type="submit"
                            class="shrink-0 rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-white transition-colors hover:bg-primary/90">
                        Contresigner le comptage
                    </button>
                </form>

                <p class="mt-2 text-[11px] text-primary/40">
                    Les montants ne sont pas modifiables ici : un désaccord se règle avec l'agent,
                    corriger le comptage effacerait ce que le contrôle fait apparaître.
                </p>
            </div>
        @endforeach
    </div>
@endif
@endsection
