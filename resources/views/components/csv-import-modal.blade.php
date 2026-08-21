@props([
    'id',
    'title',
    'action',
    'template',
    'structure',
    'submitLabel' => 'Importer',
])

{{--
    Modale d'import CSV réutilisable, alignée sur celle des chambres. Ouverte
    en JS simple (bascule de la classe « hidden »). Le slot reçoit les <li>
    d'explication de la structure attendue.
--}}
<div id="{{ $id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">{{ $title }}</h3>
            <button type="button" onclick="document.getElementById('{{ $id }}').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden"
              onsubmit="const b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Import en cours…';">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
                <div class="px-4 py-3 bg-accent/20 border border-secondary/20 rounded-lg text-xs text-primary/70 leading-relaxed">
                    <p class="font-semibold text-primary mb-1">Structure attendue (délimiteur « ; ») :</p>
                    <p class="font-mono text-[11px] break-all">{{ $structure }}</p>
                    <ul class="mt-2 space-y-1 list-disc list-inside">
                        {{ $slot }}
                        <li>Le fichier peut être au format Excel (.xlsx, .xls) ou CSV UTF-8.</li>
                    </ul>
                    <a href="{{ $template }}" class="inline-flex items-center gap-1.5 mt-3 text-primary font-semibold hover:underline">
                        <i data-lucide="file-down" class="w-3.5 h-3.5"></i>
                        Télécharger le modèle (Excel / CSV)
                    </a>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Fichier Excel ou CSV *</label>
                    <input type="file" name="csv_file" required accept=".xlsx,.xls,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-secondary">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-secondary/20 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('{{ $id }}').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors disabled:opacity-60">
                    {{ $submitLabel }}
                </button>
            </div>
        </form>
    </div>
</div>
