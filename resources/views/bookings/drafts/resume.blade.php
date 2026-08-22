@extends('layouts.hotel')

@section('title', 'Reprendre la réservation')

@section('content')
<div class="max-w-2xl mx-auto">

    {{-- En-tête --}}
    <div class="mb-6">
        <a href="{{ route('bookings.drafts.index') }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour aux brouillons
        </a>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <i data-lucide="file-edit" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="font-heading text-2xl font-semibold text-primary">Reprendre la réservation</h1>
                <p class="text-sm text-primary/50 mt-0.5">
                    Brouillon enregistré · Étape {{ $draft->current_step }}/4 — {{ $draft->stepLabel() }}
                </p>
            </div>
        </div>
    </div>

    {{-- Bannière d'information brouillon --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
        <i data-lucide="clock" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
        <div class="text-sm">
            <p class="font-semibold text-amber-800 mb-0.5">Session de réservation interrompue</p>
            <p class="text-amber-700 text-xs">
                Dernière activité : {{ $draft->last_activity_at?->locale('fr')->diffForHumans() ?? 'inconnue' }}
                @if($draft->expires_at)
                    · Expire {{ $draft->expires_at->locale('fr')->diffForHumans() }}
                @endif
            </p>
        </div>
    </div>

    {{-- Récapitulatif des données enregistrées --}}
    <div class="bg-white rounded-xl shadow-sm p-5 mb-6 space-y-4">
        <h2 class="font-heading font-semibold text-primary text-base">Données enregistrées</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            {{-- Client --}}
            <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg">
                <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="user" class="w-3.5 h-3.5 text-primary/60"></i>
                </div>
                <div>
                    <p class="text-[10px] text-primary/40 uppercase font-semibold tracking-wider">Client</p>
                    <p class="font-medium text-primary text-sm">
                        {{ $draft->customer?->full_name ?? '—' }}
                    </p>
                </div>
            </div>

            {{-- Mandataire --}}
            @if($draft->booker)
                <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="users" class="w-3.5 h-3.5 text-primary/60"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-primary/40 uppercase font-semibold tracking-wider">Mandataire</p>
                        <p class="font-medium text-primary text-sm">{{ $draft->booker->full_name }}</p>
                    </div>
                </div>
            @endif

            {{-- Dates --}}
            @if($draft->check_in && $draft->check_out)
                <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-primary/60"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-primary/40 uppercase font-semibold tracking-wider">Séjour</p>
                        <p class="font-medium text-primary text-sm">
                            {{ \Carbon\Carbon::parse($draft->check_in)->locale('fr')->isoFormat('D MMM YYYY') }}
                            @if($draft->check_in_time) <span class="text-xs text-primary/60">({{ $draft->check_in_time }})</span>@endif
                            → {{ \Carbon\Carbon::parse($draft->check_out)->locale('fr')->isoFormat('D MMM YYYY') }}
                        </p>
                        @php $nights = \Carbon\Carbon::parse($draft->check_in)->diffInDays(\Carbon\Carbon::parse($draft->check_out)); @endphp
                        <p class="text-xs text-primary/50">{{ $nights }} nuit{{ $nights > 1 ? 's' : '' }}</p>
                    </div>
                </div>
            @endif

            {{-- Occupants --}}
            @if($draft->adults)
                <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="users" class="w-3.5 h-3.5 text-primary/60"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-primary/40 uppercase font-semibold tracking-wider">Occupants</p>
                        <p class="font-medium text-primary text-sm">
                            {{ $draft->adults }} adulte{{ $draft->adults > 1 ? 's' : '' }}
                            @if($draft->children > 0), {{ $draft->children }} enfant{{ $draft->children > 1 ? 's' : '' }}@endif
                        </p>
                    </div>
                </div>
            @endif

            {{-- Chambre sélectionnée --}}
            @if($draft->room)
                <div class="flex items-center gap-2 p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                    <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="hotel" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-emerald-700/60 uppercase font-semibold tracking-wider">Chambre sélectionnée</p>
                        <p class="font-semibold text-emerald-800 text-sm">
                            Chambre {{ $draft->room->number }}
                            @if($draft->room->roomType) · {{ $draft->room->roomType->name }}@endif
                        </p>
                    </div>
                </div>
            @endif

            {{-- Source --}}
            @if($draft->source)
                <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="tag" class="w-3.5 h-3.5 text-primary/60"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-primary/40 uppercase font-semibold tracking-wider">Origine</p>
                        <p class="font-medium text-primary text-sm capitalize">{{ $draft->source }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Indicateur d'étapes avec progression --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <div class="flex items-center gap-2 mb-3">
            <i data-lucide="list-ordered" class="w-4 h-4 text-primary/50"></i>
            <span class="text-xs font-semibold text-primary/60 uppercase tracking-wider">Progression du wizard</span>
        </div>
        <div class="flex items-center gap-2">
            @php
                $steps = [
                    1 => ['label' => 'Client', 'icon' => 'user'],
                    2 => ['label' => 'Dates', 'icon' => 'calendar'],
                    3 => ['label' => 'Chambre', 'icon' => 'hotel'],
                    4 => ['label' => 'Confirmation', 'icon' => 'check-circle'],
                ];
            @endphp
            @foreach($steps as $num => $step)
                <div class="flex items-center gap-1.5 {{ !$loop->first ? 'flex-1' : '' }}">
                    @if(!$loop->first)
                        <div class="flex-1 h-px {{ $num <= $draft->current_step ? 'bg-primary' : 'bg-slate-200' }}"></div>
                    @endif
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                            {{ $num < $draft->current_step ? 'bg-green-500 text-white' : ($num === $draft->current_step ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-400') }}">
                            @if($num < $draft->current_step)
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span class="text-[10px] {{ $num === $draft->current_step ? 'text-amber-700 font-semibold' : 'text-primary/40' }} whitespace-nowrap">
                            {{ $step['label'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Actions de reprise --}}
    <div class="flex flex-col sm:flex-row gap-3">

        {{-- Bouton de reprise à l'étape courante --}}
        @if($draft->current_step >= 2 && $draft->customer_id)
            @php
                $resumeParams = [
                    'customer_id'   => $draft->customer_id,
                    'booker_id'     => $draft->booker_id,
                    'check_in'      => $draft->check_in?->format('Y-m-d'),
                    'check_out'     => $draft->check_out?->format('Y-m-d'),
                    'check_in_time' => $draft->check_in_time,
                    'adults'        => $draft->adults,
                    'children'      => $draft->children ?? 0,
                    'source'        => $draft->source,
                    'draft_token'   => $draft->token,
                ];
            @endphp
            <a href="{{ route('bookings.create', $resumeParams) }}"
               class="flex-1 flex items-center justify-center gap-2 px-5 py-3 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="play" class="w-4 h-4"></i>
                Reprendre à l'étape {{ $draft->current_step - 1 }}
                <span class="text-xs font-normal text-white/70">— {{ $draft->stepLabel() }}</span>
            </a>
        @else
            <a href="{{ route('bookings.create') }}"
               class="flex-1 flex items-center justify-center gap-2 px-5 py-3 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="play" class="w-4 h-4"></i>
                Reprendre la réservation
            </a>
        @endif

        {{-- Supprimer le brouillon --}}
        <form method="POST" action="{{ route('bookings.drafts.destroy', $draft->token) }}"
              onsubmit="return confirm('Supprimer définitivement ce brouillon ?')">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-3 bg-white border border-red-200 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50 transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Supprimer ce brouillon
            </button>
        </form>
    </div>

</div>
@endsection
