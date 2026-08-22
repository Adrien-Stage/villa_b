@extends('layouts.hotel')

@section('title', 'Réservations en cours — Brouillons')

@section('content')
<div class="max-w-3xl mx-auto">

    {{-- En-tête --}}
    <div class="mb-6 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('bookings.index') }}" class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
                <i data-lucide="arrow-left" class="w-3 h-3"></i>
                Retour aux réservations
            </a>
            <h1 class="font-heading text-2xl font-semibold text-primary">Brouillons de réservation</h1>
            <p class="text-sm text-primary/50 mt-0.5">Sessions en cours non finalisées</p>
        </div>
        <a href="{{ route('bookings.create') }}"
           class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors flex-shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nouvelle réservation
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($drafts->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i data-lucide="file-clock" class="w-10 h-10 text-primary/20 mx-auto mb-3"></i>
            <p class="text-sm font-medium text-primary/60">Aucun brouillon de réservation en cours</p>
            <p class="text-xs text-primary/40 mt-1">Les sessions de réservation démarrées mais non finalisées apparaîtront ici.</p>
            <a href="{{ route('bookings.create') }}"
               class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Créer une réservation
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($drafts as $draft)
                <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-4 flex items-center justify-between gap-4 hover:border-secondary/30 transition-colors">
                    <div class="flex items-center gap-3.5 min-w-0">
                        {{-- Icône d'étape --}}
                        <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="file-edit" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-primary truncate">
                                {{ $draft->summary() ?: 'Brouillon vide' }}
                            </p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                {{-- Badge étape --}}
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                                    <i data-lucide="list-ordered" class="w-3 h-3"></i>
                                    Étape {{ $draft->current_step }}/4 · {{ $draft->stepLabel() }}
                                </span>
                                {{-- Dernière activité --}}
                                <span class="text-xs text-primary/40">
                                    Modifié {{ $draft->last_activity_at?->locale('fr')->diffForHumans() ?? 'récemment' }}
                                </span>
                                {{-- Expiration --}}
                                @if($draft->expires_at)
                                    <span class="text-xs text-primary/40">
                                        · Expire {{ $draft->expires_at->locale('fr')->diffForHumans() }}
                                    </span>
                                @endif
                            </div>

                            {{-- Aperçu rapide des données renseignées --}}
                            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                @if($draft->customer)
                                    <span class="flex items-center gap-1 text-xs text-primary/60">
                                        <i data-lucide="user" class="w-3 h-3 text-primary/30"></i>
                                        {{ $draft->customer->full_name }}
                                    </span>
                                @endif
                                @if($draft->check_in && $draft->check_out)
                                    <span class="flex items-center gap-1 text-xs text-primary/60">
                                        <i data-lucide="calendar" class="w-3 h-3 text-primary/30"></i>
                                        {{ \Carbon\Carbon::parse($draft->check_in)->locale('fr')->isoFormat('D MMM') }}
                                        → {{ \Carbon\Carbon::parse($draft->check_out)->locale('fr')->isoFormat('D MMM YYYY') }}
                                    </span>
                                @endif
                                @if($draft->room)
                                    <span class="flex items-center gap-1 text-xs text-primary/60">
                                        <i data-lucide="hotel" class="w-3 h-3 text-primary/30"></i>
                                        Chambre {{ $draft->room->number }}
                                        @if($draft->room->roomType) · {{ $draft->room->roomType->name }}@endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Bouton Reprendre direct --}}
                        <a href="{{ route('bookings.drafts.continue', $draft->token) }}"
                           class="flex items-center gap-1.5 px-3.5 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                            <i data-lucide="play" class="w-3.5 h-3.5"></i>
                            Reprendre
                        </a>
                        {{-- Bouton Supprimer --}}
                        <form method="POST" action="{{ route('bookings.drafts.destroy', $draft->token) }}"
                              onsubmit="return confirm('Supprimer ce brouillon ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Supprimer le brouillon">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
