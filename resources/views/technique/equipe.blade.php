@extends('layouts.hotel')

@section('title', 'Équipe technique')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-heading font-semibold text-primary">Équipe technique</h1>
        <p class="text-sm text-primary/60 mt-0.5">
            Les spécialités de chacun : une demande alerte d'abord les techniciens de sa spécialité, et l'affectation les signale.
            Les comptes se créent dans Gestion › Utilisateurs, avec le rôle « Technicien de maintenance ».
        </p>
    </div>

    @include('economat.partials.flash')
    @php($peutModifier = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'technique.equipe.modifier'))

    @if($techniciens->isEmpty())
        <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center text-sm text-primary/55">
            Aucun technicien. Donnez le rôle « Technicien de maintenance » aux comptes de l'équipe.
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @foreach($techniciens as $technicien)
            @php($siennes = $technicien->technicianSpecialties->pluck('specialty')->all())
            <form method="POST" action="{{ route('technique.equipe.update', $technicien) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-3">
                @csrf
                @method('PUT')
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-primary">{{ $technicien->name }}</p>
                        <p class="text-xs text-primary/55">{{ $charge[$technicien->id] ?? 0 }} intervention(s) en cours</p>
                    </div>
                    @if($technicien->exerce(['tech_manager']))
                        <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-700">Responsable</span>
                    @endif
                </div>
                <fieldset>
                    <legend class="sr-only">Spécialités de {{ $technicien->name }}</legend>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                        @foreach(\App\Models\MaintenanceRequest::SPECIALTIES as $cle => $libelle)
                            @continue($cle === 'autre')
                            <label class="flex items-center gap-2 text-xs text-primary/80">
                                <input type="checkbox" name="specialites[]" value="{{ $cle }}" class="rounded border-secondary/40" @checked(in_array($cle, $siennes, true))
                                       @disabled(! $peutModifier)>
                                {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @if($peutModifier)
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 border border-secondary/30 bg-white text-primary text-xs font-medium rounded-lg hover:bg-gray-50">
                        <i data-lucide="save" class="w-3.5 h-3.5" aria-hidden="true"></i> Enregistrer
                    </button>
                @endif
            </form>
        @endforeach
    </div>
</div>
@endsection
