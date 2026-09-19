{{--
    Séparation des tâches : le cumul refusé, et la dérogation qui l'autorise.

    Quatre fonctions doivent rester dans des mains différentes — autoriser,
    détenir, enregistrer, contrôler. Le bloc n'apparaît qu'après un refus :
    tant que les rôles cochés sont compatibles, rien ne s'affiche.
--}}
@if(session('duty_segregation_conflits'))
    <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
        <p class="text-xs font-semibold text-amber-900">Ce cumul de rôles casse la séparation des tâches</p>

        <ul class="mt-2 space-y-1.5">
            @foreach(session('duty_segregation_conflits') as $conflit)
                <li class="text-[11px] leading-relaxed text-amber-800">• {{ $conflit }}</li>
            @endforeach
        </ul>

        <p class="mt-3 text-[11px] leading-relaxed text-amber-700">
            Retirez l'un des rôles, ou accordez la dérogation si votre établissement
            est trop petit pour séparer ces fonctions. Elle sera consignée au journal
            avec son motif et votre nom.
        </p>

        <label class="mt-3 inline-flex items-center gap-2 text-xs font-medium text-amber-900">
            <input type="checkbox" name="derogation" value="1" @checked(old('derogation'))
                   onchange="this.closest('div').querySelector('[name=derogation_motif]').toggleAttribute('required', this.checked)">
            J'accorde la dérogation malgré ce cumul
        </label>

        <input type="text" name="derogation_motif" value="{{ old('derogation_motif') }}"
               placeholder="Motif — ex. : établissement de six personnes, contrôle mensuel du directeur"
               class="mt-2 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs outline-none focus:border-amber-500">

        @error('derogation_motif')
            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endif
