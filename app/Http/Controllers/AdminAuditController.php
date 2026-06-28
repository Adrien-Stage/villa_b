<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        $activeTab = $request->input('tab', 'audit');
        $subTab = $request->input('sub', 'logs'); // logs or users

        // Logs Query with filters
        $logsQuery = AuditLog::with(['user', 'tenant'])->latest();

        if ($request->filled('tenant_id')) {
            $logsQuery->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('user_id')) {
            $logsQuery->where('user_id', $request->user_id);
        }

        if ($request->filled('event_type')) {
            $logsQuery->where('event_type', $request->event_type);
        }

        if ($request->filled('module')) {
            $logsQuery->where('module', $request->module);
        }

        if ($request->filled('date_from')) {
            $logsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $logsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $logsQuery->paginate(20, ['*'], 'logs_page')->withQueryString();

        // Users Query with search
        $usersQuery = User::with(['tenant', 'roles']);

        if ($request->filled('user_search')) {
            $search = trim((string) $request->user_search);
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('role', 'ilike', "%{$search}%");
            });
        }

        $users = $usersQuery->orderBy('name')->paginate(10, ['*'], 'users_page')->withQueryString();

        // Fetch helper lists for filters
        $tenants = Tenant::orderBy('name')->get();
        $allUsers = User::orderBy('name')->get();

        // Calculate statistics for the sidebar
        $auditStats = [
            'total_logs' => AuditLog::count(),
            'failed_logins' => AuditLog::where('event_type', 'failed_login')->count(),
            'access_denied' => AuditLog::where('event_type', 'access_denied')->count(),
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
        ];

        return view('admin.dashboard', compact(
            'activeTab',
            'subTab',
            'logs',
            'users',
            'tenants',
            'allUsers',
            'auditStats'
        ));
    }

    public function toggleUserActive(User $user)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusStr = $user->is_active ? 'activé' : 'désactivé';
        $actionMessage = "Le compte de l'utilisateur {$user->name} ({$user->email}) a été {$statusStr} par l'administrateur.";

        AuditLog::record(
            Auth::id(),
            'user_management',
            $actionMessage,
            'security',
            ['target_user_id' => $user->id, 'is_active' => $user->is_active]
        );

        return back()->with('success', "Le compte de {$user->name} a été {$statusStr} avec succès.");
    }

    public function forcePasswordReset(User $user)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        // Generate a secure temporary password
        $tempPassword = Str::random(10) . '@B2026';

        $user->update([
            'password' => Hash::make($tempPassword),
        ]);

        $actionMessage = "Le mot de passe de l'utilisateur {$user->name} ({$user->email}) a été réinitialisé de force par l'administrateur.";

        AuditLog::record(
            Auth::id(),
            'user_management',
            $actionMessage,
            'security',
            ['target_user_id' => $user->id]
        );

        return back()->with('temp_password_info', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $tempPassword,
        ]);
    }

    public function createTenant()
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        return view('admin.tenants.create');
    }

    public function showTenant(Tenant $tenant)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        $tenant->loadCount(['users', 'rooms', 'bookings']);

        $tenantUsers = User::where('tenant_id', $tenant->id)
            ->with('roles')
            ->orderBy('name')
            ->get();

        $section = request('section', 'overview');

        return view('admin.tenants.show', compact('tenant', 'tenantUsers', 'section'));
    }

    public function storeTenant(Request $request)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'country' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'theme' => ['nullable', 'array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.surface_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_light' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $settings = [];
        $settings['country'] = $validated['country'] ?? null;

        if ($request->has('theme')) {
            $settings['theme'] = $validated['theme'];
        }

        if ($request->hasFile('logo')) {
            $settings['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'currency' => $validated['currency'],
            'settings' => $settings,
            'is_active' => true,
        ]);

        AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Création d'un nouvel établissement : {$tenant->name} (slug: {$tenant->slug})",
            'settings',
            ['tenant_id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug]
        );

        return redirect()
            ->route('admin.dashboard', ['tab' => 'tenants'])
            ->with('success', "L'établissement « {$tenant->name} » a été créé avec succès.");
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug,' . $tenant->id],
            'country' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'logo' => ['nullable', 'image', 'max:2048'], // 2MB max
            'theme' => ['nullable', 'array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.surface_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_light' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $settings = $tenant->settings ?? [];
        $settings['country'] = $validated['country'] ?? null;
        
        if ($request->has('theme')) {
            $settings['theme'] = $validated['theme'];
        }

        if ($request->hasFile('logo')) {
            if (!empty($settings['logo'])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($settings['logo']);
            }
            $settings['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $tenant->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'currency' => $validated['currency'],
            'settings' => $settings,
        ]);

        AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Modification des informations générales de l'établissement {$tenant->name}",
            'settings',
            ['tenant_id' => $tenant->id, 'changes' => array_diff_key($validated, ['logo' => ''])]
        );

        return back()->with('success', "Les informations de l'établissement {$tenant->name} ont été mises à jour avec succès.");
    }

    public function exportSupervision()
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Export du rapport de supervision des établissements au format CSV",
            'settings',
            []
        );

        $headers = [
            'Content-type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=rapport_supervision_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM
            fwrite($file, "\xEF\xBB\xBF");

            // Headers
            fputcsv($file, [
                'ID Établissement',
                'Nom',
                'Slug',
                'Pays',
                'Adresse',
                'Téléphone',
                'Email',
                'Devise',
                'Statut',
                'Utilisateurs Rattachés',
                'Réservations Totales'
            ], ';');

            $tenants = Tenant::withCount(['users', 'bookings'])->orderBy('name')->get();

            foreach ($tenants as $tenant) {
                fputcsv($file, [
                    $tenant->id,
                    $tenant->name,
                    $tenant->slug,
                    $tenant->settings['country'] ?? 'Cameroun',
                    $tenant->address ?? 'N/A',
                    $tenant->phone ?? 'N/A',
                    $tenant->email ?? 'N/A',
                    $tenant->currency,
                    $tenant->is_active ? 'Actif' : 'Inactif',
                    $tenant->users_count,
                    $tenant->bookings_count
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportBackup()
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Exportation d'une sauvegarde complète de la base de données au format ZIP",
            'settings',
            []
        );

        $connection = \Illuminate\Support\Facades\DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();

        $sql = "-- Villa Boutanga Database Backup\n";
        $sql .= "-- Driver: " . $driver . "\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n\n";

        if ($driver === 'sqlite') {
            $sql .= "PRAGMA foreign_keys = OFF;\n\n";

            $tables = $pdo->query("
                SELECT name 
                FROM sqlite_master 
                WHERE type='table' 
                  AND name NOT LIKE 'sqlite_%'
                ORDER BY name
            ")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $sql .= "-- -----------------------------------------------------\n";
                $sql .= "-- Structure de la table \"{$table}\"\n";
                $sql .= "-- -----------------------------------------------------\n";
                $sql .= "DROP TABLE IF EXISTS \"{$table}\";\n";
                
                $sqlStmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = :table");
                $sqlStmt->execute(['table' => $table]);
                $createSql = $sqlStmt->fetchColumn();
                
                $sql .= $createSql . ";\n\n";

                // Dump data
                $sql .= "-- Contenu de la table \"{$table}\"\n";
                $dataStmt = $pdo->query("SELECT * FROM \"{$table}\"");
                $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $vals = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $vals[] = 'NULL';
                            } elseif (is_bool($val)) {
                                $vals[] = $val ? '1' : '0';
                            } elseif (is_numeric($val)) {
                                $vals[] = $val;
                            } else {
                                $vals[] = "'" . str_replace("'", "''", $val) . "'";
                            }
                        }
                        $sql .= "INSERT INTO \"{$table}\" (\"" . implode('", "', $cols) . "\") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                }
                $sql .= "\n";
            }

            $sql .= "PRAGMA foreign_keys = ON;\n";
        } else {
            // PostgreSQL logic
            $sql .= "SET session_replication_role = 'replica';\n\n";

            $tables = $pdo->query("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public' 
                  AND table_type = 'BASE TABLE'
                ORDER BY table_name
            ")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $sql .= "-- -----------------------------------------------------\n";
                $sql .= "-- Structure de la table \"{$table}\"\n";
                $sql .= "-- -----------------------------------------------------\n";
                $sql .= "DROP TABLE IF EXISTS \"{$table}\" CASCADE;\n";
                $sql .= "CREATE TABLE \"{$table}\" (\n";

                $colsStmt = $pdo->prepare("
                    SELECT column_name, data_type, character_maximum_length, is_nullable, column_default 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' AND table_name = :table
                    ORDER BY ordinal_position
                ");
                $colsStmt->execute(['table' => $table]);
                $columns = $colsStmt->fetchAll(\PDO::FETCH_ASSOC);

                $colDefinitions = [];
                foreach ($columns as $col) {
                    $def = '  "' . $col['column_name'] . '" ' . $col['data_type'];
                    if ($col['character_maximum_length']) {
                        $def .= '(' . $col['character_maximum_length'] . ')';
                    }
                    if ($col['is_nullable'] === 'NO') {
                        $def .= ' NOT NULL';
                    }
                    if ($col['column_default'] !== null) {
                        $def .= ' DEFAULT ' . $col['column_default'];
                    }
                    $colDefinitions[] = $def;
                }

                $pkStmt = $pdo->prepare("
                    SELECT kcu.column_name
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.key_column_usage kcu
                      ON tc.constraint_name = kcu.constraint_name
                      AND tc.table_schema = kcu.table_schema
                    WHERE tc.constraint_type = 'PRIMARY KEY'
                      AND tc.table_name = :table
                ");
                $pkStmt->execute(['table' => $table]);
                $pks = $pkStmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($pks)) {
                    $colDefinitions[] = '  PRIMARY KEY ("' . implode('", "', $pks) . '")';
                }

                $sql .= implode(",\n", $colDefinitions);
                $sql .= "\n);\n\n";

                $sql .= "-- Contenu de la table \"{$table}\"\n";
                $dataStmt = $pdo->query("SELECT * FROM \"{$table}\"");
                $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $vals = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $vals[] = 'NULL';
                            } elseif (is_bool($val)) {
                                $vals[] = $val ? 'TRUE' : 'FALSE';
                            } elseif (is_numeric($val)) {
                                $vals[] = $val;
                            } else {
                                $vals[] = "'" . str_replace("'", "''", $val) . "'";
                            }
                        }
                        $sql .= "INSERT INTO \"{$table}\" (\"" . implode('", "', $cols) . "\") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                }
                $sql .= "\n";
            }

            $sql .= "SET session_replication_role = 'origin';\n";
        }

        // Create temporary files
        $tempSqlFile = tempnam(sys_get_temp_dir(), 'backup_');
        file_put_contents($tempSqlFile, $sql);

        $zipFile = tempnam(sys_get_temp_dir(), 'backup_zip_');
        $zip = new \ZipArchive();
        
        $timestamp = now()->format('Y-m-d_H-i-s');
        $sqlFilename = 'villa_boutanga_backup_' . $timestamp . '.sql';
        $zipFilename = 'villa_boutanga_backup_' . $timestamp . '.zip';

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($tempSqlFile, $sqlFilename);
            $zip->close();
        }
        
        @unlink($tempSqlFile);

        return response()->download($zipFile, $zipFilename)->deleteFileAfterSend(true);
    }
}


