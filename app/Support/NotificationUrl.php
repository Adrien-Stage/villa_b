<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalise les URL des notifications (cloche in-app, redirection /read, Web Push).
 *
 * Les notifications peuvent contenir un hôte local ou éphémère (127.0.0.1,
 * localhost, nom de conteneur Docker, IP d'une interface précédente) hérité
 * d'APP_URL ou de la session de l'émetteur. Sur un réseau d'entreprise où
 * l'utilisateur accède au serveur via son IP LAN ou un domaine dédié, suivre
 * une telle URL absolue le ferait sortir de l'application active.
 *
 * Convertir les URL internes en chemins relatifs (`/chemin?query#fragment`)
 * garantit une navigation toujours fidèle à l'origine courante du navigateur.
 */
final class NotificationUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const LOOPBACK_HOSTS = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    private const INTERNAL_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.lan',
        '.home.arpa',
    ];

    public static function toAppPath(?string $rawUrl, ?Request $request = null): ?string
    {
        if ($rawUrl === null) {
            return null;
        }

        $trimmed = trim($rawUrl);
        if ($trimmed === '') {
            return null;
        }

        // Chemin déjà relatif à la racine applicative (hors //host protocol-relative).
        if (str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        // URL protocol-relative (//host/chemin) : normalisée uniquement si hôte interne.
        if (str_starts_with($trimmed, '//')) {
            $parts = parse_url('http:' . $trimmed);
            if ($parts === false) {
                return null;
            }

            $host = self::normalizeHost((string) ($parts['host'] ?? ''));

            return self::isInternalOrAppHost($host, $request)
                ? self::buildRelativePath($parts)
                : null;
        }

        // Chemin relatif nu sans slash initial ni schéma (ex: "housekeeping").
        if (! str_contains($trimmed, '://') && ! preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $trimmed)) {
            return '/' . ltrim($trimmed, '/');
        }

        $parts = parse_url($trimmed);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme === '' || ! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            // Rejette javascript:, data:, vbscript:, file:, etc.
            return null;
        }

        $host = self::normalizeHost((string) ($parts['host'] ?? ''));

        if (self::isInternalOrAppHost($host, $request)) {
            return self::buildRelativePath($parts);
        }

        // URL externe publique légitime : conservée intacte.
        return $trimmed;
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host, " \t\n\r\0\x0B[]"), '.'));
    }

    private static function isInternalOrAppHost(string $host, ?Request $request): bool
    {
        if ($host === '' || in_array($host, self::LOOPBACK_HOSTS, true)) {
            return true;
        }

        // Adresses IP privées, loopback, link-local ou réservées (RFC1918, 127/8, ::1, fe80::…).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $isPublicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

            if (! $isPublicIp) {
                return true;
            }
        }

        // Hôte sans point (nom de service Docker/conteneur ou machine locale, ex. meka-erp-app).
        if (! str_contains($host, '.')) {
            return true;
        }

        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $appHost = self::normalizeHost((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? ''));
        if ($appHost !== '' && $host === $appHost) {
            return true;
        }

        if ($request !== null) {
            $requestHost = self::normalizeHost((string) $request->getHost());
            if ($requestHost !== '' && $host === $requestHost) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private static function buildRelativePath(array $parts): string
    {
        $path = (string) ($parts['path'] ?? '');
        $normalized = $path === '' ? '/' : (str_starts_with($path, '/') ? $path : '/' . $path);

        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }
}
