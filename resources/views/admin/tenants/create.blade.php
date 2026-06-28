@php
    $tabs = [
        'dashboard' => ['label' => 'Supervision'],
        'tenants' => ['label' => 'Etablissements'],
        'managers' => ['label' => 'Managers'],
        'roles' => ['label' => 'Roles'],
        'modules' => ['label' => 'Modules'],
        'audit' => ['label' => 'Audit'],
        'support' => ['label' => 'Support'],
        'settings' => ['label' => 'Configuration'],
        'billing' => ['label' => 'Licences'],
        'imports' => ['label' => 'Import/Export'],
        'system' => ['label' => 'Systeme'],
    ];
    $activeTab = 'tenants';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouvel Établissement - Administration</title>
    <meta name="description" content="Créer un nouvel établissement dans la plateforme Villa Boutanga.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased font-body">

    <!-- Top Navigation Bar -->
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
    </header>

    <main class="mx-auto min-h-screen w-full max-w-4xl px-5 py-8 lg:px-8" x-data="{
        name: '',
        slug: '',
        autoSlug: true,
        themePrimary: '#391F0E',
        themeSecondary: '#CCAB87',
        themeAccent: '#EED4A3',
        themeDark: '#0F0201',
        themeSurfaceDark: '#2C1810',
        themeTextOnLight: '#391F0E',
        themeTextOnDark: '#CCAB87',
        logoPreview: null,
        handleLogoChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.logoPreview = URL.createObjectURL(file);
            }
        },
        generateSlug() {
            if (this.autoSlug) {
                this.slug = this.name
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        },
        applyPalette(p, s, a, d, sd, tl, td) {
            this.themePrimary = p;
            this.themeSecondary = s;
            this.themeAccent = a;
            this.themeDark = d;
            this.themeSurfaceDark = sd;
            this.themeTextOnLight = tl;
            this.themeTextOnDark = td;
        }
    }">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6">
            <a href="{{ route('admin.dashboard', ['tab' => 'tenants']) }}" class="hover:text-indigo-600 transition font-semibold">Établissements</a>
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-600 font-bold">Nouvel Établissement</span>
        </nav>

        <!-- Page Title -->
        <div class="border-b border-slate-200 pb-5 mb-8">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Créer un Nouvel Établissement</h1>
            <p class="text-xs text-slate-500 mt-1.5">Remplissez les informations ci-dessous pour enregistrer un nouvel établissement dans la plateforme.</p>
        </div>

        <!-- Validation Errors -->
        @if($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div>
                        <h3 class="text-xs font-bold text-red-800">Erreurs de validation</h3>
                        <ul class="mt-1.5 list-disc list-inside text-xs text-red-700 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.tenants.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf

            <!-- ======= SECTION 1: Identité ======= -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-600/20 p-2">
                        <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide">Identité de l'Établissement</h2>
                        <p class="text-[10px] text-slate-400">Nom, identifiant unique et logo</p>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    <!-- Logo Upload -->
                    <div>
                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2">Logo de l'établissement</label>
                        <div class="flex items-center gap-5">
                            <div class="h-20 w-28 bg-slate-950 border-2 border-dashed border-slate-700 rounded-xl flex items-center justify-center overflow-hidden transition-colors hover:border-indigo-500">
                                <template x-if="logoPreview">
                                    <img :src="logoPreview" alt="Logo preview" class="h-16 object-contain">
                                </template>
                                <template x-if="!logoPreview">
                                    <div class="text-center">
                                        <svg class="h-6 w-6 text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                        </svg>
                                        <span class="text-[8px] text-slate-500 font-bold mt-1 block">PNG, JPG</span>
                                    </div>
                                </template>
                            </div>
                            <div>
                                <input type="file" name="logo" accept="image/*" @change="handleLogoChange($event)" class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1">Format recommandé : PNG transparent. Max 2 Mo.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Name -->
                        <div>
                            <label for="create-name" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom de l'établissement <span class="text-red-400">*</span></label>
                            <input type="text" id="create-name" name="name" x-model="name" @input="generateSlug()" required value="{{ old('name') }}"
                                   placeholder="Ex: Villa Boutanga"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        </div>

                        <!-- Slug -->
                        <div>
                            <label for="create-slug" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                Code unique / Slug <span class="text-red-400">*</span>
                            </label>
                            <input type="text" id="create-slug" name="slug" x-model="slug" @input="autoSlug = false" required value="{{ old('slug') }}"
                                   placeholder="ex: villa-boutanga"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            <p class="text-[10px] text-slate-400 mt-1">Lettres minuscules, chiffres et tirets uniquement. Généré automatiquement à partir du nom.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======= SECTION 2: Coordonnées ======= -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                    <div class="rounded-lg bg-emerald-600/20 p-2">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide">Coordonnées & Localisation</h2>
                        <p class="text-[10px] text-slate-400">Adresse, contact et devise</p>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Country -->
                        <div>
                            <label for="create-country" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Pays</label>
                            <input type="text" id="create-country" name="country" value="{{ old('country', 'Cameroun') }}"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="create-currency" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Devise par défaut <span class="text-red-400">*</span></label>
                            <input type="text" id="create-currency" name="currency" value="{{ old('currency', 'XAF') }}" required maxlength="3"
                                   placeholder="XAF"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono uppercase outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <label for="create-address" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse</label>
                        <input type="text" id="create-address" name="address" value="{{ old('address') }}"
                               placeholder="Ex: Bangoulap, Ouest Cameroun"
                               class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Phone -->
                        <div>
                            <label for="create-phone" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                            <input type="text" id="create-phone" name="phone" value="{{ old('phone') }}"
                                   placeholder="+237 699 000 000"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="create-email" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse e-mail</label>
                            <input type="email" id="create-email" name="email" value="{{ old('email') }}"
                                   placeholder="contact@etablissement.com"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======= SECTION 3: Thème & Couleurs ======= -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                    <div class="rounded-lg bg-violet-600/20 p-2">
                        <svg class="h-5 w-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide">Thème & Couleurs</h2>
                        <p class="text-[10px] text-slate-400">Choisissez une palette ou personnalisez les couleurs</p>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    <!-- Preset Palettes -->
                    <div>
                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-3">Palettes prédéfinies</label>
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- Terracotta -->
                            <button type="button"
                                @click="applyPalette('#391F0E', '#CCAB87', '#EED4A3', '#0F0201', '#2C1810', '#391F0E', '#CCAB87')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #391F0E 50%, #CCAB87 50%);"
                                title="Terracotta (Original)">
                                <span x-show="themePrimary === '#391F0E'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                            <!-- Royal Blue -->
                            <button type="button"
                                @click="applyPalette('#1E3A8A', '#3B82F6', '#93C5FD', '#0F172A', '#1E293B', '#FFFFFF', '#93C5FD')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #1E3A8A 50%, #3B82F6 50%);"
                                title="Bleu Royal">
                                <span x-show="themePrimary === '#1E3A8A'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                            <!-- Forest Green -->
                            <button type="button"
                                @click="applyPalette('#064E3B', '#10B981', '#A7F3D0', '#022C22', '#064E3B', '#FFFFFF', '#A7F3D0')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #064E3B 50%, #10B981 50%);"
                                title="Vert Forêt">
                                <span x-show="themePrimary === '#064E3B'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                            <!-- Imperial Purple -->
                            <button type="button"
                                @click="applyPalette('#4C1D95', '#8B5CF6', '#DDD6FE', '#1E1B4B', '#312E81', '#FFFFFF', '#DDD6FE')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #4C1D95 50%, #8B5CF6 50%);"
                                title="Violet Impérial">
                                <span x-show="themePrimary === '#4C1D95'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                            <!-- Rose -->
                            <button type="button"
                                @click="applyPalette('#831843', '#EC4899', '#FCE7F3', '#500724', '#831843', '#FFFFFF', '#FCE7F3')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #831843 50%, #EC4899 50%);"
                                title="Rose Vibrant">
                                <span x-show="themePrimary === '#831843'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                            <!-- Sunset Orange -->
                            <button type="button"
                                @click="applyPalette('#7C2D12', '#F97316', '#FFEDD5', '#431407', '#7C2D12', '#FFFFFF', '#FFEDD5')"
                                class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm"
                                style="background: linear-gradient(135deg, #7C2D12 50%, #F97316 50%);"
                                title="Orange Couchant">
                                <span x-show="themePrimary === '#7C2D12'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                            </button>
                        </div>
                    </div>

                    <!-- Live Preview Card -->
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-4 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase bg-slate-50 border-b border-slate-200">Aperçu en temps réel</div>
                        <div class="p-4 flex items-center gap-4">
                            <div class="rounded-lg overflow-hidden shadow-md border border-slate-200 w-48 shrink-0">
                                <div class="h-10 flex items-center justify-center" :style="'background-color:' + themePrimary">
                                    <span class="text-[9px] font-bold tracking-widest uppercase" :style="'color:' + themeTextOnDark" x-text="name || 'NOM ÉTABLISSEMENT'"></span>
                                </div>
                                <div class="h-6 flex items-center justify-center" :style="'background-color:' + themeSecondary">
                                    <span class="text-[8px] font-semibold" :style="'color:' + themeTextOnLight">Menu Navigation</span>
                                </div>
                                <div class="h-10 bg-white flex items-center justify-center">
                                    <span class="text-[8px] text-slate-400">Contenu principal</span>
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 leading-relaxed">
                                <p class="font-semibold text-slate-700 mb-1">Aperçu du thème</p>
                                <p class="text-[10px]">Les couleurs sélectionnées seront appliquées à l'interface de l'établissement.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Color Pickers Grid -->
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Primaire</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themePrimary" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[primary]" x-model="themePrimary" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Secondaire</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeSecondary" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[secondary]" x-model="themeSecondary" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Accent</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeAccent" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[accent]" x-model="themeAccent" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Fond Sombre</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeDark" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[dark]" x-model="themeDark" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Surface Sombre</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeSurfaceDark" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[surface_dark]" x-model="themeSurfaceDark" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Clair</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeTextOnLight" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[text_on_light]" x-model="themeTextOnLight" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Sombre</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeTextOnDark" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                <input type="text" name="theme[text_on_dark]" x-model="themeTextOnDark" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======= ACTIONS ======= -->
            <div class="flex items-center justify-between pt-2 pb-12">
                <a href="{{ route('admin.dashboard', ['tab' => 'tenants']) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Retour
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Créer l'Établissement
                </button>
            </div>
        </form>
    </main>
</body>
</html>
