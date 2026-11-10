@php
    $tenant = \App\Models\Tenant::current();
    $tenantName = trim((string) ($tenant?->name ?? '')) ?: 'Établissement';
    // Logo importé par le manager depuis les paramètres de l'application
    // (stocké localement dans le storage de ce tenant).
    $tenantLogo = ($tenant && !empty($tenant->settings['logo'])) ? asset('storage/' . $tenant->settings['logo']) : null;
    $contactEmail = $tenant?->email;
    $contactPhone = $tenant?->phone;

    $rawTheme = is_array($tenant?->settings['theme'] ?? null) ? $tenant->settings['theme'] : [];
    $sanitizeHex = static function (?string $value, string $fallback): string {
        $hex = strtoupper(ltrim(trim((string) $value), '#'));
        if (strlen($hex) === 3 && ctype_xdigit($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return preg_match('/^[0-9A-F]{6}$/', $hex) ? '#' . $hex : $fallback;
    };

    $theme = [
        'primary'       => $sanitizeHex($rawTheme['primary'] ?? null, '#391F0E'),
        'secondary'     => $sanitizeHex($rawTheme['secondary'] ?? null, '#CCAB87'),
        'accent'        => $sanitizeHex($rawTheme['accent'] ?? null, '#EED4A3'),
        'dark'          => $sanitizeHex($rawTheme['dark'] ?? null, '#0F0201'),
        'surface_dark'  => $sanitizeHex($rawTheme['surface_dark'] ?? null, '#2C1810'),
        'text_on_light' => $sanitizeHex($rawTheme['text_on_light'] ?? null, '#391F0E'),
        'text_on_dark'  => $sanitizeHex($rawTheme['text_on_dark'] ?? null, '#CCAB87'),
    ];

    $relativeLuminance = static function (string $hex): float {
        $channels = array_map(static function (int $c): float {
            $srgb = $c / 255;

            return $srgb <= 0.03928 ? $srgb / 12.92 : (($srgb + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $primaryLum = $relativeLuminance($theme['primary']);
    $brandSurfaceLum = ($primaryLum + $relativeLuminance($theme['surface_dark'])) / 2;
    $brandIsLight = $brandSurfaceLum > 0.32;
    $ctaText = $primaryLum > 0.17913 ? '#181410' : '#FFFFFF';
    $primaryReadableOnLight = $primaryLum <= 0.22;

    // Initialisation sécurisée du sac d'erreurs et détermination de l'état de veille
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

    $hasImmediateFormFeedback = old('email') !== null
        || $isDeactivated
        || $isThrottled
        || $isAuthFailed
        || $showInlineEmailError
        || $showInlinePasswordError;

    $isStandby = (bool) ($forceStandby
        ?? (request()->query('expired') === 'inactivity'
            || request()->boolean('veille')
            || session('veille')))
        && ! $hasImmediateFormFeedback;
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
            --color-primary: {{ $theme['primary'] }};
            --color-secondary: {{ $theme['secondary'] }};
            --color-accent: {{ $theme['accent'] }};
            --color-dark: {{ $theme['dark'] }};
            --color-surface-dark: {{ $theme['surface_dark'] }};
            --color-text-on-light: {{ $theme['text_on_light'] }};
            --color-text-on-dark: {{ $theme['text_on_dark'] }};

            --login-ink: color-mix(in oklab, var(--color-dark) 88%, #1e293b);
            --login-primary: var(--color-primary);
            --login-primary-hover: color-mix(in oklab, var(--color-primary) 76%, var(--color-dark));
            --login-link: {{ $primaryReadableOnLight ? 'var(--color-primary)' : 'color-mix(in oklab, var(--color-primary) 45%, var(--color-dark))' }};
            --login-muted: color-mix(in oklab, var(--color-dark) 54%, #6b7280);
            --login-quiet: color-mix(in oklab, var(--color-dark) 44%, #6b7280);
            --login-bg: color-mix(in oklab, var(--color-accent) 7%, #ffffff);
            --login-surface-subtle: color-mix(in oklab, var(--color-accent) 12%, #ffffff);
            --login-line: color-mix(in oklab, var(--color-secondary) 24%, #e5e7eb);
            --login-input-border: color-mix(in oklab, var(--color-dark) 30%, #9ca3af);
            --login-focus: var(--login-link);
            --login-red: #a03735;

            --login-brand-fg: {{ $brandIsLight ? '#181410' : 'color-mix(in oklab, var(--color-accent) 28%, #ffffff)' }};
            --login-brand-sub-fg: {{ $brandIsLight ? '#3f3730' : 'color-mix(in oklab, var(--color-text-on-dark) 75%, #ffffff)' }};
            --login-brand-period-fg: {{ $brandIsLight ? 'var(--color-dark)' : 'var(--color-secondary)' }};
            --login-brand-arch-fg: {{ $brandIsLight ? '#3f3730' : 'var(--color-secondary)' }};
            --login-cta-bg: var(--color-primary);
            --login-cta-fg: {{ $ctaText }};

            --veille-bubble-bg: {{ $brandIsLight ? 'var(--color-dark)' : 'color-mix(in oklab, var(--color-accent) 22%, #ffffff)' }};
            --veille-bubble-fg: {{ $brandIsLight ? '#FFFFFF' : 'color-mix(in oklab, var(--color-dark) 92%, #181410)' }};
            --veille-bubble-muted: {{ $brandIsLight ? 'color-mix(in oklab, var(--color-accent) 75%, #e5e7eb)' : 'color-mix(in oklab, var(--color-dark) 62%, #4b5563)' }};
            --veille-bubble-border: {{ $brandIsLight ? 'color-mix(in oklab, var(--color-accent) 45%, #374151)' : 'color-mix(in oklab, var(--color-secondary) 55%, #ffffff)' }};
            --veille-halo: color-mix(in oklab, var(--color-accent) 32%, transparent);
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
            grid-template-rows: 1fr;
            background: var(--login-bg);
            position: relative;
            overflow-x: clip;
        }

        /* ── Panneau gauche : identité de l'établissement & thème ERP ───────── */
        .login-brand {
            grid-column: 1;
            grid-row: 1;
            z-index: 1;
            background-color: var(--color-surface-dark);
            background: radial-gradient(
                ellipse at 25% 18%,
                color-mix(in oklab, var(--color-primary) 88%, var(--color-surface-dark)) 0%,
                var(--color-surface-dark) 58%,
                var(--color-dark) 100%
            );
            color: var(--login-brand-fg);
            padding: 42px 48px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .login-brand-wordmark,
        .veille-wordmark {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 600;
            font-size: clamp(24px, 2.4vw, 31px);
            line-height: 1.15;
            letter-spacing: -0.9px;
            color: var(--login-brand-fg);
            margin: 0;
            text-wrap: balance;
            word-break: break-word;
        }

        .login-brand-period {
            color: var(--login-brand-period-fg);
        }

        .login-brand-sub {
            margin: 7px 0 0;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-weight: 600;
            color: var(--login-brand-sub-fg);
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
            color: var(--login-brand-fg);
        }

        .login-brand-copy p {
            margin: 16px 0 0;
            color: var(--login-brand-sub-fg);
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
            color: var(--login-brand-arch-fg);
            opacity: .76;
        }

        .login-architecture-door {
            fill: var(--color-accent);
            fill-opacity: .14;
        }

        .login-brand-foot {
            border-top: 1px solid color-mix(in oklab, var(--login-brand-sub-fg) 26%, transparent);
            padding-top: 18px;
            margin: auto 0 0;
            font-size: 9px;
            color: var(--login-brand-sub-fg);
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        /* ── Panneau droit : formulaire de connexion ─────────────────────────── */
        .login-content {
            grid-column: 2;
            grid-row: 1;
            z-index: 2;
            padding: 55px 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--login-bg);
        }

        /* ── Page de veille (verrouillage après 20 min d'inactivité) ──────────── */
        .login-standby {
            grid-column: 1 / -1;
            grid-row: 1;
            z-index: 15;
            min-height: 100dvh;
            padding: 42px 48px;
            display: flex;
            flex-direction: column;
            background-color: var(--color-surface-dark);
            background: radial-gradient(
                ellipse at 50% 42%,
                color-mix(in oklab, var(--color-primary) 90%, var(--color-surface-dark)) 0%,
                var(--color-surface-dark) 56%,
                var(--color-dark) 100%
            );
            color: var(--login-brand-fg);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .veille-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .veille-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklab, var(--login-brand-sub-fg) 30%, transparent);
            background: color-mix(in oklab, var(--color-dark) 26%, transparent);
            color: var(--login-brand-fg);
            font-size: 11px;
            font-weight: 550;
            letter-spacing: 0.04em;
        }

        .veille-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--color-accent);
            flex-shrink: 0;
        }

        .veille-stage {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: clamp(14px, 2.4dvh, 24px);
            padding: clamp(18px, 3dvh, 32px) 0;
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .veille-bubble {
            position: relative;
            max-width: min(370px, 88vw);
            padding: 13px 20px;
            border-radius: 16px;
            background: var(--veille-bubble-bg);
            color: var(--veille-bubble-fg);
            border: 1px solid var(--veille-bubble-border);
            box-shadow: 0 20px 44px -14px rgba(0, 0, 0, 0.45);
            cursor: pointer;
        }

        @media (prefers-reduced-motion: no-preference) {
            .veille-bubble {
                animation: veilleBubbleFloat 3.6s ease-in-out infinite;
            }
        }

        .veille-bubble::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -8px;
            width: 14px;
            height: 14px;
            background: var(--veille-bubble-bg);
            border-right: 1px solid var(--veille-bubble-border);
            border-bottom: 1px solid var(--veille-bubble-border);
            transform: translateX(-50%) rotate(45deg);
        }

        .veille-bubble-kicker {
            margin: 0 0 3px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--veille-bubble-muted);
        }

        .veille-bubble-text {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            line-height: 1.4;
            color: var(--veille-bubble-fg);
        }

        .veille-door-btn {
            position: relative;
            border: 0;
            padding: 16px 24px 10px;
            background: transparent;
            color: var(--login-brand-arch-fg);
            cursor: pointer;
            border-radius: 220px 220px 22px 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            min-height: 180px;
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), color 0.2s ease;
        }

        .veille-door-btn::before {
            content: '';
            position: absolute;
            inset: 6% 12% 4%;
            border-radius: 50% 50% 12% 12%;
            background: radial-gradient(circle at 50% 58%, var(--veille-halo) 0%, transparent 72%);
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .veille-door-btn:hover::before,
        .veille-door-btn:focus-visible::before {
            opacity: 1;
            transform: scale(1.06);
        }

        .veille-door-btn:hover {
            transform: translateY(-3px) scale(1.02);
            color: var(--login-brand-fg);
        }

        .veille-door-btn:active {
            transform: translateY(0) scale(0.99);
        }

        .veille-door-btn:focus-visible {
            outline: 3px solid var(--login-brand-fg);
            outline-offset: 6px;
        }

        .veille-architecture {
            position: relative;
            z-index: 1;
            width: clamp(260px, 44vmin, 430px);
            height: auto;
            max-height: 44dvh;
            opacity: 0.94;
            overflow: visible;
        }

        .veille-door-leaf {
            transform-box: fill-box;
            transform-origin: 0% 50%;
            transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .veille-door-panel {
            fill: var(--color-accent);
            fill-opacity: 0.22;
            transition: fill-opacity 0.25s ease;
        }

        .veille-door-btn:hover .veille-door-panel,
        .veille-door-btn:focus-visible .veille-door-panel {
            fill-opacity: 0.36;
        }

        .veille-door-btn:hover .veille-door-leaf,
        .veille-door-btn:focus-visible .veille-door-leaf {
            transform: scaleX(0.9);
        }

        .veille-tagline {
            margin: 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(16px, 2vw, 21px);
            font-weight: 500;
            color: var(--login-brand-fg);
            opacity: 0.88;
        }

        /* États de veille et animation slide de la droite vers la gauche */
        .login-page.is-standby .login-content {
            transform: translate3d(100%, 0, 0);
            opacity: 0;
            pointer-events: none;
            z-index: 25;
        }

        .login-page.is-revealing .login-content {
            z-index: 25;
            animation: loginPanelSlideIn 0.72s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .login-page.is-revealing .login-form-wrap {
            animation: loginFormSettle 0.54s cubic-bezier(0.22, 1, 0.36, 1) 0.14s both;
        }

        .login-page.is-revealing .login-standby {
            pointer-events: none;
            animation: veillePanelExit 0.72s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .login-page.is-revealing .veille-bubble,
        .login-page.is-revealing .veille-tagline,
        .login-page.is-revealing .veille-status-pill {
            animation: veilleCueExit 0.32s cubic-bezier(0.4, 0, 1, 1) forwards;
        }

        .login-page.is-revealing .veille-architecture {
            animation: veilleDoorSlideLeft 0.68s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .login-page.is-revealing .veille-door-leaf {
            transform: scaleX(0.68);
        }

        .login-page.is-revealing .login-brand-copy {
            animation: brandCopyReveal 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.16s both;
        }

        @keyframes veilleBubbleFloat {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -5px, 0); }
        }

        @keyframes loginPanelSlideIn {
            from {
                transform: translate3d(100%, 0, 0);
                opacity: 1;
                box-shadow: -32px 0 80px color-mix(in oklab, var(--color-dark) 42%, transparent);
            }
            75% {
                box-shadow: -16px 0 40px color-mix(in oklab, var(--color-dark) 22%, transparent);
            }
            to {
                transform: translate3d(0, 0, 0);
                opacity: 1;
                box-shadow: 0 0 0 transparent;
            }
        }

        @keyframes loginFormSettle {
            from {
                opacity: 0;
                transform: translate3d(28px, 0, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes veillePanelExit {
            0%, 48% {
                opacity: 1;
            }
            100% {
                opacity: 0;
            }
        }

        @keyframes veilleCueExit {
            from {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
            to {
                opacity: 0;
                transform: translate3d(-32px, 0, 0);
            }
        }

        @keyframes veilleDoorSlideLeft {
            from {
                opacity: 0.94;
                transform: translate3d(0, 0, 0) scale(1);
            }
            to {
                opacity: 0.12;
                transform: translate3d(-24vw, 0, 0) scale(0.76);
            }
        }

        @keyframes brandCopyReveal {
            from {
                opacity: 0;
                transform: translate3d(24px, 0, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
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
            line-height: 1.1;
            letter-spacing: -0.9px;
            color: var(--login-link);
            margin: 0 0 28px;
        }

        .login-place {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 29px;
            font-size: 14px;
            font-weight: 600;
            color: var(--login-ink);
        }

        .place-seal {
            border: 1px solid color-mix(in oklab, var(--color-secondary) 45%, #d6d3d1);
            background: color-mix(in oklab, var(--color-accent) 28%, #ffffff);
            width: 39px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px 18px 4px 4px;
            color: var(--login-link);
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
            color: color-mix(in oklab, var(--login-ink) 84%, #ffffff);
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
            border-color: color-mix(in oklab, var(--login-ink) 55%, var(--login-input-border));
        }

        .login-form .field input:focus-visible {
            border-color: var(--login-link);
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
            background: color-mix(in oklab, var(--color-secondary) 16%, #f5f5f4);
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
            border: 1px solid var(--login-cta-bg);
            border-radius: 7px;
            background: var(--login-cta-bg);
            color: var(--login-cta-fg);
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
            --login-cta-bg: var(--login-primary-hover);
            background: var(--login-primary-hover);
            border-color: var(--login-primary-hover);
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
            color: var(--login-link);
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
            color: var(--login-primary-hover);
        }

        .login-foot {
            border-top: 1px solid var(--login-line);
            padding-top: 18px;
            margin: 0;
            text-align: center;
            font-size: 11px;
            color: var(--login-quiet);
        }

        .mobile-login-brand .login-brand-period {
            color: color-mix(in oklab, var(--color-secondary) 82%, var(--login-link));
        }

        /* Focus clavier haute visibilité sur le thème clair */
        .login-page :where(button, input, a):focus-visible,
        dialog.login-dialog :where(button, a):focus-visible {
            outline: 2px solid var(--login-focus);
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
            border: 1px solid var(--login-line);
            border-radius: 14px;
            padding: 0;
            background: #ffffff;
            color: var(--login-ink);
            box-shadow: 0 24px 90px color-mix(in oklab, var(--color-dark) 26%, transparent);
            overflow: auto;
        }

        dialog.login-dialog:modal {
            overscroll-behavior: contain;
        }

        dialog.login-dialog::backdrop {
            background: color-mix(in oklab, var(--color-dark) 48%, transparent);
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
            background: color-mix(in oklab, var(--color-secondary) 16%, #f5f5f4);
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
            background: var(--login-surface-subtle);
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
            color: var(--login-link);
            font-weight: 550;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .login-dialog-foot {
            padding: 14px 24px;
            border-top: 1px solid var(--login-line);
            display: flex;
            justify-content: flex-end;
            background: var(--login-surface-subtle);
        }

        .dialog-btn-primary {
            border: 1px solid var(--login-cta-bg);
            background: var(--login-cta-bg);
            color: var(--login-cta-fg);
            border-radius: 7px;
            min-height: 39px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 550;
            cursor: pointer;
        }

        .dialog-btn-primary:hover {
            --login-cta-bg: var(--login-primary-hover);
            background: var(--login-primary-hover);
            border-color: var(--login-primary-hover);
        }

        @supports (color: color(from red xyz-d65 r g b)) {
            .login-submit,
            .dialog-btn-primary {
                color: color(
                    from var(--login-cta-bg) xyz-d65
                    calc(max(0, sign(0.17913 - y)) * 0.9505)
                    max(0, sign(0.17913 - y))
                    calc(max(0, sign(0.17913 - y)) * 1.089)
                );
            }
        }

        @supports (color: contrast-color(red)) {
            .login-submit,
            .dialog-btn-primary {
                color: contrast-color(var(--login-cta-bg));
            }
        }

        /* ── Responsive ──────────────────────────────────────────────────────── */
        @media (max-width: 1150px) {
            .login-brand,
            .login-standby {
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
            .login-page.is-standby,
            .login-page.is-revealing {
                display: grid;
                grid-template-columns: 1fr;
                grid-template-rows: 1fr;
            }
            .login-page.is-standby .login-content,
            .login-page.is-revealing .login-content {
                grid-column: 1;
                grid-row: 1;
            }
            .login-brand {
                display: none;
            }
            .login-standby {
                padding: 28px 22px;
            }
            .veille-wordmark {
                font-size: 24px;
            }
            .veille-architecture {
                width: min(300px, 78vw);
                max-height: 36dvh;
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
                font-size: 13px;
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
                --login-muted: color-mix(in oklab, var(--color-dark) 78%, #111827);
                --login-quiet: color-mix(in oklab, var(--color-dark) 78%, #111827);
                --login-line: color-mix(in oklab, var(--color-dark) 42%, #9ca3af);
                --login-input-border: color-mix(in oklab, var(--color-dark) 62%, #4b5563);
            }
        }

        @media (forced-colors: active) {
            .login-brand,
            .login-standby {
                border-right: 1px solid CanvasText;
            }
            .veille-bubble,
            .veille-status-pill {
                border-color: CanvasText;
            }
            .place-seal,
            .login-submit,
            .dialog-btn-primary,
            dialog.login-dialog {
                border-color: ButtonText;
            }
        }
    </style>
</head>
<body class="login-body">

<main id="main" class="login-page {{ $isStandby ? 'is-standby' : '' }}" data-standby="{{ $isStandby ? 'true' : 'false' }}">

    {{-- ===== PAGE DE VEILLE — Design plein écran avec porte interactive ===== --}}
    <section
        id="veille-panel"
        class="login-standby"
        aria-label="{{ $tenantName }} — Session en veille"
        {!! $isStandby ? '' : 'hidden inert' !!}
    >
        <div class="veille-head">
            <div>
                <p class="veille-wordmark">{{ $tenantName }}<span class="login-brand-period">.</span></p>
                <p class="login-brand-sub">Wetchah · Gestion hôtelière</p>
            </div>
            <span class="veille-status-pill">
                <span class="veille-status-dot" aria-hidden="true"></span>
                <span>Veille · 20 min d’inactivité</span>
            </span>
        </div>

        <div class="veille-stage">
            <h2 class="visually-hidden">Session mise en veille</h2>

            <div id="veille-bubble" class="veille-bubble">
                <p class="veille-bubble-kicker">Travail en pause</p>
                <p id="veille-bubble-cta" class="veille-bubble-text">Cliquez sur la porte pour reprendre le travail</p>
            </div>

            <button
                type="button"
                id="veille-resume-btn"
                class="veille-door-btn"
                aria-labelledby="veille-bubble-cta"
            >
                <svg class="veille-architecture" viewBox="0 0 360 310" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
                    <path d="M42 288V130a138 138 0 0 1 276 0v158M69 288V132a111 111 0 0 1 222 0v156M97 288V135a83 83 0 0 1 166 0v153M126 288V137a54 54 0 0 1 108 0v151" stroke-width="1"/>
                    <path d="M15 289h330M15 300h330M180 84v205M126 184h108M97 154h29m108 0h29M69 123h29m164 0h29" stroke-width=".9"/>
                    <g class="veille-door-leaf">
                        <path class="veille-door-panel" d="M143 289V152a37 37 0 0 1 74 0v137" stroke-width="1.1"/>
                        <path d="M153 284V156a27 27 0 0 1 54 0v128" stroke-width=".6" stroke-opacity=".55"/>
                        <circle cx="196" cy="222" r="3.6" fill="currentColor"/>
                    </g>
                </svg>
            </button>

            <p class="veille-tagline">Une journée d’hôtel, bien organisée · {{ $tenantName }}</p>
        </div>

        <p class="login-brand-foot">RÉCEPTION · RESTAURATION · ÉCONOMAT · FINANCE</p>
    </section>

    {{-- ===== PANNEAU GAUCHE — Identité de l'établissement & Wetchah ===== --}}
    <section
        id="login-brand-panel"
        class="login-brand"
        aria-label="{{ $tenantName }} — Wetchah, gestion hôtelière"{{ $isStandby ? ' aria-hidden="true"' : '' }}
    >
        <div>
            <p class="login-brand-wordmark">{{ $tenantName }}<span class="login-brand-period">.</span></p>
            <p class="login-brand-sub">Wetchah · Gestion hôtelière</p>
        </div>

        <div class="login-brand-copy">
            <h2>Une journée d’hôtel,<br>bien organisée.</h2>
            <p>Les services de {{ $tenantName }}.<br>Un espace pour travailler ensemble.</p>
        </div>

        <svg class="login-architecture" viewBox="0 0 360 310" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
            <path d="M42 288V130a138 138 0 0 1 276 0v158M69 288V132a111 111 0 0 1 222 0v156M97 288V135a83 83 0 0 1 166 0v153M126 288V137a54 54 0 0 1 108 0v151" stroke-width=".8"/>
            <path d="M15 289h330M15 300h330M180 84v205M126 184h108M97 154h29m108 0h29M69 123h29m164 0h29" stroke-width=".8"/>
            <path class="login-architecture-door" d="M143 289V152a37 37 0 0 1 74 0v137" stroke-width=".7"/>
            <circle cx="196" cy="222" r="3"/>
        </svg>

        <p class="login-brand-foot">RÉCEPTION · RESTAURATION · ÉCONOMAT · FINANCE</p>
    </section>

    {{-- ===== PANNEAU DROIT — Connexion à l'établissement ===== --}}
    <section id="login-content-panel" class="login-content" aria-labelledby="login-title"{{ $isStandby ? ' inert' : '' }}>
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
                        @unless($isStandby) autofocus @endunless
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

    // Page de veille : transition slide de la droite vers la gauche au clic sur la porte
    const loginPage = document.getElementById('main');
    const brandPanel = document.getElementById('login-brand-panel');
    const veillePanel = document.getElementById('veille-panel');
    const contentPanel = document.getElementById('login-content-panel');
    const resumeBtn = document.getElementById('veille-resume-btn');
    const veilleBubble = document.getElementById('veille-bubble');
    const emailInput = document.getElementById('email');

    if (loginPage && veillePanel && contentPanel && resumeBtn) {
        let unlocking = false;

        const finishUnlock = () => {
            veillePanel.hidden = true;
            veillePanel.setAttribute('inert', '');
            brandPanel?.removeAttribute('aria-hidden');
            loginPage.classList.remove('is-revealing', 'is-standby');
            loginPage.dataset.standby = 'false';

            if (
                window.location.pathname === '/veille' ||
                window.location.search.includes('expired=inactivity') ||
                window.location.search.includes('veille=')
            ) {
                window.history.replaceState(null, '', '{{ route('login', [], false) }}');
            }

            if (emailInput && !document.activeElement?.closest('#login-form')) {
                emailInput.focus();
            }
        };

        const unlockStandby = () => {
            if (unlocking || loginPage.dataset.standby !== 'true') {
                return;
            }
            unlocking = true;

            contentPanel.removeAttribute('inert');

            const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
            if (prefersReducedMotion) {
                finishUnlock();
                return;
            }

            loginPage.classList.remove('is-standby');
            loginPage.classList.add('is-revealing');

            window.requestAnimationFrame(() => {
                const activeAnimations = [
                    ...(contentPanel.getAnimations ? contentPanel.getAnimations({ subtree: false }) : []),
                    ...(veillePanel.getAnimations ? veillePanel.getAnimations({ subtree: false }) : []),
                ];

                if (activeAnimations.length === 0) {
                    finishUnlock();
                    return;
                }

                Promise.race([
                    Promise.allSettled(activeAnimations.map((animation) => animation.finished)),
                    new Promise((resolve) => window.setTimeout(resolve, 900)),
                ]).then(finishUnlock);
            });
        };

        resumeBtn.addEventListener('click', unlockStandby);
        veilleBubble?.addEventListener('click', unlockStandby);
    }

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
