@extends('layouts.hotel')

@section('title', 'Contrôle des comptages')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Contrôle des comptages de caisse</h1>
    <p class="text-sm text-primary/60 mt-1">
        Caisses comptées par leur titulaire et closes seulement après contresignature.
        Ce contrôle revient à <strong>{{ $witnessLabel }}</strong>, qui n'encaisse jamais.
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
                            {{ \App\Services\CashRegisterCircuit::libelle($session) }}
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

                {{-- Tiroir passé au quart suivant : le collègue qui l'a repris l'a recompté. --}}
                @if ($passation = $session->handoverOut)
                    <div class="mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900">
                        <span class="font-semibold">Remis en passation</span> à {{ $passation->handed_at->format('d/m H:i') }} :
                        @if ($passation->isPending())
                            en attente de reprise.
                        @else
                            repris par {{ $passation->toUser?->name }} à {{ $passation->taken_at?->format('H:i') }},
                            {{ $fcfa($passation->received_amount) }} recomptés{{ (int) $passation->discrepancy_amount !== 0 ? ' — écart à la remise : ' . $fcfa($passation->discrepancy_amount) : ', sans écart' }}.
                        @endif
                        @droit('bookings.handovers.voir')
                            <a href="{{ route('bookings.handovers.show', $passation) }}" class="underline font-semibold">Voir la passation</a>
                        @enddroit
                    </div>
                @endif

                @php $tiersReview = $tiersParSession[$session->id] ?? null; @endphp
                @if ($tiersReview && ($tiersReview['has_activity'] ?? false))
                    <div class="mt-2 rounded-lg bg-indigo-50 border border-indigo-200 px-3 py-2.5 text-xs text-indigo-950">
                        <p class="font-semibold">
                            Interventions d'un tiers dans cette caisse ({{ $tiersReview['total_count'] }} opération{{ $tiersReview['total_count'] > 1 ? 's' : '' }}) :
                            @foreach($tiersReview['actors'] as $actor)
                                {{ $actor['name'] }}@if($actor['role_label']) ({{ $actor['role_label'] }})@endif
                                @if($actor['net_cash_centimes'] !== 0)
                                    — impact espèces {{ $actor['net_cash_centimes'] > 0 ? '+' : '−' }}{{ $fcfa(abs($actor['net_cash_centimes'])) }}
                                @endif
                                @if($actor['non_cash_centimes'] > 0)
                                    · {{ $fcfa($actor['non_cash_centimes']) }} hors espèces
                                @endif
                                @if(!$loop->last) ; @endif
                            @endforeach
                        </p>
                        <details class="mt-1.5">
                            <summary class="cursor-pointer select-none font-semibold text-indigo-800 hover:text-indigo-950">
                                Voir le détail des opérations effectuées par un tiers
                            </summary>
                            <div class="mt-2 overflow-x-auto rounded border border-indigo-200 bg-white">
                                <table class="w-full text-left text-[11px]">
                                    <caption class="sr-only">Détail des interventions tiers pour la session #{{ $session->id }}</caption>
                                    <thead class="bg-indigo-50/70 text-[10px] uppercase tracking-wider text-indigo-900/70 border-b border-indigo-100">
                                        <tr>
                                            <th scope="col" class="px-2.5 py-1.5">Heure</th>
                                            <th scope="col" class="px-2.5 py-1.5">Collaborateur</th>
                                            <th scope="col" class="px-2.5 py-1.5">Opération</th>
                                            <th scope="col" class="px-2.5 py-1.5">Mode</th>
                                            <th scope="col" class="px-2.5 py-1.5 text-right">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-indigo-100/70 text-primary">
                                        @foreach($tiersReview['items'] as $item)
                                            <tr>
                                                <td class="px-2.5 py-1.5 tabular-nums whitespace-nowrap text-primary/60">{{ $item['occurred_at']?->format('H:i') ?? '—' }}</td>
                                                <td class="px-2.5 py-1.5 whitespace-nowrap font-medium">{{ $item['actor_name'] }}</td>
                                                <td class="px-2.5 py-1.5">{{ $item['description'] }}</td>
                                                <td class="px-2.5 py-1.5 whitespace-nowrap text-primary/70">{{ $item['method_label'] ?? '—' }}</td>
                                                <td class="px-2.5 py-1.5 text-right tabular-nums whitespace-nowrap font-semibold {{ $item['cash_impact'] < 0 ? 'text-red-600' : ($item['is_financial'] ? 'text-emerald-700' : 'text-primary/50') }}">
                                                    @if($item['is_financial'] && $item['amount'])
                                                        {{ $item['cash_impact'] < 0 ? '−' : ($item['cash_impact'] > 0 ? '+' : '') }}{{ $fcfa(abs((int) $item['amount'])) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
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
