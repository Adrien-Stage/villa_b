{{-- Tableau de demandes d'intervention. Paramètres : $demandes, $vide, $prendre (bouton « Prendre »). --}}
<x-table :rows="$demandes" :empty="($vide ?? null) ?: 'Aucune demande.'" empty-icon="wrench" caption="Demandes d'intervention">
    <x-slot:head>
        <x-table.col>Demande</x-table.col>
        <x-table.col>Lieu</x-table.col>
        <x-table.col hide="md">Spécialité</x-table.col>
        <x-table.col>État</x-table.col>
        <x-table.col hide="lg">Technicien</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($demandes as $demande)
        <x-table.row>
            <x-table.cell>
                <a href="{{ route('technique.demandes.show', $demande) }}" class="font-medium text-primary hover:underline">{{ $demande->title }}</a>
                <p class="text-[11px] text-primary/50">
                    {{ $demande->reference }} · {{ $demande->created_at->format('d/m H:i') }}
                    @if($demande->source === \App\Models\MaintenanceRequest::SOURCE_HOUSEKEEPING) · housekeeping @endif
                    @if($demande->reopened_count) · rouverte @endif
                </p>
            </x-table.cell>
            <x-table.cell class="text-sm">{{ $demande->lieu() }}</x-table.cell>
            <x-table.cell hide="md" class="text-xs text-primary/70">{{ $demande->specialtyLabel() }}</x-table.cell>
            <x-table.cell nowrap>
                <span class="inline-flex flex-wrap gap-1">@include('technique.partials.badges')</span>
            </x-table.cell>
            <x-table.cell hide="lg" class="text-xs text-primary/70">{{ $demande->technician?->name ?? '—' }}</x-table.cell>
            <x-table.actions :label="'Actions pour '.$demande->reference">
                <x-table.action :href="route('technique.demandes.show', $demande)" icon="eye">Ouvrir</x-table.action>
                @if(($prendre ?? false) && $demande->status === \App\Models\MaintenanceRequest::STATUS_OPEN && auth()->user()->exerce(['technician']))
                    <x-table.action :action="route('technique.demandes.take', $demande)" icon="hand" tone="success">Prendre</x-table.action>
                @endif
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
