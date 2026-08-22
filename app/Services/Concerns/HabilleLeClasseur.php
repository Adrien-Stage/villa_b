<?php

namespace App\Services\Concerns;

use App\Models\Tenant;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * L'habillage commun des classeurs de fiches techniques — hébergement comme
 * cuisine.
 *
 * Un établissement n'a qu'une charte : ses fiches de chambres et ses fiches de
 * cuisine doivent se ressembler au point qu'on les reconnaisse posées côte à
 * côte sur un bureau. C'est pourquoi la palette, les bandeaux, la ligne de
 * marque et le logo vivent ici plutôt que d'être recopiés dans chaque classeur.
 *
 * Sans thème configuré, on retombe exactement sur la charte des documents de
 * référence ; avec un thème, les bandeaux prennent les couleurs de
 * l'établissement et l'encre s'ajuste pour rester lisible.
 */
trait HabilleLeClasseur
{
    protected const FMT_FCFA     = '#,##0 "FCFA"';
    protected const FMT_FCFA_FIN = '#,##0.00 "FCFA"';
    protected const FMT_TAUX     = '0.0%';
    protected const FMT_ENTIER   = '0';
    protected const FMT_DECIMAL  = '#,##0.00';
    protected const FMT_QUANTITE = '0.###';

    /** Palette courante du document. @var array<string,string> */
    protected array $c = [];

    // ── Palette de l'établissement ───────────────────────────────────────────

    /**
     * Couleurs du document, dérivées du thème de l'établissement.
     *
     * @return array<string,string>
     */
    protected function palette(?Tenant $tenant): array
    {
        $theme = is_array($tenant?->settings['theme'] ?? null) ? $tenant->settings['theme'] : [];

        $titre   = $this->hex($theme['primary'] ?? null, '1E293B');
        $section = $this->hex($theme['surface_dark'] ?? null, '0F766E');
        $entete  = $this->hex($theme['secondary'] ?? null, '334155');
        $accent  = $this->hex($theme['accent'] ?? null, null);

        return [
            'titre'         => $titre,
            'titre_encre'   => $this->encreSur($titre),
            'section'       => $section,
            'section_encre' => $this->encreSur($section),
            'entete'        => $entete,
            'entete_encre'  => $this->encreSur($entete),
            // Fonds clairs : teintes de l'accent, sinon les gris du modèle.
            'total'         => $accent ? $this->eclaircir($accent, 0.82) : 'F1F5F9',
            'cle'           => $accent ? $this->eclaircir($accent, 0.68) : 'ECFDF5',
            'moyenne'       => $accent ? $this->eclaircir($accent, 0.55) : 'E2E8F0',
            'encre'         => '1E293B',
            'gris'          => '64748B',
            'rouge'         => 'B91C1C',
            'blanc'         => 'FFFFFF',
        ];
    }

    protected function hex(?string $valeur, ?string $defaut): ?string
    {
        $propre = strtoupper(ltrim(trim((string) $valeur), '#'));

        if (strlen($propre) === 3) {
            $propre = $propre[0] . $propre[0] . $propre[1] . $propre[1] . $propre[2] . $propre[2];
        }

        return preg_match('/^[0-9A-F]{6}$/', $propre) ? $propre : $defaut;
    }

    /** Blanc ou encre foncée, selon ce qui se lit sur ce fond. */
    protected function encreSur(string $fond): string
    {
        [$r, $v, $b] = $this->canaux($fond);
        $luminance = (0.2126 * $r + 0.7152 * $v + 0.0722 * $b) / 255;

        return $luminance > 0.55 ? '1E293B' : 'FFFFFF';
    }

    /** Teinte pâle d'une couleur, mélangée à du blanc. */
    protected function eclaircir(string $couleur, float $part): string
    {
        [$r, $v, $b] = $this->canaux($couleur);

        return sprintf(
            '%02X%02X%02X',
            (int) round($r + (255 - $r) * $part),
            (int) round($v + (255 - $v) * $part),
            (int) round($b + (255 - $b) * $part)
        );
    }

    /** @return array{0:int,1:int,2:int} */
    protected function canaux(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    // ── Signature de l'établissement ─────────────────────────────────────────

    /** Métadonnées du classeur : c'est l'établissement qui édite, pas l'outil. */
    protected function proprietes(Spreadsheet $classeur, ?Tenant $tenant, string $titre, string $sujet): void
    {
        $nom = $tenant?->name ?: config('app.name');

        $classeur->getProperties()
            ->setCreator($nom)
            ->setLastModifiedBy($nom)
            ->setCompany($nom)
            ->setTitle($titre)
            ->setSubject($sujet)
            ->setDescription($titre . ' — ' . $nom . ' — ' . now()->format('d/m/Y'));
    }

    /** Ligne d'identité : qui édite ce document, et quand. */
    protected function ligneMarque(Worksheet $ws, string $cellule, ?Tenant $tenant, string $prefixe = ''): void
    {
        $morceaux = array_filter([
            $prefixe ?: null,
            $tenant?->name,
            $tenant?->address,
            $tenant?->phone,
            $tenant?->email,
            'Exporté le ' . now()->format('d/m/Y à H:i'),
        ]);

        $ws->getRowDimension((int) filter_var($cellule, FILTER_SANITIZE_NUMBER_INT))->setRowHeight(20);
        $ws->setCellValue($cellule, implode(' · ', $morceaux));
        $this->style($ws, $cellule, ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris']]);
    }

    /**
     * Logo de l'établissement, posé sur le bandeau de titre.
     * Un logo absent ou illisible ne fait pas échouer l'export.
     */
    protected function logo(Worksheet $ws, ?Tenant $tenant): void
    {
        $fichier = $tenant?->settings['logo'] ?? null;
        if (!$fichier) {
            return;
        }

        $chemin = storage_path('app/public/' . ltrim((string) $fichier, '/'));
        if (!is_file($chemin) || !@getimagesize($chemin)) {
            return;
        }

        try {
            $dessin = new Drawing();
            $dessin->setName($tenant->name ?? 'Logo');
            $dessin->setDescription('Logo ' . ($tenant->name ?? ''));
            $dessin->setPath($chemin);
            $dessin->setHeight(38);
            $dessin->setCoordinates('A1');
            $dessin->setOffsetX(8);
            $dessin->setOffsetY(8);
            $dessin->setWorksheet($ws);
        } catch (\Throwable) {
            // Un export sans logo reste un export utilisable.
        }
    }

    /** Titre d'un onglet, précédé du nom de l'établissement quand il est connu. */
    protected function titreSigne(?Tenant $tenant, string $titre): string
    {
        return $tenant?->name ? mb_strtoupper($tenant->name) . ' — ' . $titre : $titre;
    }

    // ── Mise en forme ────────────────────────────────────────────────────────

    /** @param array<string,float> $largeurs */
    protected function largeurs(Worksheet $ws, array $largeurs): void
    {
        foreach ($largeurs as $colonne => $largeur) {
            $ws->getColumnDimension($colonne)->setWidth($largeur);
        }
    }

    protected function bandeau(Worksheet $ws, string $plage, string $texte, string $fond, string $encre, float $taille, string $align, float $hauteur): void
    {
        $premiere = explode(':', $plage)[0];
        $ligne = (int) filter_var($premiere, FILTER_SANITIZE_NUMBER_INT);

        $ws->mergeCells($plage);
        $ws->setCellValue($premiere, $texte);
        $ws->getRowDimension($ligne)->setRowHeight($hauteur);

        $this->style($ws, $plage, [
            'taille' => $taille, 'gras' => true, 'couleur' => $encre,
            'fond' => $fond, 'align' => $align, 'vertical' => true,
        ]);
    }

    /** Bandeau de section, à la couleur secondaire de l'établissement. */
    protected function section(Worksheet $ws, string $plage, string $texte): void
    {
        $this->bandeau($ws, $plage, $texte, $this->c['section'], $this->c['section_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);
    }

    /** @param array<int,string> $colonnes */
    protected function enteteTableau(Worksheet $ws, int $ligne, array $colonnes, float $hauteur = 24, bool $retour = false): void
    {
        $ws->getRowDimension($ligne)->setRowHeight($hauteur);

        foreach ($colonnes as $i => $libelle) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $ligne, $libelle);
        }

        $plage = 'A' . $ligne . ':' . Coordinate::stringFromColumnIndex(count($colonnes)) . $ligne;
        $this->style($ws, $plage, [
            'taille' => 10, 'gras' => true, 'couleur' => $this->c['entete_encre'],
            'fond' => $this->c['entete'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true,
            'retour' => $retour,
        ]);
        $this->bordures($ws, $plage);
    }

    /** @param array<string,mixed> $options */
    protected function style(Worksheet $ws, string $plage, array $options): void
    {
        $style = $ws->getStyle($plage);

        $police = $style->getFont();
        $police->setName('Calibri');
        if (isset($options['taille'])) {
            $police->setSize($options['taille']);
        }
        if (!empty($options['gras'])) {
            $police->setBold(true);
        }
        if (!empty($options['italique'])) {
            $police->setItalic(true);
        }
        $police->getColor()->setARGB('FF' . ($options['couleur'] ?? $this->c['encre']));

        if (!empty($options['fond'])) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $options['fond']);
        }
        // « 0 » est un format légitime : le tester avec empty() le perdrait.
        if (isset($options['format']) && $options['format'] !== '') {
            $style->getNumberFormat()->setFormatCode($options['format']);
        }
        if (!empty($options['align'])) {
            $style->getAlignment()->setHorizontal($options['align']);
        }
        if (!empty($options['vertical'])) {
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        if (!empty($options['retour'])) {
            $style->getAlignment()->setWrapText(true);
        }
    }

    protected function bordures(Worksheet $ws, string $plage): void
    {
        $ws->getStyle($plage)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FFCBD5E1');
    }

    /** Nom d'onglet valide et unique : Excel refuse 31+ caractères et []:*?/\ */
    protected function nomOnglet(Spreadsheet $classeur, string $nom): string
    {
        $propre = trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $nom)) ?: 'Fiche';
        $propre = mb_substr($propre, 0, 31);
        $propre = preg_replace('/[\s&+,;·\-–—]+$/u', '', $propre) ?: 'Fiche';

        $candidat = $propre;
        $suffixe = 2;
        while ($classeur->sheetNameExists($candidat)) {
            $marque = ' (' . $suffixe . ')';
            $candidat = mb_substr($propre, 0, 31 - mb_strlen($marque)) . $marque;
            $suffixe++;
        }

        return $candidat;
    }

    /** Référence d'onglet utilisable dans une formule. */
    protected function reference(string $onglet): string
    {
        return "'" . str_replace("'", "''", $onglet) . "'";
    }
}
