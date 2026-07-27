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

    <!-- Tableau des sessions de caisse -->
    <div class="bg-white rounded-lg shadow-sm border border-secondary/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Session & Caissier</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">État</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Fond Départ</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Attendu (Théorique)</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Compté (Réel)</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($sessions as $session)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-accent/20 flex items-center justify-center text-primary font-bold text-xs">
                                        {{ strtoupper(substr($session->user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-primary text-sm">{{ $session->user->name }}</p>
                                        <p class="text-secondary text-xs">
                                            Ouverte: {{ $session->opened_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}
                                        </p>
                                        @if($session->closed_at)
                                            <p class="text-secondary text-[10px]">
                                                Fermée: {{ $session->closed_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($session->closed_at)
                                    <span class="bg-gray-100 text-gray-700 border border-gray-200 px-2.5 py-1 rounded-full text-xs font-medium">Clôturée</span>
                                @else
                                    <span class="bg-green-50 text-green-700 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium animate-pulse">En cours</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-600 text-sm">
                                    {{ number_format($session->opening_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                                </span>
                            </td>
                            
                            @if ($session->closed_at)
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-primary text-sm">
                                        {{ number_format($session->theoretical_closing_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-primary text-sm">
                                        {{ number_format($session->actual_closing_amount / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $gap = $session->discrepancy_amount;
                                    @endphp
                                    @if ($gap === 0)
                                        <span class="inline-flex items-center gap-1 text-green-600 font-medium text-sm">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i> Juste
                                        </span>
                                    @elseif ($gap > 0)
                                        <span class="inline-flex items-center gap-1 text-yellow-600 font-medium text-sm" title="{{ $session->closing_notes }}">
                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                            {{ number_format($gap / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-red-600 font-medium text-sm" title="{{ $session->closing_notes }}">
                                            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                            {{ number_format(abs($gap) / 100, 0, ',', ' ') }} <span class="text-xs">FCFA</span>
                                        </span>
                                    @endif
                                    
                                    @if($session->closing_notes)
                                        <i data-lucide="info" class="w-3 h-3 inline text-secondary/50 ml-1" title="{{ $session->closing_notes }}"></i>
                                    @endif
                                </td>
                            @else
                                <td colspan="3" class="px-6 py-4 text-sm text-secondary/50 italic">
                                    En attente de fermeture...
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-secondary">
                                <i data-lucide="calculator" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                                <p>Aucune session de caisse n'a été trouvée.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $sessions->links() }}
    </div>
</div>
@endsection
