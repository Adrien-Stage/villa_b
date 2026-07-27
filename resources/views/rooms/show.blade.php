@extends('layouts.hotel')

@section('title', 'Chambre ' . $room->number)

@section('content')

    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('rooms.index') }}"
               class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Retour aux chambres
            </a>
            <h1 class="font-heading text-2xl font-semibold text-primary">
                Chambre {{ $room->number }}
            </h1>
            <p class="text-sm text-primary/50 mt-0.5">{{ $room->roomType->name }} — Étage {{ $room->floor ?? 'N/A' }}</p>
        </div>

        {{-- Badge statut --}}
        <span class="px-3 py-1.5 rounded-full text-xs font-semibold
            {{ $room->status->value === 'available'    ? 'bg-green-100 text-green-700'  : '' }}
            {{ $room->status->value === 'occupied'     ? 'bg-blue-100 text-blue-700'    : '' }}
            {{ $room->status->value === 'cleaning'     ? 'bg-yellow-100 text-yellow-700': '' }}
            {{ $room->status->value === 'maintenance'  ? 'bg-orange-100 text-orange-700': '' }}
            {{ $room->status->value === 'out_of_order' ? 'bg-red-100 text-red-700'      : '' }}">
            {{ $room->status->label() }}
        </span>
    </div>

    <div class="grid grid-cols-3 gap-4">

        {{-- Infos chambre --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Informations</h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Type</dt>
                    <dd class="text-xs font-medium text-primary">{{ $room->roomType->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Numéro</dt>
                    <dd class="text-xs font-medium text-primary">{{ $room->number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Étage</dt>
                    <dd class="text-xs font-medium text-primary">{{ $room->floor ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Vue</dt>
                    <dd class="text-xs font-medium text-primary capitalize">{{ $room->view_type ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Capacité</dt>
                    <dd class="text-xs font-medium text-primary">{{ $room->roomType->max_capacity }} pers. max</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Prix / nuit</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ number_format($room->roomType->base_price / 100, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Changer le statut --}}
        @role('housekeeping_leader', 'housekeeping_staff', 'housekeeping', 'manager', 'reception')
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Changer le statut</h2>

            @if(session('success'))
                <div class="mb-3 text-xs text-green-600 bg-green-50 px-3 py-2 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-3 text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('rooms.updateStatus', $room) }}" class="expect-popup">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs text-primary/50 mb-1.5">Nouveau statut</label>
                    <select name="status"
                            class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                        @php
                            $user = auth()->user();
                            $isHousekeepingOnly = $user->hasAnyRole(['housekeeping', 'housekeeping_chief', 'housekeeping_staff', 'housekeeping_leader']) && !$user->hasAnyRole(['manager', 'reception']);
                            $housekeepingStatuses = [
                                \App\Enums\RoomStatus::DIRTY,
                                \App\Enums\RoomStatus::CLEANING,
                                \App\Enums\RoomStatus::CLEAN,
                                \App\Enums\RoomStatus::INSPECTED,
                                \App\Enums\RoomStatus::MAINTENANCE,
                            ];
                        @endphp
                        @foreach(\App\Enums\RoomStatus::cases() as $status)
                            @if($isHousekeepingOnly && !in_array($status, $housekeepingStatuses))
                                @continue
                            @endif
                            <option value="{{ $status->value }}"
                                {{ $room->status === $status ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-xs text-primary/50 mb-1.5">Raison (optionnel)</label>
                    <input type="text" name="reason"
                           placeholder="Ex: Check-out client Martin"
                           class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary placeholder:text-primary/30">
                </div>
                <button type="submit"
                        class="w-full py-2 rounded-lg text-xs font-semibold bg-primary text-secondary hover:bg-surface-dark transition-colors">
                    Mettre à jour
                </button>
            </form>
        </div>
        @endrole

        {{-- Historique des statuts --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Historique</h2>
            </div>

            @if($room->statusHistory->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-primary/30">
                    <p class="text-xs">Aucun changement enregistré</p>
                </div>
            @else
                <div class="divide-y divide-secondary/10">
                    @foreach($room->statusHistory as $history)
                        <div class="px-5 py-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs text-primary/50">{{ $history->from_status->label() }}</span>
                                <svg class="w-3 h-3 text-primary/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-xs font-medium text-primary">{{ $history->to_status->label() }}</span>
                            </div>
                            @if($history->reason)
                                <p class="text-xs text-primary/50 italic">{{ $history->reason }}</p>
                            @endif
                            <p class="text-[10px] text-primary/30 mt-1">
                                {{ $history->changed_at->locale('fr')->diffForHumans() }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

@endsection
