{{-- Badges d'une demande : priorité et statut. --}}
@php
    $priorites = [
        'urgent' => 'border-red-200 bg-red-50 text-red-700',
        'high'   => 'border-orange-200 bg-orange-50 text-orange-700',
        'normal' => 'border-slate-200 bg-slate-50 text-slate-600',
        'low'    => 'border-slate-200 bg-white text-slate-500',
    ];
    $statuts = [
        'open'        => 'border-amber-200 bg-amber-50 text-amber-800',
        'assigned'    => 'border-sky-200 bg-sky-50 text-sky-700',
        'in_progress' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        'submitted'   => 'border-purple-200 bg-purple-50 text-purple-700 font-bold',
        'done'        => 'border-green-200 bg-green-50 text-green-700',
        'cancelled'   => 'border-slate-200 bg-slate-50 text-slate-500',
    ];
@endphp
@if($priorite ?? true)
    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $priorites[$demande->priority] ?? '' }}">{{ $demande->priorityLabel() }}</span>
@endif
@if($statut ?? true)
    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $statuts[$demande->status] ?? '' }}">{{ $demande->statusLabel() }}</span>
@endif
