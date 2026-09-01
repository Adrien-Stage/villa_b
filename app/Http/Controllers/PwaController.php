<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantModules;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
    /** Tailles du manifeste (px). 192 et 512 sont exigées par Android. */
    private const MANIFEST_SIZES = [192, 512];

    /** Tailles pour l'onglet du navigateur (32) et l'écran d'accueil iOS (180). */
    private const FAVICON_SIZES = [32, 180];

    /** Tailles que la route accepte de générer. */
    private const ICON_SIZES = [32, 180, 192, 512];

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
        $cacheKey = 'pwa-icon-' . $size . '-' . self::brandVersion();

        $png = Cache::remember($cacheKey, now()->addDay(), function () use ($size, $logoPath, $tenant) {
            return $this->buildIcon($size, $logoPath, $tenant?->name ?? 'WT');
        });

        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Icône du chemin historique /favicon.ico.
     *
     * Les navigateurs le réclament d'office, et les notifications push le
     * désignent comme icône. On y sert le PNG 32 px : tous les navigateurs
     * actuels acceptent un PNG sous ce nom, et l'ICO n'apporterait rien.
     */
    public function favicon()
    {
        return $this->icon(32);
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
        foreach (self::MANIFEST_SIZES as $size) {
            $icons[] = [
                // Route nommée : l'URL du manifeste suit toute évolution du
                // routage, au lieu de diverger silencieusement.
                'src'     => self::iconUrl($size),
                'sizes'   => "{$size}x{$size}",
                'type'    => 'image/png',
                // 'any maskable' : l'icône s'adapte aux masques Android sans être rognée.
                'purpose' => 'any maskable',
            ];
        }

        return $icons;
    }

    /**
     * URL d'une icône, suffixée d'une empreinte du logo.
     *
     * Sans ce suffixe, le navigateur garderait l'ancienne icône d'onglet en
     * cache après un changement de logo : c'est justement le moment où le
     * manager veut voir sa nouvelle identité apparaître.
     */
    public static function iconUrl(int $size): string
    {
        return route('pwa.icon', ['size' => $size, 'v' => self::brandVersion()]);
    }

    /** Empreinte courte du logo et du nom : change quand la marque change. */
    public static function brandVersion(): string
    {
        $tenant = Tenant::first();

        return substr(md5(($tenant?->settings['logo'] ?? '') . ($tenant?->name ?? '')), 0, 8);
    }

    /** Raccourcis d'application (appui long sur l'icône), selon les modules actifs. */
    private function shortcuts(): array
    {
        $shortcuts = [[
            'name'  => 'Réservations',
            'url'   => '/bookings',
            'icons' => [['src' => self::iconUrl(192), 'sizes' => '192x192']],
        ]];

        if (TenantModules::has('housekeeping')) {
            $shortcuts[] = [
                'name'  => 'Housekeeping',
                'url'   => '/housekeeping',
                'icons' => [['src' => self::iconUrl(192), 'sizes' => '192x192']],
            ];
        }
        if (TenantModules::has('restaurant')) {
            $shortcuts[] = [
                'name'  => 'Restaurant',
                'url'   => '/restaurant/orders',
                'icons' => [['src' => self::iconUrl(192), 'sizes' => '192x192']],
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
            // Icône masquable Android : 18 % de marge, la zone sûre. Un favicon
            // n'est jamais masqué et ne fait que 32 px : la même marge y
            // rendrait le logo illisible, on la réduit fortement.
            $ratio = in_array($size, self::FAVICON_SIZES, true) ? 0.88 : 0.64;
            $inner = (int) round($size * $ratio);
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

    /**
     * Charge le logo du tenant depuis le storage public, si lisible.
     *
     * Un échec ici est invisible pour l'utilisateur : l'icône retombe sur les
     * initiales alors que le logo s'affiche correctement partout ailleurs dans
     * l'application (le navigateur, lui, décode tous les formats). On journalise
     * donc la cause — le plus souvent un GD compilé sans le format en question.
     */
    private function loadLogo(?string $logoPath): ?\GdImage
    {
        if (!$logoPath) {
            return null;
        }

        if (!Storage::disk('public')->exists($logoPath)) {
            Log::warning('PWA : logo introuvable sur le disque public.', ['path' => $logoPath]);

            return null;
        }

        try {
            $image = @imagecreatefromstring(Storage::disk('public')->get($logoPath));
        } catch (\Throwable $e) {
            Log::warning('PWA : lecture du logo impossible.', [
                'path'  => $logoPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$image) {
            Log::warning('PWA : logo non décodable par GD, repli sur les initiales.', [
                'path'             => $logoPath,
                'formats_gd'       => self::supportedFormats(),
                'piste_probable'   => 'GD compilé sans le support de ce format '
                    . '(docker-php-ext-configure gd --with-jpeg --with-webp).',
            ]);

            return null;
        }

        return $image;
    }

    /** Formats que GD sait décoder sur cette installation. */
    private static function supportedFormats(): array
    {
        $types = imagetypes();

        return array_keys(array_filter([
            'png'  => (bool) ($types & IMG_PNG),
            'jpeg' => (bool) ($types & IMG_JPG),
            'gif'  => (bool) ($types & IMG_GIF),
            'webp' => (bool) ($types & IMG_WEBP),
        ]));
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
