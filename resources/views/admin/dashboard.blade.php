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
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-neutral-500">{{ $active['label'] }}</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-950">{{ $active['title'] }}</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-neutral-600">{{ $active['description'] }}</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach($active['items'] as $item)
                            <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="text-sm font-medium text-neutral-900">{{ $item }}</p>
                                <p class="mt-1 text-xs text-neutral-500">Backend a connecter prochainement.</p>
                            </div>
                        @endforeach
                    </div>
                </div>

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
            </div>
        </section>
    </main>
</body>
</html>
