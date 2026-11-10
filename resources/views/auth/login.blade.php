@php
    $tenant = \App\Models\Tenant::current();
    $tenantName = $tenant?->name ?? 'Établissement';
    // Logo importé par le manager depuis les paramètres de l'application
    // (stocké localement dans le storage de ce tenant).
    $tenantLogo = ($tenant && !empty($tenant->settings['logo'])) ? asset('storage/' . $tenant->settings['logo']) : null;
    $contactEmail = $tenant?->email;
    $contactPhone = $tenant?->phone;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $tenantName }}</title>
    @include('partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400..700&family=Inter:wght@300..700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: {{ $tenant->settings['theme']['primary'] ?? '#391F0E' }};
            --color-secondary: {{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }};
            --color-accent: {{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }};
            --color-dark: {{ $tenant->settings['theme']['dark'] ?? '#0F0201' }};
            --color-surface-dark: {{ $tenant->settings['theme']['surface_dark'] ?? '#2C1810' }};
            --color-text-on-light: {{ $tenant->settings['theme']['text_on_light'] ?? '#391F0E' }};
            --color-text-on-dark: {{ $tenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }};

            --login-ink: #30271f;
            --login-brown: #3b281d;
            --login-brown-hover: #553927;
            --login-muted: #70675e;
            --login-quiet: #786e64;
            --login-bg: #fffdfa;
            --login-line: #e6e1d8;
            --login-input-border: #968978;
            --login-sage: #3d6957;
            --login-red: #a03735;
        }

        body.login-body {
            margin: 0;
            min-height: 100dvh;
            background: var(--login-bg);
            color: var(--login-ink);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .login-page {
            min-height: 100dvh;
            display: grid;
            grid-template-columns: 1fr 1.12fr;
            background: var(--login-bg);
        }

        /* ── Panneau gauche : identité Wetchah ───────────────────────────────── */
        .login-brand {
            background: var(--login-brown);
            color: #f7eddd;
            padding: 42px 48px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .login-brand-wordmark {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 600;
            font-size: 31px;
            line-height: 1;
            letter-spacing: -1.1px;
            color: #f7eddd;
            margin: 0;
        }

        .login-brand-period {
            color: #985331;
        }

        .login-brand-sub {
            margin: 6px 0 0;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-weight: 600;
            color: #dac3a9;
        }

        .login-brand-copy {
            margin-top: auto;
            padding-top: 26px;
            z-index: 1;
        }

        .login-brand-copy h2 {
            margin: 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 500;
            font-size: 36px;
            line-height: 1.25;
            letter-spacing: -.02em;
            color: #f7eddd;
        }

        .login-brand-copy p {
            margin: 16px 0 0;
            color: #d6bfa5;
            font-size: 12.5px;
            line-height: 1.8;
            max-width: 310px;
        }

        .login-architecture {
            max-width: 340px;
            width: 85%;
            margin: 32px auto 20px;
            flex: 1;
            max-height: 340px;
            min-height: 150px;
            color: #a38362;
        }

        .login-brand-foot {
            border-top: 1px solid #d5b28f3b;
            padding-top: 18px;
            margin: auto 0 0;
            font-size: 9px;
            color: #d6bfa5;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        /* ── Panneau droit : formulaire de connexion ─────────────────────────── */
        .login-content {
            padding: 55px 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--login-bg);
        }

        .login-form-wrap {
            width: 100%;
            max-width: 366px;
        }

        .mobile-login-brand {
            display: none;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 600;
            font-size: 26px;
            line-height: 1;
            letter-spacing: -1px;
            color: var(--login-brown);
            margin: 0 0 35px;
        }

        .login-place {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 29px;
            font-size: 13px;
            font-weight: 500;
            color: var(--login-ink);
        }

        .place-seal {
            border: 1px solid #dacdbb;
            background: #f2ecdf;
            width: 39px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px 18px 4px 4px;
            color: #8b6c4d;
            flex-shrink: 0;
            overflow: hidden;
        }

        .place-seal svg {
            width: 20px;
            height: 20px;
        }

        .place-seal-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 3px;
            background: #ffffff;
        }

        .login-form-wrap h1 {
            margin: 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 550;
            font-size: 34px;
            line-height: 1.2;
            letter-spacing: -.025em;
            color: var(--login-ink);
        }

        .login-form-wrap h1:focus {
            outline: none;
        }

        .login-form-wrap > .intro {
            font-size: 14px;
            line-height: 1.7;
            color: var(--login-muted);
            margin: 10px 0 29px;
        }

        /* ── Alertes statut / erreurs serveur ────────────────────────────────── */
        .login-alert {
            margin-bottom: 21px;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12.5px;
            line-height: 1.55;
        }

        .login-alert svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .login-alert-title {
            margin: 0;
            font-weight: 600;
            font-size: 12.5px;
        }

        .login-alert-text {
            margin: 3px 0 0;
            font-size: 12px;
            line-height: 1.5;
        }

        .login-alert--error {
            background: #fff4ef;
            border: 1px solid #e8c6be;
            color: #8b3e35;
        }

        .login-alert--warning {
            background: #faf3e7;
            border: 1px solid #e3cfa8;
            color: #73531f;
        }

        .login-alert--info {
            background: #eef6ef;
            border: 1px solid #c5decb;
            color: #2b503b;
        }

        /* ── Champs et contrôles de formulaire ───────────────────────────────── */
        .login-form .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 19px;
        }

        .login-form .field > label {
            font-size: 12px;
            font-weight: 550;
            color: #594839;
        }

        .login-form .field input {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--login-input-border);
            border-radius: 6px;
            background: #ffffff;
            color: var(--login-ink);
            font-family: inherit;
            font-size: max(1rem, 14px);
            line-height: 1.5;
            box-sizing: border-box;
            transition: border-color .15s, background-color .15s;
        }

        .login-form .field input::placeholder {
            color: var(--login-quiet);
            opacity: 1;
        }

        .login-form .field input:hover {
            border-color: #756859;
        }

        .login-form .field input[aria-invalid="true"],
        .login-form .field input:user-invalid {
            border-color: var(--login-red);
            background: #fffaf8;
        }

        .login-field-error {
            margin: 2px 0 0;
            color: var(--login-red);
            font-size: 11.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .login-field-error svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 3px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 5px;
            background: transparent;
            color: var(--login-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: color .15s, background-color .15s;
        }

        .password-toggle:hover {
            background: #efeae1;
            color: var(--login-ink);
        }

        .password-toggle svg {
            width: 17px;
            height: 17px;
            pointer-events: none;
        }

        .login-submit {
            width: 100%;
            min-height: 46px;
            margin-top: 3px;
            padding: 10px 16px;
            border: 1px solid var(--login-brown);
            border-radius: 7px;
            background: var(--login-brown);
            color: #ffffff;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 550;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            cursor: pointer;
            transition: background-color .15s, border-color .15s, opacity .15s;
        }

        .login-submit:hover {
            background: var(--login-brown-hover);
            border-color: var(--login-brown-hover);
        }

        .login-submit:disabled {
            opacity: .72;
            cursor: wait;
        }

        .login-submit svg {
            width: 16px;
            height: 16px;
            pointer-events: none;
        }

        .login-support {
            text-align: center;
            font-size: 12px;
            margin: 19px 0 22px;
            color: var(--login-muted);
        }

        .login-support button {
            border: 0;
            background: transparent;
            color: var(--login-brown);
            font-family: inherit;
            font-size: 12px;
            font-weight: 550;
            padding: 6px 4px;
            text-decoration: underline;
            text-underline-offset: 3px;
            border-radius: 4px;
            cursor: pointer;
        }

        .login-support button:hover {
            color: var(--login-brown-hover);
        }

        .login-foot {
            border-top: 1px solid var(--login-line);
            padding-top: 18px;
            margin: 0;
            text-align: center;
            font-size: 11px;
            color: var(--login-quiet);
        }

        /* Focus clavier haute visibilité sur le thème clair */
        .login-page :where(button, input, a):focus-visible,
        dialog.login-dialog :where(button, a):focus-visible {
            outline: 2px solid var(--login-sage);
            outline-offset: 2px;
        }

        .visually-hidden {
            position: absolute !important;
            clip-path: inset(50%) !important;
            overflow: hidden !important;
            width: 1px !important;
            height: 1px !important;
            margin: -1px !important;
            padding: 0 !important;
            border: 0 !important;
            white-space: nowrap !important;
        }

        [hidden] {
            display: none !important;
        }

        /* Neutraliser le fond jaune d'autofill sur thème clair */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--login-ink) !important;
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            box-shadow: 0 0 0 1000px #ffffff inset !important;
            caret-color: var(--login-ink) !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* ── Boîte de dialogue native : Contacter l'administrateur ────────────── */
        dialog.login-dialog {
            width: 460px;
            max-width: calc(100% - 32px);
            max-height: calc(100dvh - 44px);
            margin: auto;
            border: 1px solid #ded5c7;
            border-radius: 14px;
            padding: 0;
            background: #ffffff;
            color: var(--login-ink);
            box-shadow: 0 24px 90px rgba(38, 24, 11, 0.22);
            overflow: auto;
        }

        dialog.login-dialog:modal {
            overscroll-behavior: contain;
        }

        dialog.login-dialog::backdrop {
            background: rgba(36, 27, 18, 0.42);
            overflow: hidden;
            overscroll-behavior: contain;
        }

        dialog.login-dialog[open] {
            animation: dialogReveal .13s ease-out;
        }

        @keyframes dialogReveal {
            from { opacity: 0; transform: translateY(3px); }
            to { opacity: 1; transform: none; }
        }

        .login-dialog-head {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--login-line);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .login-dialog-head h2 {
            margin: 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 550;
            font-size: 22px;
            line-height: 1.25;
            color: var(--login-ink);
        }

        .dialog-close {
            width: 36px;
            height: 36px;
            margin: -4px -8px 0 0;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--login-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .dialog-close:hover {
            background: #efeae1;
            color: var(--login-ink);
        }

        .dialog-close svg {
            width: 17px;
            height: 17px;
            pointer-events: none;
        }

        .login-dialog-body {
            padding: 20px 24px;
            font-size: 13px;
            line-height: 1.75;
            color: var(--login-muted);
        }

        .login-dialog-body p {
            margin: 0;
        }

        .login-dialog-body p + p {
            margin-top: 12px;
        }

        .login-contact-list {
            margin: 14px 0 0;
            padding: 12px 14px;
            list-style: none;
            background: #faf8f4;
            border: 1px solid var(--login-line);
            border-radius: 8px;
            display: grid;
            gap: 8px;
            font-size: 12.5px;
            color: var(--login-ink);
        }

        .login-contact-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .login-contact-list span {
            color: var(--login-muted);
        }

        .login-contact-list a {
            color: var(--login-brown);
            font-weight: 550;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .login-dialog-foot {
            padding: 14px 24px;
            border-top: 1px solid var(--login-line);
            display: flex;
            justify-content: flex-end;
            background: #faf8f4;
        }

        .dialog-btn-primary {
            border: 1px solid var(--login-brown);
            background: var(--login-brown);
            color: #ffffff;
            border-radius: 7px;
            min-height: 39px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 550;
            cursor: pointer;
        }

        .dialog-btn-primary:hover {
            background: var(--login-brown-hover);
            border-color: var(--login-brown-hover);
        }

        /* ── Responsive ──────────────────────────────────────────────────────── */
        @media (max-width: 1150px) {
            .login-brand {
                padding: 35px;
            }
            .login-brand-copy h2 {
                font-size: 31px;
            }
        }

        @media (max-width: 850px) {
            .login-content {
                padding: 35px 28px;
            }
            .login-brand-copy h2 {
                font-size: 27px;
            }
            .login-form-wrap h1 {
                font-size: 30px;
            }
        }

        @media (max-width: 640px) {
            .login-page {
                display: block;
            }
            .login-brand {
                display: none;
            }
            .login-content {
                padding: 32px 24px;
                min-height: 100dvh;
            }
            .login-form-wrap {
                max-width: 390px;
            }
            .mobile-login-brand {
                display: block;
            }
            .login-place {
                font-size: 12px;
                margin-bottom: 22px;
            }
            .place-seal {
                width: 34px;
                height: 37px;
            }
            .login-form-wrap h1 {
                font-size: 32px;
            }
            .login-form-wrap > .intro {
                font-size: 13px;
                margin-bottom: 26px;
            }
            .login-form .field {
                margin-bottom: 20px;
            }
            .login-submit {
                min-height: 48px;
            }
        }

        @media (pointer: coarse) {
            .login-form .field input {
                min-height: 48px;
            }
            .password-toggle {
                width: 44px;
                height: 44px;
            }
            .login-submit,
            .dialog-btn-primary,
            .dialog-close {
                min-height: 48px;
            }
            .login-support button {
                padding: 10px 8px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

        @media (prefers-contrast: more) {
            :root {
                --login-muted: #4b4034;
                --login-quiet: #4b4034;
                --login-line: #b9a68d;
                --login-input-border: #5e5144;
            }
        }
    </style>
</head>
<body class="login-body">

<main id="main" class="login-page">

    {{-- ===== PANNEAU GAUCHE — Branding Wetchah ===== --}}
    <section class="login-brand" aria-label="Wetchah, gestion hôtelière">
        <div>
            <p class="login-brand-wordmark">Wetchah<span class="login-brand-period">.</span></p>
            <p class="login-brand-sub">Gestion hôtelière</p>
        </div>

        <div class="login-brand-copy">
            <h2>Une journée d’hôtel,<br>bien organisée.</h2>
            <p>Les services de votre établissement.<br>Un espace pour travailler ensemble.</p>
        </div>

        <svg class="login-architecture" viewBox="0 0 360 310" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
            <path d="M42 288V130a138 138 0 0 1 276 0v158M69 288V132a111 111 0 0 1 222 0v156M97 288V135a83 83 0 0 1 166 0v153M126 288V137a54 54 0 0 1 108 0v151" stroke-width=".8"/>
            <path d="M15 289h330M15 300h330M180 84v205M126 184h108M97 154h29m108 0h29M69 123h29m164 0h29" stroke-width=".8"/>
            <path d="M143 289V152a37 37 0 0 1 74 0v137" fill="#684a34" fill-opacity=".2" stroke-width=".7"/>
            <circle cx="196" cy="222" r="3"/>
        </svg>

        <p class="login-brand-foot">RÉCEPTION · RESTAURATION · ÉCONOMAT · FINANCE</p>
    </section>

    {{-- ===== PANNEAU DROIT — Connexion à l'établissement ===== --}}
    <section class="login-content" aria-labelledby="login-title">
        <div class="login-form-wrap">

            {{-- Marque affichée sur mobile lorsque le panneau gauche est masqué --}}
            <p class="mobile-login-brand">Wetchah<span class="login-brand-period">.</span></p>

            {{-- Sceau et nom de l'établissement --}}
            <div class="login-place">
                <span class="place-seal">
                    @if($tenantLogo)
                        <img src="{{ $tenantLogo }}" alt="" class="place-seal-img">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M10 12h4"/>
                            <path d="M10 8h4"/>
                            <path d="M14 21v-3a2 2 0 0 0-4 0v3"/>
                            <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/>
                            <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/>
                        </svg>
                    @endif
                </span>
                <span>{{ $tenantName }}</span>
            </div>

            <h1 id="login-title" tabindex="-1">Heureux de vous retrouver.</h1>
            <p class="intro">Connectez-vous à votre espace de travail.</p>

            @php
                // Initialisation sécurisée du sac d'erreurs
                $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
                $mainError = $errors->first('login') ?: $errors->first('email') ?: $errors->first('password');
                $isDeactivated = $mainError && (
                    str_contains(mb_strtolower($mainError), 'désactivé') ||
                    str_contains(mb_strtolower($mainError), 'desactive')
                );
                $isThrottled = $mainError && (
                    str_contains(mb_strtolower($mainError), 'tentative') ||
                    str_contains(mb_strtolower($mainError), 'secondes')
                );
                $isAuthFailed = $mainError && ! $isDeactivated && ! $isThrottled && (
                    $mainError === trans('auth.failed') ||
                    $mainError === trans('auth.password') ||
                    str_contains(mb_strtolower($mainError), 'identifiant') ||
                    str_contains(mb_strtolower($mainError), 'mot de passe') ||
                    str_contains(mb_strtolower($mainError), 'correspondent pas')
                );
                $showInlineEmailError = $errors->has('email')
                    && ! $isDeactivated
                    && ! $isAuthFailed
                    && ! $isThrottled
                    && (! $errors->has('login') || $errors->first('email') !== $errors->first('login'));
                $showInlinePasswordError = $errors->has('password')
                    && ! $isDeactivated
                    && ! $isAuthFailed
                    && ! $isThrottled
                    && (! $errors->has('login') || $errors->first('password') !== $errors->first('login'));
            @endphp

            {{-- Message d'état de session optionnel (hors expiration d'inactivité) --}}
            @if(session('status') && ! str_contains(mb_strtolower(session('status')), 'inactivit') && ! str_contains(mb_strtolower(session('status')), 'expir'))
                <div class="login-alert login-alert--info" role="status">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01"/>
                    </svg>
                    <div>
                        <p class="login-alert-text" style="margin-top: 0;">{{ session('status') }}</p>
                    </div>
                </div>
            @endif

            {{-- Bannière d'erreur contextuelle --}}
            @if($mainError)
                @if($isDeactivated)
                    <div class="login-alert login-alert--error" role="alert">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.93 4.93l14.14 14.14"/>
                        </svg>
                        <div>
                            <p class="login-alert-title">Compte désactivé</p>
                            <p class="login-alert-text">
                                Votre compte utilisateur a été désactivé par l'administration. Veuillez contacter votre responsable ou le support pour y accéder.
                            </p>
                        </div>
                    </div>
                @elseif($isThrottled)
                    <div class="login-alert login-alert--warning" role="alert">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 2"/>
                        </svg>
                        <div>
                            <p class="login-alert-title">Trop de tentatives</p>
                            <p class="login-alert-text">{{ $mainError }}</p>
                        </div>
                    </div>
                @elseif($isAuthFailed)
                    <div class="login-alert login-alert--error" role="alert">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                        </svg>
                        <div>
                            <p class="login-alert-title">Identifiants incorrects</p>
                            <p class="login-alert-text">
                                L'adresse email ou le mot de passe est incorrect. Veuillez vérifier vos informations de connexion.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="login-alert login-alert--error" role="alert">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                        </svg>
                        <div>
                            <p class="login-alert-title">Erreur de connexion</p>
                            <p class="login-alert-text">{{ $mainError }}</p>
                        </div>
                    </div>
                @endif
            @endif

            <form id="login-form" class="login-form" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="email">Adresse email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="prenom@exemple.test"
                        autocomplete="username"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                        enterkeyhint="next"
                        required
                        autofocus
                        @if($errors->has('email') || ($errors->has('login') && !old('email')) || $isAuthFailed) aria-invalid="true" @endif
                        @if($showInlineEmailError) aria-describedby="email-error" @endif
                    >
                    @if($showInlineEmailError)
                        <p id="email-error" class="login-field-error">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                            </svg>
                            <span>{{ $errors->first('email') }}</span>
                        </p>
                    @endif
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Votre mot de passe"
                            autocomplete="current-password"
                            enterkeyhint="done"
                            required
                            @if($errors->has('password') || ($errors->has('email') && $isAuthFailed)) aria-invalid="true" @endif
                            @if($showInlinePasswordError) aria-describedby="password-error" @endif
                        >
                        <button
                            type="button"
                            id="toggle-password"
                            class="password-toggle"
                            aria-label="Afficher le mot de passe"
                            aria-pressed="false"
                            aria-describedby="toggle-password-warning"
                        >
                            <svg id="eye-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="eye-icon-closed" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                                <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                                <path d="m2 2 20 20"/>
                            </svg>
                        </button>
                    </div>
                    <span id="toggle-password-warning" class="visually-hidden">Attention : le mot de passe sera affiché en clair à l’écran.</span>
                    @if($showInlinePasswordError)
                        <p id="password-error" class="login-field-error">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                            </svg>
                            <span>{{ $errors->first('password') }}</span>
                        </p>
                    @endif
                </div>

                <button type="submit" id="login-submit" class="login-submit">
                    <span id="login-submit-label">Se connecter</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M5 12h14"/>
                        <path d="m12 5 7 7-7 7"/>
                    </svg>
                </button>
            </form>

            <p class="login-support">
                Besoin d’un accès ?
                <button type="button" id="login-help-btn" command="show-modal" commandfor="login-help-dialog">Contacter l’administrateur</button>
            </p>

            <p class="login-foot">{{ $tenantName }} · Wetchah PMS</p>
        </div>
    </section>

</main>

{{-- Boîte de dialogue native pour l'aide à la connexion --}}
<dialog id="login-help-dialog" class="login-dialog" closedby="any" aria-labelledby="login-help-title">
    <header class="login-dialog-head">
        <h2 id="login-help-title">Contacter l’administrateur</h2>
        <button type="button" id="login-help-close-icon" class="dialog-close" command="close" commandfor="login-help-dialog" aria-label="Fermer la fenêtre">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M18 6 6 18"/>
                <path d="m6 6 12 12"/>
            </svg>
        </button>
    </header>
    <div class="login-dialog-body">
        <p>
            Les comptes d’accès à <strong>{{ $tenantName }}</strong> sont créés et administrés en interne par la direction de l’établissement.
        </p>
        <p>
            Si vous n’avez pas encore d’identifiants, si votre mot de passe provisoire a expiré ou si votre compte est verrouillé, veuillez solliciter votre responsable ou l’administrateur système.
        </p>
        @if($contactEmail || $contactPhone)
            <ul class="login-contact-list">
                @if($contactEmail)
                    <li><span>Email</span><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></li>
                @endif
                @if($contactPhone)
                    <li><span>Téléphone</span><a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}">{{ $contactPhone }}</a></li>
                @endif
            </ul>
        @endif
    </div>
    <footer class="login-dialog-foot">
        <form method="dialog">
            <button type="submit" class="dialog-btn-primary" autofocus>Fermer</button>
        </form>
    </footer>
</dialog>

<script>
(() => {
    'use strict';

    // Bascule afficher / masquer le mot de passe
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-password');
    const eyeOpen = document.getElementById('eye-icon-open');
    const eyeClosed = document.getElementById('eye-icon-closed');

    if (passwordInput && toggleBtn && eyeOpen && eyeClosed) {
        toggleBtn.addEventListener('click', () => {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            toggleBtn.setAttribute('aria-pressed', String(show));
            toggleBtn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            eyeOpen.hidden = show;
            eyeClosed.hidden = !show;
        });
    }

    // Prévenir les doubles soumissions tout en laissant la validation native agir
    const loginForm = document.getElementById('login-form');
    const submitBtn = document.getElementById('login-submit');
    const submitLabel = document.getElementById('login-submit-label');

    if (loginForm && submitBtn && submitLabel) {
        loginForm.addEventListener('submit', () => {
            if (!loginForm.checkValidity()) {
                return;
            }
            submitBtn.disabled = true;
            submitLabel.textContent = 'Connexion…';
        });

        window.addEventListener('pageshow', () => {
            submitBtn.disabled = false;
            submitLabel.textContent = 'Se connecter';
        });
    }

    // Dialogue « Contacter l'administrateur » : secours si Invoker Commands ou closedby="any" ne sont pas encore natifs
    const helpDialog = document.getElementById('login-help-dialog');
    const helpBtn = document.getElementById('login-help-btn');
    const helpCloseIcon = document.getElementById('login-help-close-icon');

    if (helpDialog) {
        if (!('commandForElement' in HTMLButtonElement.prototype)) {
            helpBtn?.addEventListener('click', () => {
                if (!helpDialog.open) helpDialog.showModal();
            });
            helpCloseIcon?.addEventListener('click', () => {
                if (helpDialog.open) helpDialog.close();
            });
        }

        if (!('closedBy' in HTMLDialogElement.prototype)) {
            const isOutsideDialog = (event) => {
                const r = helpDialog.getBoundingClientRect();
                return (
                    event.clientX < r.left ||
                    event.clientX > r.right ||
                    event.clientY < r.top ||
                    event.clientY > r.bottom
                );
            };
            let pressedOutside = false;
            helpDialog.addEventListener('pointerdown', (event) => {
                pressedOutside = isOutsideDialog(event);
            });
            helpDialog.addEventListener('pointerup', (event) => {
                if (pressedOutside && isOutsideDialog(event)) {
                    helpDialog.close();
                }
                pressedOutside = false;
            });
        }
    }
})();
</script>

@include('partials.pwa-install')

</body>
</html>
