﻿@php
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
            'label' => 'Import/Export',
            'title' => 'Import et export',
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
                <svg class="h-4 w-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4 text-xs font-bold text-red-800 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('temp_password_info'))
            <div class="mb-6 rounded-lg bg-emerald-50 border-2 border-emerald-500 p-5 shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="rounded-full bg-emerald-500 p-1 text-white shadow-sm shrink-0">
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
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">ETABLISSEMENTS</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Gestion des Établissements</h1>
                    <p class="text-xs text-slate-500 mt-1">Gérer les informations générales, le statut et les paramètres des filiales de l'ONG.</p>
                </div>
                <a href="{{ route('admin.tenants.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition group">
                    <svg class="h-4 w-4 transition-transform group-hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nouvel Établissement
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                @foreach($tenants as $tenant)
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between group hover:shadow-md hover:border-slate-300 transition-all duration-200">
                        <div>
                            <!-- Header with logo / image placeholder -->
                            <div class="h-32 bg-slate-900 flex items-center justify-center relative">
                                @if(!empty($tenant->settings['logo']))
                                    <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="Logo {{ $tenant->name }}" class="h-20 object-contain">
                                @else
                                    <div class="text-indigo-400 flex flex-col items-center">
                                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                        </svg>
                                        <span class="text-[9px] font-bold tracking-widest uppercase text-slate-500 mt-2">Aucun logo</span>
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Details -->
                            <div class="p-5">
                                <h3 class="text-base font-bold text-slate-800 tracking-tight">{{ $tenant->name }}</h3>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $tenant->slug }}</p>
                                
                                <div class="mt-4 space-y-2.5 text-xs text-slate-600 border-t border-slate-100 pt-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Pays :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->settings['country'] ?? 'Cameroun' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Devise :</span>
                                        <span class="font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5 text-[9px]">{{ $tenant->currency }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Utilisateurs :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->users_count ?? $tenant->users()->count() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex justify-end">
                            <a 
                                href="{{ route('admin.tenants.show', $tenant) }}"
                                class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Gérer
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($activeTab === 'imports')
            <!-- ================= IMPORT & EXPORT LAYOUT ================= -->
            
            <!-- Breadcrumb Path -->
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">IMPORT / EXPORT</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Import & Export de Données</h1>
                    <p class="text-xs text-slate-500 mt-1">Exportez les journaux et rapports d'activité de la plateforme ou planifiez des imports.</p>
                </div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block sm:text-right">
                    Format recommandé : CSV Excel (UTF-8 BOM, délimiteur ;)
                </span>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] mt-6">
                <!-- Main Content Area -->
                <div class="space-y-6">
                    
                    <!-- Section: Exports -->
                    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <div class="rounded-full bg-indigo-50 p-2 text-indigo-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-md font-bold text-slate-800 tracking-tight">Exportation des données métiers</h2>
                                <p class="text-[11px] text-slate-500">Téléchargez des extractions de données consolidées au format CSV compatible Microsoft Excel.</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <!-- Card: Supervision Export -->
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-5 hover:border-indigo-100 hover:bg-slate-50 transition duration-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-800">Rapport de Supervision des Établissements</h3>
                                    </div>
                                    <p class="text-xs text-slate-500 max-w-xl">
                                        Génère un rapport consolidé de tous les établissements actifs ou inactifs (contacts, devise, pays, nombre d'utilisateurs et de réservations enregistrés).
                                    </p>
                                </div>
                                <div class="shrink-0">
                                    <a href="{{ route('admin.export.supervision') }}" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-xs">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Exporter la Supervision
                                    </a>
                                </div>
                            </div>

                            <!-- Card: Database Backup -->
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-5 hover:border-indigo-100 hover:bg-slate-50 transition duration-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-800">Sauvegarde de la Base de Données (Backup)</h3>
                                    </div>
                                    <p class="text-xs text-slate-500 max-w-xl">
                                        Génère une sauvegarde complète de la base de données PostgreSQL au format SQL compressé dans un fichier ZIP pour archivage.
                                    </p>
                                </div>
                                <div class="shrink-0">
                                    <a href="{{ route('admin.export.backup') }}" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-xs">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Télécharger le Backup (.zip)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Imports -->
                    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm opacity-85">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <div class="rounded-full bg-slate-100 p-2 text-slate-500">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-md font-bold text-slate-700 tracking-tight">Importation de données</h2>
                                <p class="text-[11px] text-slate-500">Préparez l'initialisation de nouveaux établissements ou utilisateurs par fichier structuré.</p>
                            </div>
                        </div>

                        <div class="grid gap-4 mt-6 sm:grid-cols-2">
                            <!-- Card: Import tenants -->
                            <div class="rounded-lg border border-slate-100 bg-slate-50/40 p-4 relative overflow-hidden">
                                <span class="absolute top-3 right-3 inline-flex items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-[9px] font-bold text-slate-600 uppercase">Bientôt</span>
                                <h3 class="text-xs font-bold text-slate-600">Import d'Établissements</h3>
                                <p class="text-[11px] text-slate-400 mt-1">Création de masse de nouveaux établissements avec leurs configurations initiales.</p>
                                <div class="mt-4 flex gap-2">
                                    <button disabled class="rounded bg-slate-200 px-3 py-1.5 text-[10px] font-bold text-slate-400 cursor-not-allowed">
                                        Sélectionner fichier
                                    </button>
                                </div>
                            </div>

                            <!-- Card: Import users -->
                            <div class="rounded-lg border border-slate-100 bg-slate-50/40 p-4 relative overflow-hidden">
                                <span class="absolute top-3 right-3 inline-flex items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-[9px] font-bold text-slate-600 uppercase">Bientôt</span>
                                <h3 class="text-xs font-bold text-slate-600">Import d'Utilisateurs</h3>
                                <p class="text-[11px] text-slate-400 mt-1">Invitation groupée et affectation de managers ou réceptionnistes à des filiales.</p>
                                <div class="mt-4 flex gap-2">
                                    <button disabled class="rounded bg-slate-200 px-3 py-1.5 text-[10px] font-bold text-slate-400 cursor-not-allowed">
                                        Sélectionner fichier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Guide -->
                <aside class="space-y-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Guide d'utilisation</h3>
                        <div class="mt-4 space-y-4 text-xs text-slate-600 leading-relaxed">
                            <div>
                                <p class="font-bold text-slate-700">Encodage & Compatibilité</p>
                                <p class="mt-1">Les exports incluent le marqueur d'ordre d'octets **BOM UTF-8** pour assurer le bon rendu des accents français sous Excel.</p>
                            </div>
                            <div class="border-t border-slate-100 pt-3">
                                <p class="font-bold text-slate-700">Délimiteur</p>
                                <p class="mt-1">Le délimiteur utilisé est le **point-virgule (;)**, standard de facto des versions françaises de tableurs.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-5 shadow-sm">
                        <h3 class="text-xs font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Sécurité
                        </h3>
                        <p class="mt-2 text-xs text-indigo-950 leading-relaxed">
                            Toutes les opérations d'export de données métiers sont historisées dans le **Journal d'Audit** sous le module `sécurité` / `paramètres`.
                        </p>
                    </div>
                </aside>
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
                                <p class="mt-1 text-xs text-slate-500">Service backend Ã  connecter prochainement.</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm self-start">
                    <p class="text-sm font-bold text-slate-800 font-heading">État du module</p>
                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-500">Interface</span>
                            <span class="font-bold bg-green-50 text-green-700 px-2 py-0.5 rounded border border-green-200">Prête</span>
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
                    Dernière mise Ã  jour : {{ now()->translatedFormat('d F Y â€“ H:i') }}
                </span>
            </div>

            <!-- 5 Stats Cards Grid -->
            @if(isset($auditStats))
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mt-6">
                    <!-- Total logs -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4">
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
                                            <span class="text-slate-300">â€¢</span>
                                            <span class="truncate max-w-[260px] text-slate-600 hover:text-slate-800 cursor-help" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Details Trigger (AlpineJS Popover) -->
                                <div class="text-right text-xs whitespace-nowrap relative self-center md:self-start mt-2 md:mt-0" x-data="{ open: false }">
                                    @if($log->payload)
                                        <button @click="open = !open" type="button" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-bold transition">
                                            <span>Voir variables</span>
                                            <span class="text-[9px]">â†—</span>
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
