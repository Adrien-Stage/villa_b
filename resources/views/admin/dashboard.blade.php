@php
    $tabs = [
        'dashboard' => [
            'label' => 'Supervision',
            'title' => 'Supervision multi-etablissements',
            'description' => 'Vue globale des etablissements, utilisateurs, activite, alertes et indicateurs consolides.',
            'items' => ['Etablissements actifs', 'Utilisateurs actifs', 'Reservations du jour', 'Alertes globales'],
        ],
        'tenants' => [
            'label' => 'Etablissements',
            'title' => 'Gestion des etablissements',
            'description' => 'Creation, configuration, activation, suspension et diagnostic des tenants.',
            'items' => ['Creation tenant', 'Configuration generale', 'Modules actifs', 'Etat onboarding'],
        ],
        'managers' => [
            'label' => 'Managers',
            'title' => 'Gestion des managers',
            'description' => 'Creation, activation, reinitialisation et rattachement des managers aux etablissements.',
            'items' => ['Compte manager', 'Reinitialisation mot de passe', 'Activation compte', 'Rattachement tenant'],
        ],
        'roles' => [
            'label' => 'Roles',
            'title' => 'Roles et permissions',
            'description' => 'Consultation des roles operationnels et configuration future des permissions par module.',
            'items' => ['Roles disponibles', 'Permissions par module', 'Roles par tenant', 'Roles personnalises'],
        ],
        'modules' => [
            'label' => 'Modules',
            'title' => 'Modules plateforme',
            'description' => 'Activation des modules disponibles selon les besoins de chaque etablissement.',
            'items' => ['Hotel', 'Restaurant', 'Boutique', 'Housekeeping', 'Comptabilite', 'IA'],
        ],
        'audit' => [
            'label' => 'Audit',
            'title' => 'Audit et securite',
            'description' => 'Suivi des connexions, acces refuses, actions sensibles et interventions admin.',
            'items' => ['Logs acces', 'Acces refuses', 'Actions sensibles', 'Comptes compromis'],
        ],
        'support' => [
            'label' => 'Support',
            'title' => 'Support operationnel',
            'description' => 'Lecture globale et futur mode assistance audite pour diagnostiquer un etablissement.',
            'items' => ['Mode lecture', 'Mode assistance', 'Justification', 'Historique interventions'],
        ],
        'settings' => [
            'label' => 'Configuration',
            'title' => 'Configuration globale',
            'description' => 'Parametres applicatifs, limites, integrations et statuts techniques de la plateforme.',
            'items' => ['Parametres globaux', 'Limites tenant', 'Integrations', 'Etat technique'],
        ],
        'billing' => [
            'label' => 'Licences',
            'title' => 'Abonnements et licences',
            'description' => 'Gestion future des plans, modules inclus, echeances et suspensions.',
            'items' => ['Plan actif', 'Modules inclus', 'Expiration', 'Historique abonnement'],
        ],
        'imports' => [
            'label' => 'Import/Export Excel',
            'title' => 'Import et export Excel',
            'description' => 'Zone dediee aux imports et exports de donnees sous forme de fichiers Excel.',
            'items' => ['Import etablissements', 'Import utilisateurs', 'Export audit', 'Export supervision'],
        ],
        'system' => [
            'label' => 'Systeme',
            'title' => 'Sante systeme',
            'description' => 'Surveillance technique des services, files, integrations et erreurs applicatives.',
            'items' => ['Files attente', 'Erreurs API', 'Emails', 'Stockage'],
        ],
    ];

    $activeTab = request('tab', 'dashboard');
    if (!array_key_exists($activeTab, $tabs)) {
        $activeTab = 'dashboard';
    }
    $active = $tabs[$activeTab];
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration - {{ \App\Models\Tenant::first()?->name ?? 'Villa Boutanga' }}</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased font-body">

    <!-- Top Full-Width Navigation Bar (Fixed to top) -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto max-w-7xl px-5 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center gap-8">
                <div class="text-sm font-extrabold uppercase tracking-wider text-white">
                    ADMIN GLOBAL
                </div>
                <nav class="hidden md:flex items-center gap-1.5" aria-label="Navigation administration">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold tracking-wide transition {{ $activeTab === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3.5 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-slate-700 hover:text-white">
                    Déconnexion
                </button>
            </form>
        </div>
        <!-- Mobile Navigation -->
        <div class="md:hidden border-t border-slate-800 px-5 py-2 overflow-x-auto">
            <div class="flex gap-1.5 min-w-max">
                @foreach($tabs as $key => $tab)
                    <a
                        href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                        class="rounded-md px-2.5 py-1 text-xs font-semibold transition {{ $activeTab === $key ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <main class="mx-auto min-h-screen w-full max-w-7xl px-5 py-8 lg:px-8">
        
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4 text-xs font-bold text-green-800 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4 text-xs font-bold text-red-800 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('temp_password_info'))
            <div class="mb-6 rounded-lg bg-emerald-50 border-2 border-emerald-500 p-5 shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="rounded-full bg-emerald-500 p-1 text-white shadow-sm flex-shrink-0">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="w-full">
                        <h3 class="text-xs font-bold text-emerald-900 uppercase tracking-wide">Mot de passe temporaire généré !</h3>
                        <p class="mt-1 text-xs text-emerald-700 leading-relaxed">
                            Un nouveau mot de passe a été généré pour <strong>{{ session('temp_password_info')['name'] }}</strong> ({{ session('temp_password_info')['email'] }}).
                            <br>Veuillez copier ce mot de passe et le lui communiquer de manière sécurisée. L'utilisateur devra le modifier lors de sa prochaine connexion.
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <span class="text-xs font-bold text-emerald-800">Mot de passe :</span>
                            <input type="text" readonly value="{{ session('temp_password_info')['password'] }}" class="font-mono text-xs border border-emerald-300 bg-white px-3 py-1 rounded text-emerald-950 select-all font-semibold outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'tenants')
            <!-- ================= GESTION DES ETABLISSEMENTS LAYOUT ================= -->
            
            <!-- Breadcrumb Path -->
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">ÉTABLISSEMENTS</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Gestion des Établissements</h1>
                    <p class="text-xs text-slate-500 mt-1">Gérer les informations générales, le statut et les paramètres des filiales de l'ONG.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6" x-data="{ 
                openEditModal: false, 
                tenantId: null, 
                tenantName: '', 
                tenantSlug: '', 
                tenantCountry: '', 
                tenantAddress: '', 
                tenantPhone: '', 
                tenantEmail: '', 
                tenantCurrency: '',
                tenantLogoUrl: '',
                tenantThemePrimary: '#391F0E',
                tenantThemeSecondary: '#CCAB87',
                tenantThemeAccent: '#EED4A3',
                tenantThemeDark: '#0F0201',
                tenantThemeSurfaceDark: '#2C1810',
                tenantThemeTextOnLight: '#391F0E',
                tenantThemeTextOnDark: '#CCAB87'
            }">
                @foreach($tenants as $tenant)
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                        <div>
                            <!-- Header with logo / image placeholder -->
                            <div class="h-32 bg-slate-900 flex items-center justify-center relative">
                                @if(!empty($tenant->settings['logo']))
                                    <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="Logo {{ $tenant->name }}" class="h-20 object-contain">
                                @else
                                    <!-- Elegant default SVG icon for hotel/establishment -->
                                    <div class="text-indigo-400 flex flex-col items-center">
                                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                        </svg>
                                        <span class="text-[9px] font-bold tracking-widest uppercase text-slate-500 mt-2">Aucun logo</span>
                                    </div>
                                @endif
                                
                                <!-- Active Status Badge -->
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Details -->
                            <div class="p-5">
                                <h3 class="text-base font-bold text-slate-800 tracking-tight">{{ $tenant->name }}</h3>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">Slug: {{ $tenant->slug }}</p>
                                
                                <div class="mt-4 space-y-2.5 text-xs text-slate-600 border-t border-slate-100 pt-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Pays :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->settings['country'] ?? 'Cameroun' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Adresse :</span>
                                        <span class="font-semibold text-slate-700 truncate max-w-[180px]" title="{{ $tenant->address }}">{{ $tenant->address ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Téléphone :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->phone ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Email :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->email ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Devise :</span>
                                        <span class="font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5 text-[9px]">{{ $tenant->currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex justify-end">
                            <button 
                                @click="
                                    openEditModal = true;
                                    tenantId = {{ $tenant->id }};
                                    tenantName = '{{ addslashes($tenant->name) }}';
                                    tenantSlug = '{{ addslashes($tenant->slug) }}';
                                    tenantCountry = '{{ addslashes($tenant->settings['country'] ?? 'Cameroun') }}';
                                    tenantAddress = '{{ addslashes($tenant->address ?? '') }}';
                                    tenantPhone = '{{ addslashes($tenant->phone ?? '') }}';
                                    tenantEmail = '{{ addslashes($tenant->email ?? '') }}';
                                    tenantCurrency = '{{ addslashes($tenant->currency ?? 'XAF') }}';
                                    tenantLogoUrl = '{{ !empty($tenant->settings['logo']) ? asset('storage/' . $tenant->settings['logo']) : '' }}';
                                    tenantThemePrimary = '{{ $tenant->settings['theme']['primary'] ?? '#391F0E' }}';
                                    tenantThemeSecondary = '{{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }}';
                                    tenantThemeAccent = '{{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }}';
                                    tenantThemeDark = '{{ $tenant->settings['theme']['dark'] ?? '#0F0201' }}';
                                    tenantThemeSurfaceDark = '{{ $tenant->settings['theme']['surface_dark'] ?? '#2C1810' }}';
                                    tenantThemeTextOnLight = '{{ $tenant->settings['theme']['text_on_light'] ?? '#391F0E' }}';
                                    tenantThemeTextOnDark = '{{ $tenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }}';
                                "
                                type="button" 
                                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm"
                            >
                                Modifier les informations
                            </button>
                        </div>
                    </div>
                @endforeach

                <!-- Edit Modal (AlpineJS Overlay) -->
                <div 
                    x-show="openEditModal" 
                    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 bg-slate-900/60 backdrop-blur-xs"
                    x-transition
                    style="display: none;"
                >
                    <div 
                        @click.away="openEditModal = false" 
                        class="bg-white rounded-lg border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden"
                    >
                        <!-- Modal Header -->
                        <div class="bg-slate-900 px-5 py-4 flex items-center justify-between text-white border-b border-slate-800">
                            <h3 class="text-xs font-bold uppercase tracking-wider">Modifier l'établissement</h3>
                            <button @click="openEditModal = false" class="text-slate-400 hover:text-white">&times;</button>
                        </div>
                        
                        <!-- Modal Body Form -->
                        <form :action="'/admin/tenants/' + tenantId" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-left">
                            @csrf
                            
                            <!-- Logo Upload -->
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Logo de l'établissement</label>
                                <div class="mt-2 flex items-center gap-4">
                                    <div class="h-14 w-20 bg-slate-950 border border-slate-800 rounded flex items-center justify-center overflow-hidden">
                                        <template x-if="tenantLogoUrl">
                                            <img :src="tenantLogoUrl" alt="Logo preview" class="h-12 object-contain">
                                        </template>
                                        <template x-if="!tenantLogoUrl">
                                            <svg class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                        </template>
                                    </div>
                                    <input type="file" name="logo" class="text-xs text-slate-600 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Name -->
                                <div>
                                    <label for="name" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom de l'établissement</label>
                                    <input type="text" id="name" name="name" x-model="tenantName" required class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                                
                                <!-- Slug -->
                                <div>
                                    <label for="slug" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Code unique / Slug</label>
                                    <input type="text" id="slug" name="slug" x-model="tenantSlug" required class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Country -->
                                <div>
                                    <label for="country" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Pays</label>
                                    <input type="text" id="country" name="country" x-model="tenantCountry" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                                
                                <!-- Currency -->
                                <div>
                                    <label for="currency" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Devise par défaut</label>
                                    <input type="text" id="currency" name="currency" x-model="tenantCurrency" required maxlength="3" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>

                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse</label>
                                <input type="text" id="address" name="address" x-model="tenantAddress" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                                    <input type="text" id="phone" name="phone" x-model="tenantPhone" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse e-mail</label>
                                    <input type="email" id="email" name="email" x-model="tenantEmail" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-4 mt-4">
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Thème & Couleurs</label>
                                
                                <!-- Pré-selections (Palettes de couleurs de la capture) -->
                                <div class="mt-2.5">
                                    <p class="text-[10px] text-slate-400 mb-1.5">Palettes prédéfinies :</p>
                                    <div class="flex items-center gap-3">
                                        <!-- Palette Terracotta (Original) -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#391F0E';
                                                tenantThemeSecondary = '#CCAB87';
                                                tenantThemeAccent = '#EED4A3';
                                                tenantThemeDark = '#0F0201';
                                                tenantThemeSurfaceDark = '#2C1810';
                                                tenantThemeTextOnLight = '#391F0E';
                                                tenantThemeTextOnDark = '#CCAB87';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #391F0E 50%, #CCAB87 50%);"
                                            title="Terracotta (Original)">
                                            <span x-show="tenantThemePrimary === '#391F0E'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                        <!-- Palette Royal Blue -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#1E3A8A';
                                                tenantThemeSecondary = '#3B82F6';
                                                tenantThemeAccent = '#93C5FD';
                                                tenantThemeDark = '#0F172A';
                                                tenantThemeSurfaceDark = '#1E293B';
                                                tenantThemeTextOnLight = '#FFFFFF';
                                                tenantThemeTextOnDark = '#93C5FD';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #1E3A8A 50%, #3B82F6 50%);"
                                            title="Bleu Royal">
                                            <span x-show="tenantThemePrimary === '#1E3A8A'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                        <!-- Palette Forest Green -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#064E3B';
                                                tenantThemeSecondary = '#10B981';
                                                tenantThemeAccent = '#A7F3D0';
                                                tenantThemeDark = '#022C22';
                                                tenantThemeSurfaceDark = '#064E3B';
                                                tenantThemeTextOnLight = '#FFFFFF';
                                                tenantThemeTextOnDark = '#A7F3D0';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #064E3B 50%, #10B981 50%);"
                                            title="Vert Forêt">
                                            <span x-show="tenantThemePrimary === '#064E3B'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                        <!-- Palette Imperial Purple -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#4C1D95';
                                                tenantThemeSecondary = '#8B5CF6';
                                                tenantThemeAccent = '#DDD6FE';
                                                tenantThemeDark = '#1E1B4B';
                                                tenantThemeSurfaceDark = '#312E81';
                                                tenantThemeTextOnLight = '#FFFFFF';
                                                tenantThemeTextOnDark = '#DDD6FE';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #4C1D95 50%, #8B5CF6 50%);"
                                            title="Violet Impérial">
                                            <span x-show="tenantThemePrimary === '#4C1D95'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                        <!-- Palette Rose / Pink -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#831843';
                                                tenantThemeSecondary = '#EC4899';
                                                tenantThemeAccent = '#FCE7F3';
                                                tenantThemeDark = '#500724';
                                                tenantThemeSurfaceDark = '#831843';
                                                tenantThemeTextOnLight = '#FFFFFF';
                                                tenantThemeTextOnDark = '#FCE7F3';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #831843 50%, #EC4899 50%);"
                                            title="Rose Vibrant">
                                            <span x-show="tenantThemePrimary === '#831843'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                        <!-- Palette Sunset Orange -->
                                        <button type="button" 
                                            @click="
                                                tenantThemePrimary = '#7C2D12';
                                                tenantThemeSecondary = '#F97316';
                                                tenantThemeAccent = '#FFEDD5';
                                                tenantThemeDark = '#431407';
                                                tenantThemeSurfaceDark = '#7C2D12';
                                                tenantThemeTextOnLight = '#FFFFFF';
                                                tenantThemeTextOnDark = '#FFEDD5';
                                            "
                                            class="w-6 h-6 rounded-full border border-slate-300 relative focus:outline-none cursor-pointer"
                                            style="background: linear-gradient(135deg, #7C2D12 50%, #F97316 50%);"
                                            title="Orange Couchant">
                                            <span x-show="tenantThemePrimary === '#7C2D12'" class="absolute inset-0 flex items-center justify-center text-white text-[10px]">✓</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Sélecteurs manuels et codes HEX -->
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Couleur Primaire (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemePrimary" class="h-7 w-7 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[primary]" x-model="tenantThemePrimary" required class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Couleur Secondaire (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeSecondary" class="h-7 w-7 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[secondary]" x-model="tenantThemeSecondary" required class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4 mt-3">
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Couleur Accent (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeAccent" class="h-6 w-6 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[accent]" x-model="tenantThemeAccent" required class="block w-full rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Fond Sombre (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeDark" class="h-6 w-6 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[dark]" x-model="tenantThemeDark" required class="block w-full rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Surface Sombre (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeSurfaceDark" class="h-6 w-6 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[surface_dark]" x-model="tenantThemeSurfaceDark" required class="block w-full rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mt-3">
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Texte sur Fond Clair (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeTextOnLight" class="h-7 w-7 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[text_on_light]" x-model="tenantThemeTextOnLight" required class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Texte sur Fond Sombre (HEX)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="tenantThemeTextOnDark" class="h-7 w-7 rounded cursor-pointer border border-slate-200 flex-shrink-0">
                                            <input type="text" name="theme[text_on_dark]" x-model="tenantThemeTextOnDark" required class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                                <button @click="openEditModal = false" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    Annuler
                                </button>
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @elseif($activeTab !== 'audit')
            <!-- Placeholder Layout for other tabs -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] mt-6">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold tracking-wider text-slate-400 uppercase">{{ $active['label'] }}</p>
                    <h2 class="mt-2 text-xl font-bold text-slate-800 tracking-tight">{{ $active['title'] }}</h2>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $active['description'] }}</p>
                    
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach($active['items'] as $item)
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-800">{{ $item }}</p>
                                <p class="mt-1 text-xs text-slate-500">Service backend à connecter prochainement.</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm self-start">
                    <p class="text-sm font-bold text-slate-800 font-heading">État du module</p>
                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-500">Interface</span>
                            <span class="font-bold text-slate-800 bg-green-50 text-green-700 px-2 py-0.5 rounded border border-green-200">Prête</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-500">Backend</span>
                            <span class="font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">À développer</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Accès</span>
                            <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">Administrateur</span>
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <!-- ================= AUDIT & SECURITE LAYOUT ================= -->
            
            <!-- Breadcrumb Path -->
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">AUDIT</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Audit & Sécurité</h1>
                    <p class="text-xs text-slate-500 mt-1">Suivi des connexions, accès refusés, actions sensibles et interventions admin.</p>
                </div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block sm:text-right">
                    Dernière mise à jour : {{ now()->translatedFormat('d F Y – H:i') }}
                </span>
            </div>

            <!-- 5 Stats Cards Grid -->
            @if(isset($auditStats))
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mt-6">
                    <!-- Total logs -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-slate-300">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">TOTAL DES LOGS</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-800">{{ $auditStats['total_logs'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                    </div>

                    <!-- Accès refusés -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-red-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">ACCÈS REFUSÉS</p>
                            <p class="mt-2 text-3xl font-extrabold text-red-600">{{ $auditStats['access_denied'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>

                    <!-- Échecs connexion -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-emerald-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">ÉCHECS CONNEXION</p>
                            <p class="mt-2 text-3xl font-extrabold text-emerald-600">{{ $auditStats['failed_logins'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Total comptes -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-indigo-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">TOTAL COMPTES</p>
                            <p class="mt-2 text-3xl font-extrabold text-indigo-600">{{ $auditStats['total_users'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Comptes désactivés -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-amber-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">COMPTES DÉSACTIVÉS</p>
                            <p class="mt-2 text-3xl font-extrabold text-amber-600">{{ $auditStats['inactive_users'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Legend Banner -->
            <div class="mt-6 bg-white rounded-lg border border-slate-200 px-4 py-3 text-xs text-slate-600 flex flex-wrap items-center gap-x-6 gap-y-2.5 shadow-sm">
                <span class="font-extrabold tracking-wider uppercase text-slate-400 text-[10px]">LÉGENDE :</span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-500 shadow-sm"></span>
                    <span>Succès / Connexion réussie</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500 shadow-sm"></span>
                    <span>Danger / Accès refusé</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-orange-500 shadow-sm"></span>
                    <span>Avertissement / Action sensible</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shadow-sm"></span>
                    <span>Info / Déconnexion</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-500 shadow-sm"></span>
                    <span>Neutre / Système</span>
                </span>
            </div>

            <!-- Sub-Tabs Navigation -->
            <div class="mt-8 border-b border-slate-200">
                <div class="flex gap-6">
                    <a 
                        href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" 
                        class="border-b-2 pb-3 text-sm font-semibold transition {{ $subTab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                    >
                        Journal d'Audit
                    </a>
                    <a 
                        href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" 
                        class="border-b-2 pb-3 text-sm font-semibold transition {{ $subTab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                    >
                        Sécurité des Comptes
                    </a>
                </div>
            </div>

            @if($subTab === 'logs')
                <!-- ================= JOURNAL D'AUDIT TAB ================= -->
                
                <!-- Filters Grid Card -->
                <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-6 bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
                    <input type="hidden" name="tab" value="audit">
                    <input type="hidden" name="sub" value="logs">
                    
                    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <label for="tenant_id" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Établissement</label>
                            <select id="tenant_id" name="tenant_id" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les établissements</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                        {{ $tenant->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label for="user_id" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Utilisateur</label>
                            <select id="user_id" name="user_id" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les utilisateurs</option>
                                @foreach($allUsers as $u)
                                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="event_type" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Type d'événement</label>
                            <select id="event_type" name="event_type" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les types</option>
                                <option value="login" {{ request('event_type') === 'login' ? 'selected' : '' }}>Connexion réussie</option>
                                <option value="logout" {{ request('event_type') === 'logout' ? 'selected' : '' }}>Déconnexion</option>
                                <option value="failed_login" {{ request('event_type') === 'failed_login' ? 'selected' : '' }}>Échec de connexion</option>
                                <option value="access_denied" {{ request('event_type') === 'access_denied' ? 'selected' : '' }}>Accès refusé</option>
                                <option value="sensitive_action" {{ request('event_type') === 'sensitive_action' ? 'selected' : '' }}>Action sensible</option>
                                <option value="user_management" {{ request('event_type') === 'user_management' ? 'selected' : '' }}>Gestion des comptes</option>
                            </select>
                        </div>

                        <div>
                            <label for="module" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Module</label>
                            <select id="module" name="module" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les modules</option>
                                <option value="auth" {{ request('module') === 'auth' ? 'selected' : '' }}>Authentification</option>
                                <option value="security" {{ request('module') === 'security' ? 'selected' : '' }}>Sécurité</option>
                                <option value="bookings" {{ request('module') === 'bookings' ? 'selected' : '' }}>Réservations</option>
                                <option value="restaurant" {{ request('module') === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                                <option value="shop" {{ request('module') === 'shop' ? 'selected' : '' }}>Boutique</option>
                                <option value="rooms" {{ request('module') === 'rooms' ? 'selected' : '' }}>Chambres</option>
                                <option value="users" {{ request('module') === 'users' ? 'selected' : '' }}>Membres / Staff</option>
                            </select>
                        </div>

                        <div>
                            <label for="date_from" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Du</label>
                            <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="date_to" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Au</label>
                            <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Réinitialiser
                        </a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">
                            Filtrer
                        </button>
                    </div>
                </form>

                <!-- Responsive Grid Logs List (Table styled) -->
                <div class="mt-6 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                    <!-- Column Headers (Hidden on Mobile) -->
                    <div class="hidden md:grid grid-cols-[140px_220px_190px_1fr_120px] bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-extrabold uppercase tracking-wider py-3.5 px-5">
                        <div>Date & heure</div>
                        <div>Utilisateur</div>
                        <div>Statut / Module</div>
                        <div>Description</div>
                        <div class="text-right">Détails</div>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                            @php
                                $borderClass = 'border-l-4 border-slate-400';
                                $statusBadgeClass = 'text-slate-700 bg-slate-50 border-slate-200';
                                $statusText = 'Système';
                                $dotClass = 'bg-slate-500';
                                
                                if ($log->event_type === 'login') {
                                    $borderClass = 'border-l-4 border-green-500';
                                    $statusBadgeClass = 'text-green-700 bg-green-50 border-green-200';
                                    $statusText = 'Connexion réussie';
                                    $dotClass = 'bg-green-500';
                                } elseif ($log->event_type === 'access_denied') {
                                    $borderClass = 'border-l-4 border-red-500';
                                    $statusBadgeClass = 'text-red-700 bg-red-50 border-red-200';
                                    $statusText = 'Accès refusé';
                                    $dotClass = 'bg-red-500';
                                } elseif ($log->event_type === 'sensitive_action') {
                                    $borderClass = 'border-l-4 border-orange-500';
                                    $statusBadgeClass = 'text-orange-700 bg-orange-50 border-orange-200';
                                    $statusText = 'Action sensible';
                                    $dotClass = 'bg-orange-500';
                                } elseif ($log->event_type === 'logout') {
                                    $borderClass = 'border-l-4 border-blue-500';
                                    $statusBadgeClass = 'text-blue-700 bg-blue-50 border-blue-200';
                                    $statusText = 'Déconnexion';
                                    $dotClass = 'bg-blue-500';
                                } elseif ($log->event_type === 'failed_login') {
                                    $borderClass = 'border-l-4 border-slate-500';
                                    $statusBadgeClass = 'text-slate-700 bg-slate-50 border-slate-200';
                                    $statusText = 'Échec connexion';
                                    $dotClass = 'bg-slate-500';
                                }
                                
                                $moduleBadgeColors = [
                                    'auth' => 'bg-slate-100 text-slate-600 border-slate-200',
                                    'security' => 'bg-red-50 text-red-600 border-red-100',
                                    'bookings' => 'bg-amber-50 text-amber-600 border-amber-100',
                                    'restaurant' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                    'shop' => 'bg-purple-50 text-purple-600 border-purple-100',
                                    'rooms' => 'bg-indigo-50 text-indigo-600 border-indigo-100',
                                    'users' => 'bg-sky-50 text-sky-600 border-sky-100',
                                ];
                            @endphp
                            <div class="grid grid-cols-1 md:grid-cols-[140px_220px_190px_1fr_120px] items-start py-4 px-5 hover:bg-slate-50/70 transition duration-150 gap-2 md:gap-0 {{ $borderClass }}">
                                <!-- Date & Heure -->
                                <div class="text-xs text-slate-800">
                                    <div class="font-bold">{{ $log->created_at->translatedFormat('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ $log->created_at->translatedFormat('H:i') }}</div>
                                </div>
                                
                                <!-- Utilisateur -->
                                <div class="text-xs pr-4">
                                    @if($log->user)
                                        <div class="font-bold text-slate-800 truncate">{{ $log->user->name }}</div>
                                        <div class="text-[10px] text-slate-400 truncate mt-0.5">{{ $log->user->email }}</div>
                                        @if($log->tenant)
                                            <div class="inline-block mt-1 bg-blue-50 text-blue-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-100 uppercase tracking-wide">
                                                {{ $log->tenant->name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">Visiteur Anonyme</span>
                                    @endif
                                </div>
                                
                                <!-- Statut / Module Badges -->
                                <div class="flex flex-row md:flex-col gap-1.5 items-center md:items-start text-[10px]">
                                    <span class="inline-flex rounded border px-1.5 py-0.5 font-bold uppercase tracking-wider {{ $moduleBadgeColors[$log->module] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                        {{ $log->module ?? 'global' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-bold {{ $statusBadgeClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
                                        {{ $statusText }}
                                    </span>
                                </div>
                                
                                <!-- Description -->
                                <div class="text-xs text-slate-800 pr-4 leading-relaxed">
                                    <div class="font-semibold text-slate-800">{{ $log->action }}</div>
                                    <!-- IP & User-Agent Box -->
                                    <div class="mt-1.5 inline-flex items-center gap-2 bg-slate-50 border border-slate-100 rounded px-2.5 py-1 text-[10px] text-slate-400 font-mono w-full max-w-lg shadow-2xs">
                                        <span class="font-bold text-slate-500 bg-slate-200 px-1 rounded text-[8px] tracking-wide">IP</span>
                                        <span class="text-slate-700 font-semibold">{{ $log->ip_address ?? '127.0.0.1' }}</span>
                                        @if($log->user_agent)
                                            <span class="text-slate-300">•</span>
                                            <span class="truncate max-w-[260px] text-slate-600 hover:text-slate-800 cursor-help" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Details Trigger (AlpineJS Popover) -->
                                <div class="text-right text-xs whitespace-nowrap relative self-center md:self-start mt-2 md:mt-0" x-data="{ open: false }">
                                    @if($log->payload)
                                        <button @click="open = !open" type="button" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-bold transition">
                                            <span>Voir variables</span>
                                            <span class="text-[9px]">↗</span>
                                        </button>
                                        
                                        <!-- Code Popover box -->
                                        <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-2 right-0 w-80 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 text-left p-4 shadow-xl font-mono text-[10px] max-h-64 overflow-auto">
                                            <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-2">
                                                <span class="font-bold text-slate-400 uppercase tracking-wider text-[9px]">Variables d'événement</span>
                                                <button @click="open = false" class="text-slate-400 hover:text-white">&times;</button>
                                            </div>
                                            <pre class="whitespace-pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[10px]">Aucune variable</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400 italic text-xs">
                                Aucun log d'audit correspondant aux critères de filtrage.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-5">
                    {{ $logs->links() }}
                </div>
            @endif

            @if($subTab === 'users')
                <!-- ================= SECURITE DES COMPTES TAB ================= -->
                
                <!-- Search user bar -->
                <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-6 flex gap-2">
                    <input type="hidden" name="tab" value="audit">
                    <input type="hidden" name="sub" value="users">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            name="user_search" 
                            value="{{ request('user_search') }}" 
                            placeholder="Rechercher par nom, email ou rôle..." 
                            class="block w-full rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
                        >
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-sm">
                        Rechercher
                    </button>
                    @if(request()->filled('user_search'))
                        <a href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition flex items-center">
                            Effacer
                        </a>
                    @endif
                </form>

                <!-- Accounts Grid Table -->
                <div class="mt-6 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                    <!-- Column Headers (Hidden on Mobile) -->
                    <div class="hidden md:grid grid-cols-[260px_160px_190px_190px_1fr] bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-extrabold uppercase tracking-wider py-3.5 px-5">
                        <div>Utilisateur</div>
                        <div>Rôle</div>
                        <div>Dernière Connexion</div>
                        <div>Activité / En ligne</div>
                        <div class="text-right">Actions de Sécurité</div>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        @forelse($users as $u)
                            <div class="grid grid-cols-1 md:grid-cols-[260px_160px_190px_190px_1fr] items-center py-4 px-5 hover:bg-slate-50/70 transition duration-150 gap-2.5 md:gap-0">
                                <!-- User Identity -->
                                <div class="text-xs">
                                    <div class="font-bold text-slate-800">{{ $u->name }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $u->email }}</div>
                                    <div class="mt-1">
                                        @if($u->tenant)
                                            <span class="bg-blue-50 text-blue-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-100 uppercase tracking-wide">
                                                {{ $u->tenant->name }}
                                            </span>
                                        @else
                                            <span class="bg-slate-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded border border-slate-800 uppercase tracking-wide">
                                                Admin Global
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Role -->
                                <div class="text-xs">
                                    <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $u->role) }}</span>
                                </div>
                                
                                <!-- Last Login Timestamp -->
                                <div class="text-xs text-slate-600">
                                    @if($u->last_login_at)
                                        <span class="font-semibold text-slate-700">{{ $u->last_login_at->translatedFormat('d M Y') }}</span>
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $u->last_login_at->translatedFormat('H:i') }}</div>
                                    @else
                                        <span class="text-slate-400 italic">Aucune connexion</span>
                                    @endif
                                </div>
                                
                                <!-- Online / Active Status badges -->
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $u->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $u->is_active ? 'Actif' : 'Désactivé' }}
                                    </span>
                                    
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $u->isOnline() ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $u->isOnline() ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                        {{ $u->isOnline() ? 'En ligne' : 'Hors ligne' }}
                                    </span>
                                </div>
                                
                                <!-- Security Actions buttons -->
                                <div class="text-right whitespace-nowrap">
                                    @if($u->id !== Auth::id())
                                        <div class="flex md:justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.users.toggle-active', $u) }}" class="inline">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="rounded-md border px-3 py-1.5 text-xs font-semibold transition shadow-sm {{ $u->is_active ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:border-red-300' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 hover:border-green-300' }}"
                                                >
                                                    {{ $u->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="inline" onsubmit="return confirm('Voulez-vous vraiment forcer la réinitialisation du mot de passe de {{ $u->name }} ?')">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition shadow-sm"
                                                >
                                                    Réinitialiser MDP
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[10px]">Mon compte</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400 italic text-xs">
                                Aucun utilisateur trouvé.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-5">
                    {{ $users->links() }}
                </div>
            @endif
            
        @endif

     </main>
</body>
</html>
