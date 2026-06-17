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
}
