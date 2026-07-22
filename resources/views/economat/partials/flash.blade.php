@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
        {{ session('error') }}
    </div>
@endif
@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
