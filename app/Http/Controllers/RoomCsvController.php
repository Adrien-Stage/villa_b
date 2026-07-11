<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import / export CSV des chambres et des types de chambre (module
 * hébergement). Pensé pour le déploiement chez un client : on remplit le
 * modèle CSV (types d'abord, puis chambres), on importe, et l'hôtel est
 * enregistré d'un coup — les photos restent ajoutées manuellement sur
 * chaque fiche (bouton Modifier).
 *
 * Format : UTF-8 avec BOM, délimiteur « ; » (Excel FR) — le parseur accepte
 * aussi « , ». Les exports produisent exactement la structure attendue par
 * l'import (aller-retour sans perte, photos exclues).
 */
class RoomCsvController extends Controller
{
    private const ROOM_HEADERS = ['numero', 'code_type', 'etage', 'vue', 'statut', 'notes', 'actif'];
    private const TYPE_HEADERS = ['code', 'nom', 'description', 'capacite_base', 'capacite_max', 'prix_base_fcfa', 'superficie_m2', 'configuration_lits', 'equipements', 'actif'];

    // ── Exports ───────────────────────────────────────────────────────────────

    public function exportRooms(Request $request)
    {
        // ?template=1 : modèle vide avec lignes d'exemple, pour remplissage
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_chambres.csv', self::ROOM_HEADERS, [
                ['101', 'STD', '1', 'Jardin', 'available', 'Proche ascenseur', 'oui'],
                ['102', 'STD', '1', 'Piscine', '', '', 'oui'],
                ['201', 'SUITE', '2', 'Panoramique', '', 'Suite d\'angle', 'oui'],
            ]);
        }

        $rooms = Room::with('roomType')->orderBy('number')->get();

        $rows = $rooms->map(fn (Room $room) => [
            $room->number,
            $room->roomType?->code,
            $room->floor,
            $room->view_type,
            $room->status?->value,
            $room->notes,
            $room->is_active ? 'oui' : 'non',
        ])->all();

        return $this->streamCsv('chambres_' . now()->format('Ymd_His') . '.csv', self::ROOM_HEADERS, $rows);
    }

    public function exportTypes(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_types_chambre.csv', self::TYPE_HEADERS, [
                ['STD', 'Standard', 'Chambre confortable pour 2 personnes', '2', '2', '25000', '18', '1 lit double', 'Wi-Fi|Climatisation|TV', 'oui'],
                ['SUITE', 'Suite Junior', 'Suite spacieuse avec coin salon', '2', '4', '45000', '45', '1 lit king size + canapé-lit', 'Wi-Fi|Climatisation|TV|Mini-bar|Vue panoramique', 'oui'],
            ]);
        }

        $types = RoomType::orderBy('name')->get();

        $rows = $types->map(fn (RoomType $type) => [
            $type->code,
            $type->name,
            $type->description,
            $type->base_capacity,
            $type->max_capacity,
            (int) round($type->base_price / 100), // centimes -> FCFA
            $type->size_sqm,
            $type->bed_configuration,
            implode('|', $type->amenities ?? []),
            $type->is_active ? 'oui' : 'non',
        ])->all();

        return $this->streamCsv('types_chambre_' . now()->format('Ymd_His') . '.csv', self::TYPE_HEADERS, $rows);
    }

    // ── Imports ───────────────────────────────────────────────────────────────

    public function importRooms(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::ROOM_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId = $this->tenantId();
        $typesByCode = RoomType::all()->keyBy(fn ($t) => mb_strtoupper(trim($t->code)));
        $existing = Room::pluck('number')->map(fn ($n) => mb_strtoupper(trim($n)))->flip();
        $validStatuses = array_column(RoomStatus::cases(), 'value');

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // 1 = en-têtes

            $number = trim((string) ($row['numero'] ?? ''));
            if ($number === '') {
                $errors[] = "Ligne {$line} : numéro de chambre manquant.";
                continue;
            }
            if (isset($existing[mb_strtoupper($number)])) {
                $skipped++;
                continue;
            }

            $typeCode = mb_strtoupper(trim((string) ($row['code_type'] ?? '')));
            $type = $typesByCode->get($typeCode);
            if (!$type) {
                $errors[] = "Ligne {$line} : type « {$row['code_type']} » introuvable — importez d'abord les types de chambre.";
                continue;
            }

            $status = trim((string) ($row['statut'] ?? ''));
            if ($status === '') {
                $status = RoomStatus::AVAILABLE->value;
            } elseif (!in_array($status, $validStatuses, true)) {
                $errors[] = "Ligne {$line} : statut « {$status} » invalide (valeurs : " . implode(', ', $validStatuses) . ').';
                continue;
            }

            Room::create([
                'room_type_id' => $type->id,
                'number'       => $number,
                'floor'        => trim((string) ($row['etage'] ?? '')) ?: null,
                'view_type'    => trim((string) ($row['vue'] ?? '')) ?: null,
                'status'       => $status,
                'notes'        => trim((string) ($row['notes'] ?? '')) ?: null,
                'is_active'    => $this->parseBool($row['actif'] ?? 'oui'),
                'tenant_id'    => $tenantId,
            ]);
            $existing[mb_strtoupper($number)] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'rooms_import',
            "Import CSV de chambres : {$created} créée(s), {$skipped} ignorée(s) (déjà existantes), " . count($errors) . ' erreur(s)',
            'rooms');

        return $this->importRedirect('rooms', $created, $skipped, $errors,
            "chambre(s) créée(s) — ajoutez maintenant les photos via « Modifier » sur chaque chambre.");
    }

    public function importTypes(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::TYPE_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId = $this->tenantId();
        $existing = RoomType::pluck('code')->map(fn ($c) => mb_strtoupper(trim($c)))->flip();

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['nom'] ?? ''));
            if ($code === '' || $name === '') {
                $errors[] = "Ligne {$line} : code et nom sont obligatoires.";
                continue;
            }
            if (isset($existing[mb_strtoupper($code)])) {
                $skipped++;
                continue;
            }

            $baseCap = (int) ($row['capacite_base'] ?? 0);
            $maxCap  = (int) ($row['capacite_max'] ?? 0);
            $price   = $row['prix_base_fcfa'] ?? '';
            if ($baseCap < 1 || $maxCap < 1) {
                $errors[] = "Ligne {$line} : capacités invalides (entiers ≥ 1 attendus).";
                continue;
            }
            if (!is_numeric($price) || (int) $price < 0) {
                $errors[] = "Ligne {$line} : prix_base_fcfa invalide (entier en FCFA attendu, ex. 25000).";
                continue;
            }

            $amenities = array_values(array_filter(array_map('trim', explode('|', (string) ($row['equipements'] ?? '')))));

            RoomType::create([
                'code'              => $code,
                'name'              => $name,
                'description'       => trim((string) ($row['description'] ?? '')) ?: null,
                'base_capacity'     => $baseCap,
                'max_capacity'      => max($maxCap, $baseCap),
                'base_price'        => (int) $price * 100, // FCFA -> centimes
                'size_sqm'          => is_numeric($row['superficie_m2'] ?? null) ? (int) $row['superficie_m2'] : null,
                'bed_configuration' => trim((string) ($row['configuration_lits'] ?? '')) ?: null,
                'amenities'         => $amenities,
                'is_active'         => $this->parseBool($row['actif'] ?? 'oui'),
                'tenant_id'         => $tenantId,
            ]);
            $existing[mb_strtoupper($code)] = true;
            $created++;
        }

        AuditLog::record(Auth::id(), 'room_types_import',
            "Import CSV de types de chambre : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'rooms');

        return $this->importRedirect('types', $created, $skipped, $errors,
            'type(s) de chambre créé(s) — vous pouvez maintenant importer les chambres.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function streamCsv(string $filename, array $headers, array $rows)
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
     * Parse le CSV en lignes associatives selon les en-têtes attendus.
     * Tolère BOM UTF-8, délimiteur ; ou , et l'ordre exact des colonnes du
     * modèle fourni. Retourne [rows, erreur|null].
     */
    private function parseCsv(string $path, array $expectedHeaders): array
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

    private function parseBool(mixed $value): bool
    {
        return !in_array(mb_strtolower(trim((string) $value)), ['non', '0', 'false', 'no'], true);
    }

    private function tenantId(): ?int
    {
        return Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
    }

    private function importRedirect(string $tab, int $created, int $skipped, array $errors, string $successSuffix)
    {
        $parts = ["{$created} {$successSuffix}"];
        if ($skipped > 0) {
            $parts[] = "{$skipped} ligne(s) ignorée(s) (déjà existantes)";
        }

        $redirect = redirect()->route('rooms.index', ['tab' => $tab])
            ->with($created > 0 ? 'success' : 'error', implode(' · ', $parts))
            ->with('import_errors', array_slice($errors, 0, 15));

        return $redirect;
    }
}
