<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import / export CSV du fichier clients (CRM). Doublons repérés par email.
 */
class CustomerCsvController extends Controller
{
    use HandlesCsv;

    private const HEADERS = [
        'prenom', 'nom', 'email', 'telephone', 'pays', 'nationalite',
        'type_piece', 'numero_piece', 'date_naissance', 'adresse', 'ville',
        'vip', 'blackliste', 'notes',
    ];

    public function export(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_clients.csv', self::HEADERS, [
                ['Jean', 'Dupont', 'jean.dupont@example.com', '+237690000000', 'CM', 'Camerounaise', '', '', '1985-03-12', 'Bonapriso', 'Douala', 'non', 'non', ''],
                ['Marie', 'Laurent', 'marie.l@example.fr', '+33600000000', 'FR', 'Française', '', '', '', '', 'Paris', 'oui', 'non', 'Cliente fidèle'],
            ]);
        }

        $rows = Customer::orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn (Customer $c) => [
                $c->first_name,
                $c->last_name,
                $c->email,
                $c->phone,
                $c->country,
                $c->nationality,
                $c->id_document_type,
                $c->id_document_number,
                $c->date_of_birth?->format('Y-m-d'),
                $c->address,
                $c->city,
                $c->is_vip ? 'oui' : 'non',
                $c->is_blacklisted ? 'oui' : 'non',
                $c->notes,
            ])->all();

        return $this->streamCsv('clients_' . now()->format('Ymd_His') . '.csv', self::HEADERS, $rows);
    }

    public function import(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $tenantId = $this->csvTenantId();
        // Emails déjà connus (clé de dédoublonnage), en minuscules.
        $existingEmails = Customer::whereNotNull('email')->pluck('email')
            ->map(fn ($e) => mb_strtolower(trim($e)))->flip();

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $first = trim((string) ($row['prenom'] ?? ''));
            $last  = trim((string) ($row['nom'] ?? ''));
            if ($first === '' || $last === '') {
                $errors[] = "Ligne {$line} : prénom et nom obligatoires.";
                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Ligne {$line} : email « {$email} » invalide.";
                    continue;
                }
                if (isset($existingEmails[mb_strtolower($email)])) {
                    $skipped++;
                    continue;
                }
            }

            // Pays : code ISO connu, sinon on ignore la valeur plutôt qu'échouer.
            $country = Countries::normalize(trim((string) ($row['pays'] ?? '')));

            $dob = trim((string) ($row['date_naissance'] ?? ''));
            $birthDate = null;
            if ($dob !== '') {
                try {
                    $birthDate = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = "Ligne {$line} : date de naissance « {$dob} » invalide (format AAAA-MM-JJ attendu).";
                    continue;
                }
            }

            Customer::create([
                'first_name'         => $first,
                'last_name'          => $last,
                'email'              => $email ?: null,
                'phone'              => trim((string) ($row['telephone'] ?? '')) ?: null,
                'country'            => $country,
                'nationality'        => trim((string) ($row['nationalite'] ?? '')) ?: null,
                'id_document_type'   => trim((string) ($row['type_piece'] ?? '')) ?: null,
                'id_document_number' => trim((string) ($row['numero_piece'] ?? '')) ?: null,
                'date_of_birth'      => $birthDate,
                'address'            => trim((string) ($row['adresse'] ?? '')) ?: null,
                'city'               => trim((string) ($row['ville'] ?? '')) ?: null,
                'is_vip'             => $this->parseFlag($row['vip'] ?? ''),
                'is_blacklisted'     => $this->parseFlag($row['blackliste'] ?? ''),
                'notes'              => trim((string) ($row['notes'] ?? '')) ?: null,
                'tenant_id'          => $tenantId,
            ]);

            if ($email !== '') {
                $existingEmails[mb_strtolower($email)] = true;
            }
            $created++;
        }

        AuditLog::record(Auth::id(), 'customers_import',
            "Import CSV de clients : {$created} créé(s), {$skipped} ignoré(s), " . count($errors) . ' erreur(s)',
            'customers');

        return $this->csvImportRedirect('customers.index', [], $created, $skipped, $errors, 'client(s) créé(s)');
    }
}
