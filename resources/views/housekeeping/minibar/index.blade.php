@extends('layouts.hotel')

@section('title', 'Mini-bars — Housekeeping')

@section('content')
@php
    $fcfa = fn (int $c) => number_format($c / 100, 0, ',', ' ');
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="wine" class="w-7 h-7 text-primary"></i>
                <span>Mini-bars</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Le mini-bar de chaque chambre : son contenu face à sa dotation, son dernier relevé.
                Ce que l'équipe relève consommé part au folio du client.
            </p>
        </div>
        <a href="{{ route('housekeeping.index') }}"
           class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shrink-0">
            <i data-lucide="sparkles" class="w-4 h-4"></i> Housekeeping
        </a>
    </div>

    @include('housekeeping.partials.flash')

    {{-- Les réglages (dépôt, articles, prix, dotations) sont dans Paramètres › Housekeeping. --}}
    @if(\App\Support\SettingsTabs::peutOuvrir(auth()->user(), 'housekeeping'))
        <div class="rounded-xl border border-secondary/20 bg-white px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-primary/70">Articles, prix, dépôt et dotations des mini-bars se règlent dans les paramètres du housekeeping.</p>
            <a href="{{ route('settings.index', ['tab' => 'housekeeping']) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                <i data-lucide="settings" class="w-4 h-4"></i> Réglages du mini-bar
            </a>
        </div>
    @endif

    {{-- ═══ État des mini-bars ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm">Mini-bars des chambres</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                    <tr>
                        <th class="px-5 py-2 text-left font-semibold">Chambre</th>
                        <th class="px-3 py-2 text-left font-semibold">Statut</th>
                        <th class="px-3 py-2 text-right font-semibold">Contenu</th>
                        <th class="px-3 py-2 text-left font-semibold">État</th>
                        <th class="px-5 py-2 text-left font-semibold">Dernier relevé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($chambres as $chambre)
                        @php
                            $dotation = $chambre->minibarLines->sum('standard_quantity');
                            $present = $chambre->minibarLines->sum('quantity');
                            $releve = $derniersReleves[$chambre->id] ?? null;
                        @endphp
                        <tr>
                            <td class="px-5 py-2">
                                <a href="{{ route('housekeeping.minibar.rooms.show', $chambre) }}" class="font-semibold text-primary hover:underline">{{ $chambre->number }}</a>
                                <span class="text-xs text-primary/45">{{ $chambre->roomType?->name }}</span>
                            </td>
                            <td class="px-3 py-2 text-primary/70">{{ $chambre->status->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $dotation > 0 ? "{$present} / {$dotation}" : '—' }}</td>
                            <td class="px-3 py-2">
                                @if($dotation === 0)
                                    <span class="text-xs text-primary/35">Sans mini-bar</span>
                                @elseif($chambre->minibar_due_booking_id)
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-red-50 text-red-700 border-red-200">Relevé dû</span>
                                @elseif($chambre->miniBarVide())
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-amber-50 text-amber-800 border-amber-200">{{ $chambre->status === \App\Enums\RoomStatus::OCCUPIED ? 'Vidé par le client' : 'À remplir' }}</span>
                                @elseif(!$chambre->miniBarComplet())
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-amber-50 text-amber-800 border-amber-200">Incomplet</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-emerald-50 text-emerald-700 border-emerald-200">Complet</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-xs text-primary/60">
                                @if($releve)
                                    {{ $releve->checked_at->format('d/m H:i') }} · {{ $releve->user?->name }} · {{ $releve->total_amount > 0 ? $fcfa($releve->total_amount) . ' F' : 'rien consommé' }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
