@extends('layouts.hotel')

@section('title', 'Banquet ' . $banquet->reference)

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $libellesModes = ['cash' => 'Espèces', 'mobile_money' => 'Mobile money', 'card' => 'Carte', 'transfer' => 'Virement', 'other' => 'Autre'];
    $couleurs = ['devis' => 'bg-amber-50 text-amber-800', 'confirme' => 'bg-sky-50 text-sky-800', 'realise' => 'bg-violet-50 text-violet-800', 'solde' => 'bg-green-50 text-green-700', 'annule' => 'bg-gray-100 text-gray-500'];
    $couleursSource = [
        'sur_place' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'telephone' => 'bg-cyan-50 text-cyan-800 border-cyan-200',
        'email' => 'bg-slate-50 text-slate-700 border-slate-200',
        'restaurant' => 'bg-amber-50 text-amber-800 border-amber-200',
        'site_web' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    ];
    $acompteManquant = $banquet->depositRemaining();
    $montantSuggere = $acompteManquant > 0
        ? (int) ceil($acompteManquant / 100)
        : (int) floor($resteDu / 100);
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('restaurant.banquets.index') }}" class="text-xs text-primary/50 hover:text-primary">&larr; Banquets</a>
        <div class="flex flex-wrap items-center gap-2 mt-1">
            <h1 class="font-heading text-2xl font-semibold text-primary">{{ $banquet->title }}</h1>
            <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $couleursSource[$banquet->source] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                {{ $banquet->libelleSource() }}
            </span>
        </div>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $banquet->reference }} · {{ $banquet->event_date->format('d/m/Y') }}
            @if($banquet->start_time) · {{ $banquet->start_time }}@if($banquet->end_time)–{{ $banquet->end_time }}@endif @endif
            · {{ $banquet->pointOfSale?->name }}@if($banquet->space) · {{ $banquet->space->name }}@if($banquet->space->capacity) ({{ $banquet->space->capacity }} places)@endif @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $couleurs[$banquet->status] ?? '' }}">{{ $banquet->libelleStatut() }}</span>
        <a href="{{ route('restaurant.banquets.print', $banquet) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/25 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/20">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            Imprimer devis / reçu
        </a>
        @droit('restaurant.banquets.status')
            @php
                $actions = match ($banquet->status) {
                    'devis' => ['confirme' => 'Confirmer', 'annule' => 'Annuler'],
                    'confirme' => ['realise' => 'Marquer réalisé', 'annule' => 'Annuler'],
                    default => [],
                };
            @endphp
            @foreach($actions as $statut => $libelle)
                <form method="POST" action="{{ route('restaurant.banquets.status', $banquet) }}"
                    @if($statut === 'annule') onsubmit="return confirm('Annuler ce banquet ?')" @endif>
                    @csrf
                    <input type="hidden" name="status" value="{{ $statut }}">
                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer {{ $statut === 'annule' ? 'border border-red-200 bg-white text-red-600 hover:bg-red-50' : 'bg-primary text-white hover:opacity-95' }}">{{ $libelle }}</button>
                </form>
            @endforeach
        @enddroit
        @if($banquet->estModifiable())
            @droit('restaurant.banquets.modifier')
                <button type="button" onclick="document.getElementById('banquet-edit').classList.remove('hidden')"
                    class="rounded-lg border border-secondary/25 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/20 cursor-pointer">Modifier</button>
            @enddroit
        @endif
    </div>
</div>

@if(!empty($cashRegisterContext))
    @include('bookings.cash_register.partials.status_banner', ['cashRegisterContext' => $cashRegisterContext])
@endif

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Prestation</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
            <dt class="text-primary/50">Client</dt>
            <dd class="text-primary font-medium">
                @if($banquet->customer)
                    <a href="{{ route('customers.show', $banquet->customer) }}" class="text-primary underline decoration-secondary hover:text-surface-dark">
                        {{ $banquet->client_name }}
                    </a>
                    <span class="ml-1 rounded bg-secondary/15 px-1.5 py-0.5 text-[10px] font-medium text-primary/80">Fiche CRM</span>
                @else
                    {{ $banquet->client_name }}
                @endif
                @if($banquet->client_phone)<span class="font-normal text-primary/70"> · {{ $banquet->client_phone }}</span>@endif
            </dd>
            @if($banquet->client_email)
                <dt class="text-primary/50">Email</dt><dd class="text-primary">{{ $banquet->client_email }}</dd>
            @endif
            <dt class="text-primary/50">Source de demande</dt>
            <dd class="text-primary">{{ $banquet->libelleSource() }}</dd>
            @if($banquet->booking)
                <dt class="text-primary/50">Séjour résident</dt>
                <dd class="text-primary">
                    <a href="{{ route('bookings.show', $banquet->booking) }}" class="underline decoration-secondary font-medium hover:text-surface-dark">
                        {{ $banquet->booking->booking_number }}
                    </a>
                    @if($banquet->booking->room)
                        <span class="text-primary/60"> · Chambre {{ $banquet->booking->room->number }}</span>
                    @endif
                </dd>
            @endif
            <dt class="text-primary/50">Couverts</dt>
            <dd class="text-primary">{{ $banquet->covers }} × {{ $fcfa($banquet->price_per_cover) }}</dd>
            @if($banquet->extras_amount > 0)
                <dt class="text-primary/50">Suppléments</dt><dd class="text-primary">{{ $fcfa($banquet->extras_amount) }}</dd>
            @endif
            <dt class="text-primary/50">Total</dt>
            <dd class="font-semibold text-primary">{{ $fcfa($banquet->total_amount) }}</dd>
            <dt class="text-primary/50">Acompte demandé</dt>
            <dd class="text-primary">
                {{ $fcfa($banquet->deposit_required) }}
                @if($acompteManquant > 0)
                    <span class="ml-1 text-xs text-amber-700 font-medium">(manque {{ $fcfa($acompteManquant) }})</span>
                @elseif($banquet->deposit_required > 0)
                    <span class="ml-1 text-xs text-emerald-700 font-medium">· Couvert</span>
                @endif
            </dd>
            <dt class="text-primary/50">Établi par</dt>
            <dd class="text-primary">{{ $banquet->createdBy?->name ?? '—' }}, le {{ $banquet->created_at->format('d/m/Y') }}</dd>
            @if($banquet->confirmed_at)
                <dt class="text-primary/50">Confirmé le</dt>
                <dd class="text-primary">{{ $banquet->confirmed_at->format('d/m/Y H:i') }}</dd>
            @endif
        </dl>
        @if($banquet->menu)
            <h3 class="mt-5 mb-1 text-xs font-bold uppercase tracking-wider text-primary/60">Menu & Prestations</h3>
            <p class="whitespace-pre-line text-sm text-primary/80">{{ $banquet->menu }}</p>
        @endif
        @if($banquet->notes)
            <h3 class="mt-5 mb-1 text-xs font-bold uppercase tracking-wider text-primary/60">Notes</h3>
            <p class="whitespace-pre-line text-sm text-primary/70">{{ $banquet->notes }}</p>
        @endif
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Règlements</h2>
        <div class="mb-3 space-y-1 text-sm">
            <p class="text-primary flex justify-between"><span>Total banquet :</span> <strong>{{ $fcfa($banquet->total_amount) }}</strong></p>
            <p class="text-emerald-700 flex justify-between"><span>Encaissé :</span> <strong>{{ $fcfa($encaisse) }}</strong></p>
            <p class="text-primary flex justify-between"><span>Reste dû :</span> <strong>{{ $fcfa($resteDu) }}</strong></p>
        </div>

        <ul class="mb-4 space-y-2 text-xs">
            @forelse($banquet->payments as $reglement)
                <li class="flex items-start justify-between gap-2 border-b border-secondary/10 pb-2">
                    <div class="min-w-0">
                        <p class="font-medium text-primary">
                            {{ $reglement->receiptReference() }} · {{ $reglement->libelleKind() }}
                        </p>
                        <p class="text-primary/60">
                            {{ $reglement->paid_at->format('d/m/Y H:i') }} · {{ $libellesModes[$reglement->payment_method] ?? $reglement->payment_method }}
                        </p>
                        <p class="text-[11px] text-primary/50">
                            {{ $reglement->paidBy?->name ?? '—' }}
                            @if($reglement->cashRegisterSession)
                                · {{ $reglement->cashRegisterSession->module === 'reception' ? 'Caisse Réception' : 'Caisse Restaurant' }}
                            @endif
                        </p>
                        @if($reglement->notes)
                            <p class="text-[11px] italic text-primary/55">{{ $reglement->notes }}</p>
                        @endif
                    </div>
                    <span class="font-semibold text-primary whitespace-nowrap">{{ $fcfa($reglement->amount) }}</span>
                </li>
            @empty
                <li class="text-primary/45">Aucun règlement enregistré.</li>
            @endforelse
        </ul>

        @if($resteDu > 0 && ! in_array($banquet->status, ['annule', 'solde'], true))
            @droit('restaurant.banquets.payments.creer')
                @if(! $caisse)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800 space-y-2">
                        <p>Ouvrez votre caisse avant d'encaisser un règlement.</p>
                        @droit('bookings.cash_register.open')
                            <a href="{{ route('bookings.cash_register.open') }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">
                                <i data-lucide="unlock" class="w-3.5 h-3.5"></i>
                                Ouvrir ma caisse Réception
                            </a>
                        @enddroit
                    </div>
                @else
                    <div class="mb-2 rounded-lg bg-primary/5 px-3 py-1.5 text-[11px] text-primary/70">
                        Encaissement dans : <strong>{{ $caisse->module === 'reception' ? 'Caisse Réception' : 'Caisse Restaurant' }}</strong>
                        (#{{ $caisse->id }}@if($caisse->user) · {{ $caisse->user->name }}@endif)
                    </div>
                    <form method="POST" action="{{ route('restaurant.banquets.payments.store', $banquet) }}" class="space-y-2.5">
                        @csrf
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-primary/60">Montant (FCFA)</span>
                                <div class="flex gap-1.5 text-[11px]">
                                    @if($acompteManquant > 0)
                                        <button type="button" onclick="this.closest('form').querySelector('[name=amount]').value = {{ (int) ceil($acompteManquant / 100) }}"
                                            class="underline text-primary/70 hover:text-primary cursor-pointer">Acompte requis</button>
                                    @endif
                                    <button type="button" onclick="this.closest('form').querySelector('[name=amount]').value = {{ (int) floor($resteDu / 100) }}"
                                        class="underline text-primary/70 hover:text-primary cursor-pointer">Solde complet</button>
                                </div>
                            </div>
                            <input type="number" name="amount" required min="1" max="{{ (int) floor($resteDu / 100) }}" value="{{ old('amount', $montantSuggere ?: '') }}"
                                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                        </div>
                        <label class="block">
                            <span class="text-xs text-primary/60">Mode de paiement</span>
                            <select name="payment_method" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                                @foreach($paymentMethods as $methode)
                                    <option value="{{ $methode }}" @selected(old('payment_method') === $methode)>{{ $libellesModes[$methode] ?? $methode }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs text-primary/60">Notes / Référence (optionnel)</span>
                            <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="Ex. N° chèque, référence Mobile Money..."
                                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-1.5 text-xs outline-none focus:border-secondary">
                        </label>
                        @if($banquet->status === 'devis')
                            @droit('restaurant.banquets.status')
                                <label class="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50/70 px-2.5 py-2 text-xs text-sky-900 cursor-pointer select-none">
                                    <input type="checkbox" name="confirm_now" value="1" checked
                                        class="mt-0.5 rounded border-sky-300 text-primary focus:ring-primary">
                                    <span>Confirmer automatiquement le banquet dès que l'acompte demandé est couvert</span>
                                </label>
                            @enddroit
                        @endif
                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95 cursor-pointer">
                            Encaisser
                        </button>
                    </form>
                @endif
            @enddroit
        @endif
    </section>
</div>

@if($banquet->estModifiable())
    @droit('restaurant.banquets.modifier')
        <x-modal id="banquet-edit" title="Modifier {{ $banquet->reference }}" max-width="max-w-2xl" formAction="{{ route('restaurant.banquets.update', $banquet) }}">
            @method('PUT')
            @include('restaurant.banquets._champs', ['b' => $banquet, 'restaurantParDefaut' => null])
            <x-slot:footer>
                <button type="button" onclick="document.getElementById('banquet-edit').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
            </x-slot:footer>
        </x-modal>
        @if($errors->any() && old('title') !== null && request()->route('banquet'))
            <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('banquet-edit')?.classList.remove('hidden'));</script>
        @endif
    @enddroit
@endif
@endsection
