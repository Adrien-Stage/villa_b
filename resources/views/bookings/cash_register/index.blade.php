@extends('layouts.hotel')

@section('title', 'Comptabilité Réception')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Comptabilité Réception</h1>
            <p class="text-secondary mt-1">Historique des sessions de caisse de réception</p>
        </div>
        
        @php
            $activeSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
                ->where('tenant_id', auth()->user()->tenant->id)
                ->where('module', 'reception')
                ->whereNull('closed_at')
                ->first();
        @endphp
        
        <div>
            @if ($activeSession)
                @role('manager')
                    <a href="{{ route('bookings.cash_register.close') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors shadow-sm">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                        Fermer la caisse
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm font-medium rounded-lg">
                        <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span>
                        Caisse ouverte par vous
                    </span>
                @endrole
            @else
                <a href="{{ route('bookings.cash_register.open') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="lock-open" class="w-4 h-4"></i>
                    Ouvrir la caisse
                </a>
            @endif
        </div>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif
    @if ($message = session('info'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800">
            <i data-lucide="info" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif
    @if ($message = session('warning'))
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
            <i data-lucide="alert-triangle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif

    <!-- Tableau des sessions de caisse -->
    <div class="bg-white rounded-lg shadow-sm border border-secondary/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Session & Réceptionniste</th>
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
