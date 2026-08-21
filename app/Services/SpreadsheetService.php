<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service central et réutilisable pour l'import / export de tableurs Excel (.xlsx, .xls)
 * et fichiers CSV via PhpSpreadsheet.
 */
class SpreadsheetService
{
    /**
     * Lit un fichier (Excel ou CSV) et le convertit en lignes associatives selon les en-têtes attendus.
     *
     * @param string $path Chemin absolu du fichier temporaire
     * @param array<int, string> $expectedHeaders Liste des colonnes obligatoires (en minuscules)
     * @return array{0: array<int, array<string, ?string>>, 1: ?string} [lignes associatives, message d'erreur éventuel]
     */
    public function parse(string $path, array $expectedHeaders): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            return [[], 'Impossible de lire le fichier envoyé.'];
        }

        try {
            // Détection automatique du type de fichier via PhpSpreadsheet
            $inputFileType = IOFactory::identify($path);
            $reader = IOFactory::createReader($inputFileType);

            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }

            // Si c'est un CSV, configure les délimiteurs possibles
            if ($inputFileType === 'Csv') {
                $firstLine = (string) file_get_contents($path, false, null, 0, 4096);
                $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
                $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
                if (method_exists($reader, 'setDelimiter')) {
                    $reader->setDelimiter($delimiter);
                }
                if (method_exists($reader, 'setInputEncoding')) {
                    $reader->setInputEncoding('UTF-8');
                }
            }

            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 1) {
                return [[], 'Le fichier est vide.'];
            }

            // Recherche de la première ligne d'en-tête non vide
            $headerRowIndex = 1;
            $headers = [];
            for ($r = 1; $r <= min(10, $highestRow); $r++) {
                $candidateHeaders = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $val = $worksheet->getCell([$col, $r])->getValue();
                    $candidateHeaders[] = mb_strtolower(trim((string) $val));
                }
                // Si au moins un en-tête attendu est présent sur cette ligne
                $matches = array_intersect($expectedHeaders, $candidateHeaders);
                if (count($matches) > 0) {
                    $headerRowIndex = $r;
                    $headers = $candidateHeaders;
                    break;
                }
            }

            if (empty($headers)) {
                // Repli sur la première ligne
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $val = $worksheet->getCell([$col, 1])->getValue();
                    $headers[] = mb_strtolower(trim((string) $val));
                }
            }

            // Vérification des colonnes manquantes
            $missing = array_diff($expectedHeaders, $headers);
            if ($missing) {
                return [[], 'Colonnes manquantes dans le fichier : ' . implode(', ', $missing)
                    . '. Téléchargez le modèle pour obtenir la structure attendue.'];
            }

            // Extraction des lignes de données
            $rows = [];
            for ($row = $headerRowIndex + 1; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasContent = false;
                for ($col = 1; $col <= count($headers); $col++) {
                    $headerName = $headers[$col - 1] ?? '';
                    if ($headerName === '') {
                        continue;
                    }
                    $cellValue = $worksheet->getCell([$col, $row])->getFormattedValue();
                    if ($cellValue === null || $cellValue === '') {
                        $cellValue = (string) $worksheet->getCell([$col, $row])->getValue();
                    }
                    $val = trim((string) $cellValue);
                    if ($val !== '') {
                        $hasContent = true;
                    }
                    $rowData[$headerName] = $val;
                }

                if ($hasContent) {
                    $rows[] = $rowData;
                }
            }

            if (empty($rows)) {
                return [[], 'Le fichier ne contient aucune ligne de données.'];
            }

            return [$rows, null];
        } catch (\Throwable $e) {
            return [[], 'Erreur lors de la lecture du fichier : ' . $e->getMessage()];
        }
    }

    /**
     * Génère et télécharge un classeur Excel (.xlsx) stylisé aux couleurs et à l'identité de l'établissement.
     *
     * @param string $filename Nom du fichier téléchargé (ex. 'fiches_techniques.xlsx')
     * @param string $sheetTitle Titre de l'onglet Excel
     * @param array<int, string> $headers Liste des colonnes
     * @param array<int, array<int, mixed>> $rows Données tabulaires
     * @param \App\Models\Tenant|null $tenant Établissement courant pour le branding (logo, palette, métadonnées)
     * @return StreamedResponse
     */
    public function exportXlsx(string $filename, string $sheetTitle, array $headers, array $rows, ?\App\Models\Tenant $tenant = null): StreamedResponse
    {
        $palette = $this->palette($tenant);

        $spreadsheet = new Spreadsheet();
        $this->proprietes($spreadsheet, $tenant, $sheetTitle);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        // 1. Écriture des en-têtes
        $highestColLetter = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $index => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Style des en-têtes aux couleurs de l'établissement
        $headerRange = "A1:{$highestColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => $palette['encre_entete']],
                'size'  => 10,
                'name'  => 'Segoe UI',
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $palette['entete']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // 2. Écriture des lignes de données
        $rowIndex = 2;
        foreach ($rows as $row) {
            foreach (array_values($row) as $colIndex => $val) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
                if (is_numeric($val) && !is_string($val)) {
                    $sheet->setCellValueExplicit("{$colLetter}{$rowIndex}", $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } elseif (is_numeric(str_replace(',', '.', (string) $val)) && !str_starts_with((string) $val, '0') && (string) $val !== '') {
                    $numericVal = (float) str_replace(',', '.', (string) $val);
                    $sheet->setCellValue("{$colLetter}{$rowIndex}", $numericVal);
                } else {
                    $sheet->setCellValue("{$colLetter}{$rowIndex}", (string) $val);
                }
            }
            $sheet->getRowDimension($rowIndex)->setRowHeight(20);
            $rowIndex++;
        }

        $lastRow = max(1, $rowIndex - 1);
        $dataRange = "A1:{$highestColLetter}{$lastRow}";

        // Bordures et police générale
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'size' => 10,
                'color' => ['rgb' => $palette['encre_corps']],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto-dimensionnement des colonnes
        foreach (range(1, count($headers)) as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Fige la ligne d'en-tête
        $sheet->freezePane('A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /** Métadonnées du classeur signées par l'établissement. */
    private function proprietes(Spreadsheet $spreadsheet, ?\App\Models\Tenant $tenant, string $title): void
    {
        $nom = $tenant?->name ?? 'Villa Boutanga';
        $spreadsheet->getProperties()
            ->setCreator($nom)
            ->setLastModifiedBy($nom)
            ->setTitle($title . ' — ' . $nom)
            ->setSubject($title)
            ->setCompany($nom);
    }

    /** Palette de couleurs extraite des réglages de l'établissement (avec repli). */
    private function palette(?\App\Models\Tenant $tenant): array
    {
        $theme = is_array($tenant?->settings['theme'] ?? null) ? $tenant->settings['theme'] : [];
        $titre = $this->hex($theme['primary'] ?? null, '1E293B');
        $entete = $this->hex($theme['secondary'] ?? null, $titre);

        return [
            'entete'       => $entete,
            'encre_entete' => $this->encreSur($entete),
            'encre_corps'  => '1E293B',
        ];
    }

    private function hex(?string $valeur, string $defaut): string
    {
        $propre = strtoupper(ltrim(trim((string) $valeur), '#'));
        if (strlen($propre) === 3) {
            $propre = $propre[0] . $propre[0] . $propre[1] . $propre[1] . $propre[2] . $propre[2];
        }

        return preg_match('/^[0-9A-F]{6}$/', $propre) ? $propre : $defaut;
    }

    private function encreSur(string $fond): string
    {
        $r = (int) hexdec(substr($fond, 0, 2));
        $v = (int) hexdec(substr($fond, 2, 2));
        $b = (int) hexdec(substr($fond, 4, 2));
        $luminance = (0.2126 * $r + 0.7152 * $v + 0.0722 * $b) / 255;

        return $luminance > 0.55 ? '1E293B' : 'FFFFFF';
    }

    /**
     * Émet un fichier CSV téléchargeable (avec BOM UTF-8 et délimiteur ';').
     */
    public function exportCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $headers, ';', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '\\');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
