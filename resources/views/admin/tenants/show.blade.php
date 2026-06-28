@php
    $sidebarMenus = [
        'overview' => ['label' => 'Vue d\'ensemble', 'icon' => 'chart'],
        'info' => ['label' => 'Informations', 'icon' => 'building'],
        'users' => ['label' => 'Utilisateurs', 'icon' => 'users'],
        'theme' => ['label' => 'Thème & Couleurs', 'icon' => 'palette'],
        'modules' => ['label' => 'Modules', 'icon' => 'puzzle'],
        'settings' => ['label' => 'Paramètres', 'icon' => 'cog'],
    ];
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Administration</title>
    <meta name="description" content="Espace de gestion de l'établissement {{ $tenant->name }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.dashboard', ['tab' => 'tenants']) }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Établissements
                </a>
                <div class="h-5 w-px bg-slate-700"></div>
                <div class="flex items-center gap-3">
                    @if(!empty($tenant->settings['logo']))
                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="{{ $tenant->name }}" class="h-7 object-contain">
                    @else
                        <div class="h-7 w-7 rounded bg-indigo-600/20 flex items-center justify-center">
                            <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-sm font-bold text-white leading-none">{{ $tenant->name }}</h1>
                        <p class="text-[10px] text-slate-400 font-mono">{{ $tenant->slug }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' }}">
                    {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-3.5rem)]">
        <!-- ======= SIDEBAR ======= -->
        <aside class="w-60 shrink-0 bg-white border-r border-slate-200 shadow-sm">
            <nav class="p-4 space-y-1">
                @foreach($sidebarMenus as $key => $menu)
                    <a 
                        href="{{ route('admin.tenants.show', ['tenant' => $tenant, 'section' => $key]) }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition {{ $section === $key ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border border-transparent' }}"
                    >
                        @if($menu['icon'] === 'chart')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        @elseif($menu['icon'] === 'building')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        @elseif($menu['icon'] === 'users')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        @elseif($menu['icon'] === 'palette')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                            </svg>
                        @elseif($menu['icon'] === 'puzzle')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.421 48.421 0 01-4.163-.3c.186 1.613.66 3.129 1.41 4.483l.278.483a.798.798 0 00.984.332 48.027 48.027 0 005.15-.87.634.634 0 00.387-.88c-.55-1.15-.894-2.418-1.012-3.77a.637.637 0 01.594-.673h.055c.353 0 .673.186.959.401.29.221.634.349 1.003.349 1.035 0 1.875-1.007 1.875-2.25s-.84-2.25-1.875-2.25c-.37 0-.713.128-1.003.349-.286.215-.606.401-.96.401v0a.64.64 0 01-.643-.657 48.421 48.421 0 01.3-4.163c-1.613.186-3.129.66-4.483 1.41l-.483.278a.798.798 0 00-.332.984c.38.807.686 1.658.914 2.545" />
                            </svg>
                        @elseif($menu['icon'] === 'cog')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        @endif
                        {{ $menu['label'] }}
                    </a>
                @endforeach
            </nav>

            <!-- Sidebar Footer -->
            <div class="border-t border-slate-100 p-4 mt-4">
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Statistiques</p>
                    <div class="space-y-1.5 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span>Utilisateurs</span>
                            <span class="font-bold text-slate-800">{{ $tenant->users_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Chambres</span>
                            <span class="font-bold text-slate-800">{{ $tenant->rooms_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Réservations</span>
                            <span class="font-bold text-slate-800">{{ $tenant->bookings_count }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ======= MAIN CONTENT ======= -->
        <main class="flex-1 p-8">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-xs font-bold text-green-800 shadow-sm flex items-center gap-2">
                    <svg class="h-4 w-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 shadow-sm">
                    <ul class="list-disc list-inside text-xs text-red-700 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ==================== VUE D'ENSEMBLE ==================== --}}
            @if($section === 'overview')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Vue d'ensemble</h2>
                    <p class="text-xs text-slate-500 mt-1">Tableau de bord de l'établissement {{ $tenant->name }}</p>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-indigo-100 p-2.5">
                                <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Utilisateurs</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->users_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-emerald-100 p-2.5">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Chambres</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->rooms_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-amber-100 p-2.5">
                                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Réservations</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->bookings_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-violet-100 p-2.5">
                                <svg class="h-5 w-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Devise</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Summary -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">Informations générales</h3>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nom</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Slug</dt>
                                <dd class="mt-1 font-mono text-slate-600">{{ $tenant->slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pays</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->settings['country'] ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Adresse</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->address ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Téléphone</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->phone ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->email ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

            {{-- ==================== INFORMATIONS ==================== --}}
            @elseif($section === 'info')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Informations de l'Établissement</h2>
                    <p class="text-xs text-slate-500 mt-1">Modifier le nom, l'adresse et les coordonnées</p>
                </div>

                <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    @csrf
                    <div class="p-6 space-y-5">
                        <!-- Logo -->
                        <div x-data="{ logoPreview: '{{ !empty($tenant->settings['logo']) ? asset('storage/' . $tenant->settings['logo']) : '' }}' }">
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2">Logo</label>
                            <div class="flex items-center gap-5">
                                <div class="h-16 w-24 bg-slate-950 border border-slate-700 rounded-lg flex items-center justify-center overflow-hidden">
                                    <template x-if="logoPreview"><img :src="logoPreview" class="h-14 object-contain"></template>
                                    <template x-if="!logoPreview">
                                        <svg class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    </template>
                                </div>
                                <input type="file" name="logo" accept="image/*" @change="logoPreview = URL.createObjectURL($event.target.files[0])" class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom <span class="text-red-400">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Slug <span class="text-red-400">*</span></label>
                                <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Pays</label>
                                <input type="text" name="country" value="{{ old('country', $tenant->settings['country'] ?? 'Cameroun') }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Devise <span class="text-red-400">*</span></label>
                                <input type="text" name="currency" value="{{ old('currency', $tenant->currency) }}" required maxlength="3" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono uppercase outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse</label>
                            <input type="text" name="address" value="{{ old('address', $tenant->address) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                                <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Email</label>
                                <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>

            {{-- ==================== UTILISATEURS ==================== --}}
            @elseif($section === 'users')
                <div class="mb-6 flex items-baseline justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Utilisateurs</h2>
                        <p class="text-xs text-slate-500 mt-1">{{ $tenantUsers->count() }} utilisateur(s) rattaché(s) à {{ $tenant->name }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Email</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Rôle</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($tenantUsers as $user)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-5 py-3 font-semibold text-slate-800">{{ $user->name }}</td>
                                    <td class="px-5 py-3 text-slate-600 font-mono">{{ $user->email }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                                            {{ $user->role }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $user->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            {{ $user->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">
                                        Aucun utilisateur rattaché à cet établissement.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            {{-- ==================== THÈME ==================== --}}
            @elseif($section === 'theme')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Thème & Couleurs</h2>
                    <p class="text-xs text-slate-500 mt-1">Personnalisez l'apparence de l'interface de {{ $tenant->name }}</p>
                </div>

                <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" x-data="{
                    p: '{{ $tenant->settings['theme']['primary'] ?? '#391F0E' }}',
                    s: '{{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }}',
                    a: '{{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }}',
                    d: '{{ $tenant->settings['theme']['dark'] ?? '#0F0201' }}',
                    sd: '{{ $tenant->settings['theme']['surface_dark'] ?? '#2C1810' }}',
                    tl: '{{ $tenant->settings['theme']['text_on_light'] ?? '#391F0E' }}',
                    td: '{{ $tenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }}'
                }">
                    @csrf
                    <!-- Hidden fields to keep existing data -->
                    <input type="hidden" name="name" value="{{ $tenant->name }}">
                    <input type="hidden" name="slug" value="{{ $tenant->slug }}">
                    <input type="hidden" name="currency" value="{{ $tenant->currency }}">

                    <div class="p-6 space-y-6">
                        <!-- Live Preview -->
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <div class="px-4 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase bg-slate-50 border-b border-slate-200">Aperçu en temps réel</div>
                            <div class="p-4 flex items-center gap-4">
                                <div class="rounded-lg overflow-hidden shadow-md border border-slate-200 w-52 shrink-0">
                                    <div class="h-12 flex items-center justify-center" :style="'background-color:' + p">
                                        <span class="text-[10px] font-bold tracking-widest uppercase" :style="'color:' + td">{{ $tenant->name }}</span>
                                    </div>
                                    <div class="h-7 flex items-center justify-center" :style="'background-color:' + s">
                                        <span class="text-[9px] font-semibold" :style="'color:' + tl">Navigation</span>
                                    </div>
                                    <div class="h-12 bg-white flex items-center justify-center">
                                        <span class="text-[9px] text-slate-400">Contenu principal</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Color Pickers -->
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Primaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="p" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[primary]" x-model="p" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Secondaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="s" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[secondary]" x-model="s" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 uppercase">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Accent</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="a" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[accent]" x-model="a" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="d" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[dark]" x-model="d" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Surface Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="sd" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[surface_dark]" x-model="sd" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Clair</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="tl" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_light]" x-model="tl" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="td" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_dark]" x-model="td" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                            Enregistrer le thème
                        </button>
                    </div>
                </form>

            {{-- ==================== MODULES / SETTINGS (placeholder) ==================== --}}
            @else
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="rounded-2xl bg-slate-100 p-6 mb-5">
                        <svg class="h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384-3.19A.745.745 0 015.65 11.6V5.98a.75.75 0 01.416-.672l5.384-3.19a.75.75 0 01.768 0l5.384 3.19c.255.151.416.42.416.672v5.62a.745.745 0 01-.378.38l-5.384 3.19a.75.75 0 01-.768 0zM12 9.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-700">Section en cours de développement</h3>
                    <p class="text-xs text-slate-400 mt-2 max-w-sm">Cette fonctionnalité sera disponible dans une prochaine mise à jour de la plateforme.</p>
                </div>
            @endif
        </main>
    </div>
</body>
</html>
