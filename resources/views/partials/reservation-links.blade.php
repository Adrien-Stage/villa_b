@php
    $sousRubriques = [
        ['bookings.index', 'Individuelles', 'user', request()->routeIs('bookings.*')
            && !request()->routeIs('bookings.cash_register.*', 'bookings.handovers.*')],
        ['groups.index', 'Groupes', 'users', request()->routeIs('groups.*')],
        ['bookings.cash_register.index', 'Compta Réception', 'calculator', request()->routeIs('bookings.cash_register.*')],
        ['bookings.handovers.index', 'Passations', 'hand-coins', request()->routeIs('bookings.handovers.*')],
    ];
    if (\App\Support\TenantModules::has('restaurant') && auth()->user()->hasModuleAccess('restaurant')) {
        $sousRubriques[] = ['restaurant.banquets.index', 'Banquets', 'party-popper', request()->routeIs('restaurant.banquets.*')];
    }
@endphp

<ul class="{{ $modeSidebar ? 'sidebar-sous-menu mt-0.5 ml-4 space-y-0.5 border-l border-text-on-dark/20 pl-3' : 'flex flex-wrap gap-x-4 gap-y-0.5 py-1' }}">
    @foreach($sousRubriques as [$route, $libelle, $icone, $actif])
        @droit(\App\Support\PermissionCatalog::permissionForRoute($route))
            <li>
                <a href="{{ route($route) }}" @if($actif) aria-current="page" @endif
                   class="flex items-center gap-2 py-1.5 text-xs font-medium
                       {{ $modeSidebar
                           ? ($actif ? 'text-white' : 'text-text-on-dark hover:text-white')
                           : ($actif ? 'text-primary underline underline-offset-4' : 'text-primary/70 hover:text-primary') }}">
                    <i data-lucide="{{ $icone }}" class="w-3 h-3 shrink-0" aria-hidden="true"></i>
                    {{ $libelle }}
                </a>
            </li>
        @enddroit
    @endforeach
</ul>
