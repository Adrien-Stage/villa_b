@extends('layouts.hotel')

@section('title', 'Demandes — Économat')

@section('content')
@php
    $statusStyles = [
        'pending' => 'bg-blue-50 text-blue-700', 'approved' => 'bg-indigo-50 text-indigo-700',
        'rejected' => 'bg-red-50 text-red-700', 'delivered' => 'bg-green-50 text-green-700',
        'cancelled' => 'bg-gray-100 text-gray-600',
    ];
@endphp
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $isKeeper ? 'Demandes des départements' : 'Mes demandes à l\'économat' }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $isKeeper ? 'Validez et livrez les demandes de matériel des départements.' : 'Sollicitez des articles auprès du magasin central.' }}
            </p>
        </div>
        <a href="{{ route('economat.requisitions.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle demande
        </a>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($requisitions->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">Aucune demande.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Numéro</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Département</th>
                            @if($isKeeper)<th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Demandeur</th>@endif
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Statut</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($requisitions as $req)
                            <tr class="hover:bg-accent/5 cursor-pointer" onclick="window.location='{{ route('economat.requisitions.show', $req) }}'">
                                <td class="px-5 py-3 font-mono text-primary">{{ $req->number }}</td>
                                <td class="px-5 py-3 text-primary/70">{{ $req->departmentLabel() }}</td>
                                @if($isKeeper)<td class="px-5 py-3 text-primary/60 text-xs">{{ $req->requestedBy?->name ?? '—' }}</td>@endif
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusStyles[$req->status] ?? 'bg-gray-100' }}">{{ $req->statusLabel() }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $req->lines->count() }}</td>
                                <td class="px-5 py-3 text-xs text-primary/40">{{ $req->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
