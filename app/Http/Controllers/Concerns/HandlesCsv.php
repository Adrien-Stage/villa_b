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
     * Émet un classeur Excel (.xlsx) stylisé aux couleurs et à l'identité de l'établissement.
     */
    protected function streamXlsx(string $filename, string $sheetTitle, array $headers, array $rows, ?\App\Models\Tenant $tenant = null)
    {
        $tenant ??= $this->tenantCourant();

        return app(\App\Services\SpreadsheetService::class)->exportXlsx($filename, $sheetTitle, $headers, $rows, $tenant);
    }

    /**
     * Établissement en cours : signe le document exporté et lui donne sa charte graphique.
     */
    protected function tenantCourant(): ?\App\Models\Tenant
    {
        $id = $this->csvTenantId();

        return $id ? \App\Models\Tenant::find($id) : null;
    }

    /**
     * Émet un CSV téléchargeable. $rows est une liste de lignes (tableaux de
     * valeurs alignées sur $headers).
     */
    protected function streamCsv(string $filename, array $headers, array $rows)
    {
        return app(\App\Services\SpreadsheetService::class)->exportCsv($filename, $headers, $rows);
    }

    /**
     * Parse un fichier tableur (Excel XLSX, XLS ou CSV) en lignes associatives selon les en-têtes attendus.
     *
     * @return array{0: array<int, array<string, ?string>>, 1: ?string} [lignes, erreur]
     */
    protected function parseSpreadsheet(string $path, array $expectedHeaders): array
    {
        return app(\App\Services\SpreadsheetService::class)->parse($path, $expectedHeaders);
    }

    /**
     * Parse le CSV ou Excel en lignes associatives selon les en-têtes attendus. Tolère
     * BOM UTF-8, délimiteur ; ou , classeurs Excel et l'ordre des colonnes du modèle.
     *
     * @return array{0: array<int, array<string, ?string>>, 1: ?string} [lignes, erreur]
     */
    protected function parseCsv(string $path, array $expectedHeaders): array
    {
        return app(\App\Services\SpreadsheetService::class)->parse($path, $expectedHeaders);
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
            ?? \App\Models\Tenant::current()?->id;
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
