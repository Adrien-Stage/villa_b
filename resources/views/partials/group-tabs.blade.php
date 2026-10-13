@php
    $activeGroup = \App\Support\Navigation::getActiveGroup();
    $groupTabs = ($activeGroup && $activeGroup['type'] === \App\Support\Navigation::TYPE_TABS)
        ? \App\Support\Navigation::getGroupTabs($activeGroup['id'])
        : [];
@endphp

@if(!empty($groupTabs))
    <div class="bg-white/95 backdrop-blur-sm border-b border-secondary/20 px-4 lg:px-8 py-2.5 flex items-center justify-between flex-shrink-0 shadow-xs print:hidden">
        <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto no-scrollbar py-0.5">
            <div class="hidden sm:flex items-center gap-2 pr-3 border-r border-secondary/20 mr-1 text-primary/70 font-semibold text-xs uppercase tracking-wider">
                <i data-lucide="{{ $activeGroup['icon'] ?? 'folder' }}" class="w-4 h-4 text-primary"></i>
                <span>{{ $activeGroup['title'] }}</span>
            </div>
            @foreach($groupTabs as $tab)
                <a href="{{ route($tab['route']) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all duration-150 {{ $tab['active'] ? 'bg-primary text-white shadow-xs font-bold' : 'text-primary/75 hover:text-primary hover:bg-accent/40 bg-secondary/10' }}">
                    <i data-lucide="{{ $tab['icon'] ?? 'circle' }}" class="w-3.5 h-3.5 flex-shrink-0 {{ $tab['active'] ? 'text-white' : 'text-primary/60' }}"></i>
                    <span>{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="hidden md:flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="text-[11px] font-semibold text-primary/50 hover:text-primary transition flex items-center gap-1">
                <span>Toutes les applications</span>
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>
    </div>
@endif
