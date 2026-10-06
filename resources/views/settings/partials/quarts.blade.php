{{--
    Onglet Quarts : les quarts de travail de l'hôtel. La direction les
    définit pour tout l'établissement ; les chefs de service y placent leur
    personnel dans le planning. Un quart dont la fin précède le début finit
    le lendemain (nuit).
--}}
@php $quarts = \App\Models\WorkShift::query()->dansLOrdre()->withCount('assignments')->get(); @endphp

<div class="max-w-3xl">
    <h2 class="text-lg font-semibold text-primary">Quarts de travail</h2>
    <p class="mt-1 text-sm text-primary/60">
        L'hôtel tourne jour et nuit : définissez ses quarts et leurs heures. Chaque chef de service y place ensuite son
        personnel, dans le <a href="{{ route('planning.index') }}" class="font-semibold text-primary underline-offset-2 hover:underline">planning</a>.
        Un quart qui finit avant l'heure où il commence se termine le lendemain.
    </p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
        </div>
    @endif

    <ul class="mt-5 space-y-3">
        @forelse($quarts as $quart)
            <li class="rounded-xl border border-secondary/20 bg-gray-50 p-4 {{ $quart->is_active ? '' : 'opacity-70' }}">
                @droit('settings.quarts.modifier')
                    <form method="POST" action="{{ route('settings.quarts.update', $quart) }}" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_7rem_7rem_auto_auto]">
                        @csrf @method('PUT')
                        <label class="block">
                            <span class="text-xs text-primary/60">Nom</span>
                            <input type="text" name="name" value="{{ $quart->name }}" required maxlength="60"
                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        </label>
                        <label class="block">
                            <span class="text-xs text-primary/60">Début</span>
                            <input type="time" name="starts_at" value="{{ $quart->debut() }}" required
                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        </label>
                        <label class="block">
                            <span class="text-xs text-primary/60">Fin</span>
                            <input type="time" name="ends_at" value="{{ $quart->fin() }}" required
                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        </label>
                        <label class="flex items-center gap-2 pb-2 text-xs text-primary">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($quart->is_active) class="rounded border-secondary/30">
                            En service
                        </label>
                        <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95">Enregistrer</button>
                    </form>
                @else
                    <p class="text-sm font-semibold text-primary">{{ $quart->name }} <span class="font-normal text-primary/60">· {{ $quart->horaire() }}</span></p>
                @enddroit
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-primary/50">
                    <span>{{ $quart->horaire() }} · {{ rtrim(rtrim(number_format($quart->duree(), 2, ',', ''), '0'), ',') }} h · {{ $quart->assignments_count }} quart(s) planifié(s)</span>
                    @if($quart->assignments_count === 0)
                        @droit('settings.quarts.supprimer')
                            <form method="POST" action="{{ route('settings.quarts.destroy', $quart) }}" onsubmit="return confirm(@js('Supprimer le quart « '.$quart->name.' » ?'))">
                                @csrf @method('DELETE')
                                <button type="submit" class="font-semibold text-red-700 hover:underline">Supprimer</button>
                            </form>
                        @enddroit
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-center text-sm text-primary/50">Aucun quart : ajoutez-en un ci-dessous.</li>
        @endforelse
    </ul>

    @droit('settings.quarts.creer')
        <form method="POST" action="{{ route('settings.quarts.store') }}" class="mt-6 rounded-xl border border-secondary/20 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-primary">Ajouter un quart</h3>
            <div class="mt-3 grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_7rem_7rem_auto]">
                <label class="block">
                    <span class="text-xs text-primary/60">Nom</span>
                    <input type="text" name="name" required maxlength="60" placeholder="Ex. Matin" value="{{ old('name') }}"
                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Début</span>
                    <input type="time" name="starts_at" required value="{{ old('starts_at') }}"
                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Fin</span>
                    <input type="time" name="ends_at" required value="{{ old('ends_at') }}"
                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Ajouter</button>
            </div>
        </form>
    @enddroit
</div>
