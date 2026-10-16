@foreach(['success' => 'bg-green-50 border-green-200 text-green-800', 'info' => 'bg-sky-50 border-sky-200 text-sky-900', 'error' => 'bg-red-50 border-red-200 text-red-700'] as $cle => $style)
    @if(session($cle))
        <div class="px-4 py-3 border text-sm rounded-lg {{ $style }}" role="{{ $cle === 'error' ? 'alert' : 'status' }}">{{ session($cle) }}</div>
    @endif
@endforeach
@if($errors->any())
    <div class="px-4 py-3 border border-red-200 bg-red-50 text-sm text-red-700 rounded-lg" role="alert">{{ $errors->first() }}</div>
@endif
