@props([
    'id',
    'title' => '',
    'titleId' => '',
    'subtitle' => '',
    'maxWidth' => 'max-w-lg',
    'formAction' => null,
    'formMethod' => 'POST',
    'enctype' => null,
    'closeAction' => "document.getElementById('id').classList.add('hidden')"
])

@php
    if ($closeAction === "document.getElementById('id').classList.add('hidden')") {
        $closeAction = "document.getElementById('{$id}').classList.add('hidden')";
    }
@endphp

<div id="{{ $id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="absolute inset-0" onclick="{{ $closeAction }}"></div>
    <div class="bg-white rounded-2xl shadow-2xl w-full {{ $maxWidth }} mx-auto flex flex-col max-h-[90vh] relative z-10">
        
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <div>
                @if($title)
                    <h3 {!! $titleId ? 'id="'.$titleId.'"' : '' !!} class="font-heading font-semibold text-primary">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-primary/50 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            <button type="button" onclick="{{ $closeAction }}"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Body + Footer --}}
        @if($formAction)
            <form method="{{ $formMethod }}" action="{{ $formAction }}" class="flex flex-col flex-1 min-h-0 overflow-hidden" {!! $enctype ? 'enctype="'.$enctype.'"' : '' !!}>
                @csrf
                <div class="p-6 space-y-4 flex-1 overflow-y-auto min-h-0">
                    {{ $slot }}
                </div>
                
                @if(isset($footer))
                    <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                        {{ $footer }}
                    </div>
                @endif
            </form>
        @else
            <div class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="p-6 space-y-4 flex-1 overflow-y-auto min-h-0">
                    {{ $slot }}
                </div>
                
                @if(isset($footer))
                    <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                        {{ $footer }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
