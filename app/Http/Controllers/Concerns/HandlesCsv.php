<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Mécanique commune d'import / export CSV, alignée sur RoomCsvController :
 * fichiers UTF-8 avec BOM et délimiteur « ; » (Excel FR), parseur tolérant au
 * BOM et au délimiteur « , ». Les exports produisent exactement la structure
 * attendue par l'import (aller-retour sans perte).
 */
trait HandlesCsv
{
    /**
     * Émet un CSV téléchargeable. $rows est une liste de lignes (tableaux de
     * valeurs alignées sur $headers).
     */
    protected function streamCsv(string $filename, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : accents corrects sous Excel
            fputcsv($out, $headers, ';', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '\\');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Parse le CSV en lignes associatives selon les en-têtes attendus. Tolère
     * BOM UTF-8, délimiteur ; ou , et l'ordre exact des colonnes du modèle.
     *
     * @return array{0: array<int, array<string, ?string>>, 1: ?string} [lignes, erreur]
     */
    protected function parseCsv(string $path, array $expectedHeaders): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [[], 'Impossible de lire le fichier envoyé.'];
        }

        $firstLine = (string) fgets($handle);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $headers = array_map(
            fn ($h) => mb_strtolower(trim((string) $h)),
            str_getcsv($firstLine, $delimiter, '"', '\\')
        );

        $missing = array_diff($expectedHeaders, $headers);
        if ($missing) {
            fclose($handle);
            return [[], 'Colonnes manquantes dans le CSV : ' . implode(', ', $missing)
                . '. Téléchargez le modèle pour obtenir la structure attendue.'];
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue; // ligne vide
            }
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $data[$idx] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return [[], 'Le fichier ne contient aucune ligne de données.'];
        }

        return [$rows, null];
    }

    /**
     * Booléen « actif » : vide vaut vrai (l'article est actif par défaut).
     * « non/0/false/no » → false.
     */
    protected function parseBool(mixed $value): bool
    {
        return !in_array(mb_strtolower(trim((string) $value)), ['non', '0', 'false', 'no'], true);
    }

    /**
     * Drapeau opt-in (VIP, blacklisté, préparation…) : vrai uniquement sur une
     * valeur affirmative explicite ; vide vaut faux.
     */
    protected function parseFlag(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['oui', '1', 'true', 'yes', 'vrai', 'o'], true);
    }

    protected function csvTenantId(): ?int
    {
        return Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
    }

    /**
     * Redirection standard après un import : message de synthèse et jusqu'à
     * 15 erreurs de lignes affichées via la clé de session « import_errors ».
     */
    protected function csvImportRedirect(string $route, array $params, int $created, int $skipped, array $errors, string $successNoun): RedirectResponse
    {
        $parts = ["{$created} {$successNoun}"];
        if ($skipped > 0) {
            $parts[] = "{$skipped} ligne(s) ignorée(s) (déjà existantes)";
        }
        if (count($errors) > 0) {
            $parts[] = count($errors) . ' erreur(s)';
        }

        return redirect()->route($route, $params)
            ->with($created > 0 ? 'success' : 'error', implode(' · ', $parts))
            ->with('import_errors', array_slice($errors, 0, 15));
    }
}
