{{-- Lignes rejetées lors du dernier import CSV (clé de session import_errors). --}}
@if(session('import_errors') && count(session('import_errors')))
    <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg">
        <p class="font-semibold mb-1.5">Lignes non importées :</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach(session('import_errors') as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
