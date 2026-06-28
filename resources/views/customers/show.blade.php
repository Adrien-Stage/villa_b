@extends('layouts.hotel')

@section('title', $customer->full_name)

@section('content')

{{-- Retour --}}
<a href="{{ route('customers.index') }}"
   class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-5">
    <i data-lucide="arrow-left" class="w-3 h-3"></i>
    Retour aux clients
</a>

{{-- Messages --}}
@if(session('success'))
<div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- En-tête fiche client --}}
<div class="bg-white rounded-xl shadow-sm p-6 mb-5">
    <div class="flex items-start justify-between">

        {{-- Identité --}}
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xl font-heading font-semibold">
                    {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
                </span>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h1 class="font-heading text-2xl font-semibold text-primary">{{ $customer->full_name }}</h1>
                    @if($customer->is_vip)
                        <span class="flex items-center gap-1 px-2 py-0.5 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full text-xs font-medium">
                            <i data-lucide="star" class="w-3 h-3"></i> VIP
                        </span>
                    @endif
                    @if($customer->is_blacklisted)
                        <span class="flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-700 border border-red-200 rounded-full text-xs font-medium">
                            <i data-lucide="ban" class="w-3 h-3"></i> Blacklisté
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-4 text-sm text-primary/50">
                    @if($customer->email)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                            {{ $customer->email }}
                        </span>
                    @endif
                    @if($customer->phone)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                            {{ $customer->phone }}
                        </span>
                    @endif
                    @if($customer->nationality)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                            {{ $customer->nationality }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Badge fidélité --}}
        @php
            $levelConfig = [
                'platinum' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'icon' => 'award'],
                'gold'     => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'icon' => 'medal'],
                'silver'   => ['bg' => 'bg-gray-50',   'text' => 'text-gray-600',   'border' => 'border-gray-200',   'icon' => 'shield'],
                'bronze'   => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'icon' => 'shield'],
            ];
            $lc = $levelConfig[$customer->loyalty_level] ?? $levelConfig['bronze'];
        @endphp
        <div class="flex flex-col items-end gap-2.5">
            <div class="inline-flex items-center gap-2 px-4 py-2 {{ $lc['bg'] }} {{ $lc['text'] }} border {{ $lc['border'] }} rounded-xl">
                <i data-lucide="{{ $lc['icon'] }}" class="w-4 h-4"></i>
                <div>
                    <p class="text-xs font-semibold capitalize">{{ $customer->loyalty_level }}</p>
                    <p class="text-lg font-heading font-bold leading-none">
                        {{ number_format($customer->loyalty_points) }}
                        <span class="text-xs font-normal">pts</span>
                    </p>
                </div>
            </div>
            @role('reception', 'manager')
            <a href="{{ route('customers.edit', $customer) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors shadow-sm">
                <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                Modifier le profil
            </a>
            @endrole
        </div>
    </div>
</div>

{{-- Métriques --}}
<div class="grid grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl p-4 shadow-sm text-center">
        <p class="text-2xl font-heading font-semibold text-primary">{{ $customer->bookings->count() }}</p>
        <p class="text-xs text-primary/50 mt-1">Réservations</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm text-center">
        <p class="text-2xl font-heading font-semibold text-primary">{{ $customer->total_nights_stayed }}</p>
        <p class="text-xs text-primary/50 mt-1">Nuits séjournées</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm text-center">
        <p class="text-2xl font-heading font-semibold text-primary">
            {{ number_format($customer->total_spent / 100, 0, ',', ' ') }}
        </p>
        <p class="text-xs text-primary/50 mt-1">FCFA dépensés</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm text-center">
        <p class="text-2xl font-heading font-semibold text-primary">{{ $customer->loyalty_points }}</p>
        <p class="text-xs text-primary/50 mt-1">Points disponibles</p>
    </div>
</div>

<div class="grid grid-cols-3 gap-5">

    {{-- Historique des réservations (2/3) --}}
    <div class="col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm">Historique des séjours</h2>
        </div>

        @if($customer->bookings->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 text-primary/30">
                <i data-lucide="calendar" class="w-8 h-8 mb-2 opacity-40"></i>
                <p class="text-sm">Aucun séjour enregistré</p>
            </div>
        @else
            <div class="divide-y divide-secondary/10">
                @foreach($customer->bookings as $booking)
                    @php
                        $statusColors = [
                            'completed'   => 'bg-gray-50 text-gray-600 border-gray-200',
                            'confirmed'   => 'bg-blue-50 text-blue-700 border-blue-200',
                            'checked_in'  => 'bg-green-50 text-green-700 border-green-200',
                            'checked_out' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'cancelled'   => 'bg-red-50 text-red-600 border-red-200',
                            'pending'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                        ];
                        $sc = $statusColors[$booking->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
                    @endphp
                    <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-accent/10 transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-primary">{{ $booking->booking_number }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $sc }}">
                                    {{ $booking->status->label() }}
                                </span>
                            </div>
                            <p class="text-xs text-primary/50 mt-0.5">
                                Chambre {{ $booking->room->number }} —
                                {{ $booking->room->roomType->name }}
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-medium text-primary">
                                {{ $booking->check_in->locale('fr')->isoFormat('D MMM') }}
                                → {{ $booking->check_out->locale('fr')->isoFormat('D MMM YYYY') }}
                            </p>
                            <p class="text-xs text-primary/40">{{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }}</p>
                        </div>
                        <div class="text-right flex-shrink-0 w-28">
                            <p class="text-xs font-semibold text-primary">
                                {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Transactions fidélité (1/3) --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm">Points fidélité</h2>
        </div>

        {{-- Progression vers le prochain niveau --}}
        @php
            $nextLevels = [
                'bronze'   => ['next' => 'Silver',   'threshold' => 5000000,   'current_threshold' => 0],
                'silver'   => ['next' => 'Gold',     'threshold' => 20000000,  'current_threshold' => 5000000],
                'gold'     => ['next' => 'Platinum', 'threshold' => 50000000,  'current_threshold' => 20000000],
                'platinum' => ['next' => null,        'threshold' => 50000000,  'current_threshold' => 50000000],
            ];
            $nl = $nextLevels[$customer->loyalty_level];
            $progress = $nl['next']
                ? min(100, round((($customer->total_spent - $nl['current_threshold']) / ($nl['threshold'] - $nl['current_threshold'])) * 100))
                : 100;
        @endphp

        @if($nl['next'])
            <div class="px-5 py-4 border-b border-secondary/10">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs text-primary/50">Vers {{ $nl['next'] }}</span>
                    <span class="text-xs font-medium text-primary">{{ $progress }}%</span>
                </div>
                <div class="h-1.5 bg-accent/40 rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all" style="width: {{ $progress }}%"></div>
                </div>
                <p class="text-xs text-primary/40 mt-1.5">
                    {{ number_format(($nl['threshold'] - $customer->total_spent) / 100, 0, ',', ' ') }} FCFA restants
                </p>
            </div>
        @endif

        {{-- Liste transactions --}}
        @if($customer->loyaltyTransactions->isEmpty())
            <div class="flex flex-col items-center justify-center py-10 text-primary/30">
                <i data-lucide="gift" class="w-7 h-7 mb-2 opacity-40"></i>
                <p class="text-xs">Aucune transaction</p>
            </div>
        @else
            <div class="divide-y divide-secondary/10">
                @foreach($customer->loyaltyTransactions as $tx)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-xs font-medium text-primary">{{ $tx->description ?? $tx->type }}</p>
                            <p class="text-[10px] text-primary/40">
                                {{ $tx->created_at->locale('fr')->isoFormat('D MMM YYYY') }}
                            </p>
                        </div>
                        <span class="text-sm font-semibold {{ $tx->points > 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $tx->points > 0 ? '+' : '' }}{{ $tx->points }} pts
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

@endsection