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
    <title>Administration - Villa Boutanga</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-950 antialiased">
    <main class="mx-auto min-h-screen w-full max-w-7xl px-5 py-6 lg:px-8">
        <header class="flex flex-col gap-5 border-b border-neutral-200 pb-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-neutral-400">Admin global</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-950">Administration</h1>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-950 hover:text-neutral-950">
                        Deconnexion
                    </button>
                </form>
            </div>

            <nav class="overflow-x-auto" aria-label="Navigation administration">
                <div class="flex min-w-max gap-1">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                            class="rounded-md px-3 py-2 text-sm font-medium transition {{ $activeTab === $key ? 'bg-neutral-950 text-white' : 'text-neutral-600 hover:bg-white hover:text-neutral-950' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        </header>

        <section class="py-8">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4 text-sm font-medium text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4 text-sm font-medium text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('temp_password_info'))
                <div class="mb-6 rounded-lg bg-emerald-50 border-2 border-emerald-500 p-5 shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="rounded-full bg-emerald-500 p-1 text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-emerald-900">Mot de passe temporaire généré !</h3>
                            <p class="mt-1 text-xs text-emerald-700 leading-relaxed">
                                Un nouveau mot de passe a été généré pour <strong>{{ session('temp_password_info')['name'] }}</strong> ({{ session('temp_password_info')['email'] }}).
                                <br>Veuillez copier ce mot de passe et le lui communiquer de manière sécurisée. L'utilisateur devra le modifier lors de sa prochaine connexion.
                            </p>
                            <div class="mt-3 flex items-center gap-2">
                                <span class="text-xs font-semibold text-emerald-800">Mot de passe :</span>
                                <input type="text" readonly value="{{ session('temp_password_info')['password'] }}" class="font-mono text-sm border-emerald-300 bg-white border px-3 py-1 rounded text-emerald-950 select-all font-semibold outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-neutral-500">{{ $active['label'] }}</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-950">{{ $active['title'] }}</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-neutral-600">{{ $active['description'] }}</p>

                    @if($activeTab === 'audit')
                        <!-- Sous-onglets de l'Audit -->
                        <div class="mt-6 flex border-b border-neutral-200">
                            <a 
                                href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" 
                                class="border-b-2 px-4 py-2 text-sm font-medium transition {{ $subTab === 'logs' ? 'border-neutral-950 text-neutral-950 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-950' }}"
                            >
                                Journal d'Audit
                            </a>
                            <a 
                                href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" 
                                class="border-b-2 px-4 py-2 text-sm font-medium transition {{ $subTab === 'users' ? 'border-neutral-950 text-neutral-950 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-950' }}"
                            >
                                Sécurité des Comptes
                            </a>
                        </div>

                        @if($subTab === 'logs')
                            <!-- Formulaire de filtres -->
                            <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-6 bg-neutral-50 border border-neutral-200 rounded-lg p-4">
                                <input type="hidden" name="tab" value="audit">
                                <input type="hidden" name="sub" value="logs">
                                
                                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <div>
                                        <label for="tenant_id" class="block text-xs font-medium text-neutral-700">Établissement</label>
                                        <select id="tenant_id" name="tenant_id" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
                                            <option value="">Tous les établissements</option>
                                            @foreach($tenants as $tenant)
                                                <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                                    {{ $tenant->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="user_id" class="block text-xs font-medium text-neutral-700">Utilisateur</label>
                                        <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
                                            <option value="">Tous les utilisateurs</option>
                                            @foreach($allUsers as $u)
                                                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                                    {{ $u->name }} ({{ $u->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="event_type" class="block text-xs font-medium text-neutral-700">Type d'événement</label>
                                        <select id="event_type" name="event_type" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
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
                                        <label for="module" class="block text-xs font-medium text-neutral-700">Module</label>
                                        <select id="module" name="module" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
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
                                        <label for="date_from" class="block text-xs font-medium text-neutral-700">Du</label>
                                        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.2 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
                                    </div>

                                    <div>
                                        <label for="date_to" class="block text-xs font-medium text-neutral-700">Au</label>
                                        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-md border-neutral-300 bg-white px-3 py-1.2 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950">
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition">
                                        Réinitialiser
                                    </a>
                                    <button type="submit" class="rounded-md bg-neutral-950 px-3 py-1.5 text-xs font-medium text-white hover:bg-neutral-800 transition">
                                        Filtrer
                                    </button>
                                </div>
                            </form>

                            <!-- Tableau des logs -->
                            <div class="mt-6 overflow-x-auto border border-neutral-200 rounded-lg">
                                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                                    <thead class="bg-neutral-50 text-neutral-500 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Utilisateur</th>
                                            <th class="px-4 py-3">Module / Type</th>
                                            <th class="px-4 py-3">Description</th>
                                            <th class="px-4 py-3 text-right">Détails</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-200 bg-white text-neutral-700">
                                        @forelse($logs as $log)
                                            <tr class="hover:bg-neutral-50 transition">
                                                <td class="whitespace-nowrap px-4 py-3 font-medium text-neutral-900">
                                                    {{ $log->created_at->translatedFormat('d M Y à H:i') }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if($log->user)
                                                        <div class="font-semibold text-neutral-900">{{ $log->user->name }}</div>
                                                        <div class="text-[10px] text-neutral-500">{{ $log->user->email }}</div>
                                                        @if($log->tenant)
                                                            <span class="inline-flex mt-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[9px] font-semibold text-neutral-600">
                                                                {{ $log->tenant->name }}
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-neutral-400 italic">Visiteur Anonyme</span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    @php
                                                        $moduleBadgeColors = [
                                                            'auth' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                            'security' => 'bg-red-50 text-red-700 border-red-200',
                                                            'bookings' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                            'restaurant' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                            'shop' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                            'rooms' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                                            'users' => 'bg-sky-50 text-sky-700 border-sky-200',
                                                        ];
                                                        
                                                        $typeLabels = [
                                                            'login' => 'Connexion',
                                                            'logout' => 'Déconnexion',
                                                            'failed_login' => 'Échec connex.',
                                                            'access_denied' => 'Accès refusé',
                                                            'sensitive_action' => 'Action sensible',
                                                            'user_management' => 'Gestion',
                                                        ];
                                                        
                                                        $typeColors = [
                                                            'login' => 'text-blue-800 bg-blue-100',
                                                            'logout' => 'text-neutral-800 bg-neutral-100',
                                                            'failed_login' => 'text-amber-800 bg-amber-100',
                                                            'access_denied' => 'text-red-800 bg-red-100',
                                                            'sensitive_action' => 'text-purple-800 bg-purple-100',
                                                            'user_management' => 'text-emerald-800 bg-emerald-100',
                                                        ];
                                                    @endphp
                                                    <div class="flex flex-col gap-1 items-start">
                                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $moduleBadgeColors[$log->module] ?? 'bg-neutral-50 text-neutral-700 border-neutral-200' }}">
                                                            {{ strtoupper($log->module ?? 'global') }}
                                                        </span>
                                                        <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold {{ $typeColors[$log->event_type] ?? 'text-neutral-700 bg-neutral-100' }}">
                                                            {{ $typeLabels[$log->event_type] ?? $log->event_type }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 leading-relaxed">
                                                    <div class="text-neutral-900 font-medium">{{ $log->action }}</div>
                                                    <div class="mt-1 text-[10px] text-neutral-500">
                                                        IP: <span class="font-mono text-neutral-700">{{ $log->ip_address ?? 'N/A' }}</span> 
                                                        @if($log->user_agent)
                                                            <span class="mx-1 text-neutral-300">|</span> 
                                                            <span class="hover:text-neutral-900 cursor-help" title="{{ $log->user_agent }}">{{ Str::limit($log->user_agent, 40) }}</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap relative">
                                                    @if($log->payload)
                                                        <div x-data="{ open: false }">
                                                            <button @click="open = !open" type="button" class="inline-flex rounded border border-neutral-200 bg-white px-2 py-1 text-[10px] font-medium text-neutral-700 shadow-sm hover:bg-neutral-50 transition">
                                                                <span x-show="!open">Voir variables</span>
                                                                <span x-show="open">Fermer</span>
                                                            </button>
                                                            
                                                            <div x-show="open" @click.away="open = false" class="absolute z-10 mt-2 right-0 max-w-sm rounded-lg border border-neutral-200 bg-neutral-900 text-neutral-100 text-left p-3 shadow-xl font-mono text-[10px] max-h-48 overflow-auto">
                                                                <pre class="whitespace-pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="text-neutral-400 italic text-[10px]">Aucune variable</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-neutral-400 italic">
                                                    Aucun log d'audit correspondant aux critères de filtrage.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                {{ $logs->links() }}
                            </div>
                        @endif

                        @if($subTab === 'users')
                            <!-- Recherche utilisateur -->
                            <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-6 flex gap-2">
                                <input type="hidden" name="tab" value="audit">
                                <input type="hidden" name="sub" value="users">
                                <input 
                                    type="text" 
                                    name="user_search" 
                                    value="{{ request('user_search') }}" 
                                    placeholder="Rechercher par nom, email ou rôle..." 
                                    class="block w-full rounded-md border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-sm focus:border-neutral-950 focus:ring-neutral-950"
                                >
                                <button type="submit" class="rounded-md bg-neutral-950 px-4 py-1.5 text-xs font-medium text-white hover:bg-neutral-800 transition">
                                    Rechercher
                                </button>
                                @if(request()->filled('user_search'))
                                    <a href="{{ route('admin.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition flex items-center">
                                        Effacer
                                    </a>
                                @endif
                            </form>

                            <!-- Tableau des comptes -->
                            <div class="mt-6 overflow-x-auto border border-neutral-200 rounded-lg">
                                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                                    <thead class="bg-neutral-50 text-neutral-500 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3">Utilisateur</th>
                                            <th class="px-4 py-3">Rôle</th>
                                            <th class="px-4 py-3">Dernière Connexion</th>
                                            <th class="px-4 py-3">Activité / En ligne</th>
                                            <th class="px-4 py-3 text-right">Actions de Sécurité</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-200 bg-white text-neutral-700">
                                        @forelse($users as $u)
                                            <tr class="hover:bg-neutral-50 transition">
                                                <td class="px-4 py-3">
                                                    <div class="font-semibold text-neutral-900">{{ $u->name }}</div>
                                                    <div class="text-[10px] text-neutral-500">{{ $u->email }}</div>
                                                    @if($u->tenant)
                                                        <span class="inline-flex mt-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[9px] font-semibold text-neutral-600">
                                                            {{ $u->tenant->name }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex mt-1 rounded bg-neutral-950 px-1.5 py-0.5 text-[9px] font-semibold text-white">
                                                            Administration globale
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <span class="font-medium text-neutral-800 capitalize">{{ str_replace('_', ' ', $u->role) }}</span>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    @if($u->last_login_at)
                                                        {{ $u->last_login_at->translatedFormat('d M Y à H:i') }}
                                                    @else
                                                        <span class="text-neutral-400 italic">Aucune connexion</span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <!-- Statut Active -->
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium {{ $u->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $u->is_active ? 'Actif' : 'Désactivé' }}
                                                    </span>
                                                    
                                                    <!-- Statut En ligne -->
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ml-1 {{ $u->isOnline() ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-neutral-100 text-neutral-500' }}">
                                                        {{ $u->isOnline() ? 'En ligne' : 'Hors ligne' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <div class="inline-flex items-center gap-2">
                                                        @if($u->id !== Auth::id())
                                                            <!-- Désactiver / Activer -->
                                                            <form method="POST" action="{{ route('admin.users.toggle-active', $u) }}" class="inline">
                                                                @csrf
                                                                <button 
                                                                    type="submit" 
                                                                    class="inline-flex rounded border border-neutral-300 bg-white px-2.5 py-1.5 text-[11px] font-medium transition shadow-sm {{ $u->is_active ? 'text-red-700 hover:border-red-500 hover:bg-red-50' : 'text-green-700 hover:border-green-500 hover:bg-green-50' }}"
                                                                >
                                                                    {{ $u->is_active ? 'Désactiver' : 'Activer' }}
                                                                </button>
                                                            </form>
                                                            
                                                            <!-- Forcer réinitialisation mot de passe -->
                                                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="inline" onsubmit="return confirm('Voulez-vous vraiment forcer la réinitialisation du mot de passe de {{ $u->name }} ?')">
                                                                @csrf
                                                                <button 
                                                                    type="submit" 
                                                                    class="inline-flex rounded border border-neutral-300 bg-white px-2.5 py-1.5 text-[11px] font-medium text-neutral-700 transition shadow-sm hover:border-neutral-500 hover:text-neutral-900"
                                                                >
                                                                    Réinitialiser MDP
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-neutral-400 italic text-[10px]">Mon compte</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-neutral-400 italic">
                                                    Aucun utilisateur trouvé.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                {{ $users->links() }}
                            </div>
                        @endif
                    @else
                        <!-- Reste des onglets placeholders -->
                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
                            @foreach($active['items'] as $item)
                                <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                                    <p class="text-sm font-medium text-neutral-900">{{ $item }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">Backend a connecter prochainement.</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($activeTab === 'audit' && isset($auditStats))
                    <!-- Sidebar statistique de l'onglet Audit -->
                    <aside class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm self-start">
                        <p class="text-sm font-semibold text-neutral-950">Statistiques de sécurité</p>
                        <div class="mt-4 space-y-4 text-xs">
                            <div class="border-b border-neutral-100 pb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-500">Total des logs d'audit</span>
                                    <span class="font-semibold text-neutral-950">{{ $auditStats['total_logs'] }}</span>
                                </div>
                            </div>
                            
                            <div class="border-b border-neutral-100 pb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-500 font-medium text-red-600">Accès refusés</span>
                                    <span class="font-bold text-red-600">{{ $auditStats['access_denied'] }}</span>
                                </div>
                            </div>

                            <div class="border-b border-neutral-100 pb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-500 font-medium text-amber-600 font-medium">Échecs de connexion</span>
                                    <span class="font-bold text-amber-600 font-bold">{{ $auditStats['failed_logins'] }}</span>
                                </div>
                            </div>

                            <div class="border-b border-neutral-100 pb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-500">Nombre total de comptes</span>
                                    <span class="font-semibold text-neutral-950">{{ $auditStats['total_users'] }}</span>
                                </div>
                            </div>

                            <div class="border-b border-neutral-100 pb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-500 text-green-600">Comptes actifs</span>
                                    <span class="font-semibold text-green-700">{{ $auditStats['active_users'] }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500 text-red-600">Comptes désactivés</span>
                                <span class="font-semibold text-red-700">{{ $auditStats['inactive_users'] }}</span>
                            </div>
                        </div>
                    </aside>
                @else
                    <aside class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-semibold text-neutral-950">Etat du module</p>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                                <span class="text-neutral-500">Interface</span>
                                <span class="font-medium text-neutral-950">Prete</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                                <span class="text-neutral-500">Backend</span>
                                <span class="font-medium text-neutral-400">A developper</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500">Acces</span>
                                <span class="font-medium text-neutral-950">Admin</span>
                            </div>
                        </div>
                    </aside>
                @endif
            </div>
        </section>

     </main>
</body>
</html>
