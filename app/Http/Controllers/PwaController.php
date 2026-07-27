<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantModules;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Application installable (PWA).
 *
 * Le manifeste et les icônes sont servis dynamiquement : chaque établissement
 * a son nom et son logo, donc l'application installée doit porter SON identité
 * — un manifeste statique afficherait le même nom chez tous les clients.
 */
class PwaController extends Controller
{
    /** Tailles d'icônes générées (px). 192 et 512 sont exigées par Android. */
    private const ICON_SIZES = [192, 512];

    private const BRAND_BG = [0x39, 0x1F, 0x0E];   // --color-primary
    private const BRAND_FG = [0xEE, 0xD4, 0xA3];   // --color-accent

    /** Manifeste de l'application, construit à partir de l'établissement. */
    public function manifest()
    {
        $tenant = Tenant::first();
        $name   = $tenant?->name ?: 'WeTchah';

        $manifest = [
            'name'             => $name,
            'short_name'       => \Illuminate\Support\Str::limit($name, 12, ''),
            'description'      => "Gestion hôtelière {$name}",
            'lang'             => 'fr',
            'dir'              => 'ltr',
            'start_url'        => '/',
            'scope'            => '/',
            // 'standalone' : l'app s'ouvre sans barre d'adresse, comme une app native.
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#FFFFFF',
            'theme_color'      => '#391F0E',
            'icons'            => $this->iconEntries(),
            'shortcuts'        => $this->shortcuts(),
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** Icône PNG carrée générée depuis le logo de l'établissement. */
    public function icon(int $size)
    {
        // Taille bornée : évite qu'une URL forgée fasse générer une image géante.
        if (!in_array($size, self::ICON_SIZES, true)) {
            abort(404);
        }

        $tenant   = Tenant::first();
        $logoPath = $tenant?->settings['logo'] ?? null;
        // La clé de cache inclut le logo : changer de logo régénère l'icône.
        $cacheKey = 'pwa-icon-' . $size . '-' . md5((string) $logoPath . ($tenant?->name ?? ''));

        $png = Cache::remember($cacheKey, now()->addDay(), function () use ($size, $logoPath, $tenant) {
            return $this->buildIcon($size, $logoPath, $tenant?->name ?? 'WT');
        });

        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Page affichée quand la navigation échoue faute de réseau. */
    public function offline()
    {
        return response()->view('pwa.offline', [], 200)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    // ── Construction ────────────────────────────────────────────────────────

    private function iconEntries(): array
    {
        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $icons[] = [
                'src'     => url("/pwa/icon-{$size}.png"),
                'sizes'   => "{$size}x{$size}",
                'type'    => 'image/png',
                // 'any maskable' : l'icône s'adapte aux masques Android sans être rognée.
                'purpose' => 'any maskable',
            ];
        }

        return $icons;
    }

    /** Raccourcis d'application (appui long sur l'icône), selon les modules actifs. */
    private function shortcuts(): array
    {
        $shortcuts = [[
            'name'  => 'Réservations',
            'url'   => '/bookings',
            'icons' => [['src' => url('/pwa/icon-192.png'), 'sizes' => '192x192']],
        ]];

        if (TenantModules::has('housekeeping')) {
            $shortcuts[] = [
                'name'  => 'Housekeeping',
                'url'   => '/housekeeping',
                'icons' => [['src' => url('/pwa/icon-192.png'), 'sizes' => '192x192']],
            ];
        }
        if (TenantModules::has('restaurant')) {
            $shortcuts[] = [
                'name'  => 'Restaurant',
                'url'   => '/restaurant/orders',
                'icons' => [['src' => url('/pwa/icon-192.png'), 'sizes' => '192x192']],
            ];
        }

        return $shortcuts;
    }

    /**
     * Compose l'icône : le logo de l'établissement centré sur le fond de marque,
     * ou ses initiales si aucun logo n'est configuré.
     */
    private function buildIcon(int $size, ?string $logoPath, string $name): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $bg = imagecolorallocate($canvas, ...self::BRAND_BG);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $bg);

        $logo = $this->loadLogo($logoPath);

        if ($logo) {
            // Marge de 18 % : la zone sûre d'une icône masquable Android.
            $inner = (int) round($size * 0.64);
            $offset = (int) round(($size - $inner) / 2);

            imagealphablending($canvas, true);
            imagecopyresampled(
                $canvas, $logo,
                $offset, $offset, 0, 0,
                $inner, $inner,
                imagesx($logo), imagesy($logo)
            );
            imagedestroy($logo);
        } else {
            $this->drawInitials($canvas, $size, $name);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return $png;
    }

    /** Charge le logo du tenant depuis le storage public, si lisible. */
    private function loadLogo(?string $logoPath): ?\GdImage
    {
        if (!$logoPath || !Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        try {
            $image = @imagecreatefromstring(Storage::disk('public')->get($logoPath));
        } catch (\Throwable) {
            return null;
        }

        return $image ?: null;
    }

    /** Repli sans logo : les initiales de l'établissement, centrées. */
    private function drawInitials(\GdImage $canvas, int $size, string $name): void
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = 'WT';
        }

        $fg = imagecolorallocate($canvas, ...self::BRAND_FG);
        // Police bitmap intégrée à GD : pas de dépendance à un fichier TTF.
        $font = 5;
        $scale = max(1, (int) round($size / 40));
        $textW = imagefontwidth($font) * strlen($initials) * $scale;
        $textH = imagefontheight($font) * $scale;

        // On dessine grand puis on rétrécit : la police bitmap est trop petite.
        $tmp = imagecreatetruecolor($textW, $textH);
        $tmpBg = imagecolorallocate($tmp, ...self::BRAND_BG);
        imagefilledrectangle($tmp, 0, 0, $textW, $textH, $tmpBg);
        $tmpFg = imagecolorallocate($tmp, ...self::BRAND_FG);
        imagestring($tmp, $font, 0, 0, $initials, $tmpFg);

        $targetW = (int) round($size * 0.5);
        $targetH = (int) round($targetW * $textH / max(1, $textW));
        imagecopyresampled(
            $canvas, $tmp,
            (int) round(($size - $targetW) / 2), (int) round(($size - $targetH) / 2),
            0, 0, $targetW, $targetH, $textW, $textH
        );
        imagedestroy($tmp);
    }
}
