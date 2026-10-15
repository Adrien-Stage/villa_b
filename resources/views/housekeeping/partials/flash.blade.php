@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg" role="status">
    {{ session('success') }}
</div>
@endif

@if(session('warning'))
<div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg" role="status">
    {{ session('warning') }}
</div>
@endif

@if(session('error'))
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg" role="alert">
    {{ session('error') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg" role="alert">
    {{ $errors->first() }}
</div>
@endif
