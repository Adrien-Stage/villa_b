<?php

namespace App\Providers;

use App\Services\CheckOutService;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use App\Models\AuditLog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Instance unique par requête : le résolveur mémorise les surcharges
        // d'un utilisateur pour ne pas les relire à chaque route parcourue.
        $this->app->singleton(\App\Services\PermissionResolver::class);

        // Injection de dépendance automatique
        $this->app->bind(LoyaltyService::class);
        $this->app->bind(CheckOutService::class);
    }

    public function boot(): void
    {
        \Carbon\Carbon::setLocale('fr');

        // Canal de notification Web Push (notifications système hors app)
        \Illuminate\Support\Facades\Notification::extend('webpush', function ($app) {
            return new \App\Notifications\Channels\WebPushChannel();
        });

        // Directives Blade pour RBAC
        $this->registerBladeDirectives();
        $this->shareDiscussionUnreadState();

        // Événements d'authentification pour audit
        Event::listen(Login::class, function ($event) {
            AuditLog::record(
                $event->user->id,
                'login',
                'Connexion réussie de l\'utilisateur : ' . $event->user->name,
                'auth'
            );
            $event->user->update(['last_login_at' => now()]);
        });

        Event::listen(Logout::class, function ($event) {
            if ($event->user) {
                AuditLog::record(
                    $event->user->id,
                    'logout',
                    'Déconnexion de l\'utilisateur : ' . $event->user->name,
                    'auth'
                );
            }
        });

        Event::listen(Failed::class, function ($event) {
            AuditLog::record(
                null,
                'failed_login',
                'Tentative de connexion échouée pour l\'identifiant : ' . ($event->credentials['email'] ?? ($event->credentials['login'] ?? 'inconnu')),
                'auth',
                ['credentials' => array_keys($event->credentials)]
            );
        });
    }

    /**
     * Enregistrer les directives Blade personnalisées pour RBAC
     */
    private function registerBladeDirectives(): void
    {
        // @admin ... @endadmin
        Blade::directive('admin', function () {
            return '<?php if(auth()->check() && auth()->user()->isAdmin()): ?>';
        });
        Blade::directive('endadmin', function () {
            return '<?php endif; ?>';
        });

        // @role('manager') ... @endrole
        // @droit('economat.items.voir') ... @enddroit
        //
        // Pose à la vue exactement la question que pose la route. Les liens de
        // la barre latérale étaient gardés par la directive de rôle : un droit
        // accordé depuis
        // la matrice ouvrait la page sans jamais faire apparaître son entrée de
        // menu, et l'accès n'existait donc que pour qui connaissait l'URL.
        Blade::directive('droit', function ($expression) {
            return "<?php if(app(\\App\\Services\\PermissionResolver::class)->allows(auth()->user(), {$expression})): ?>";
        });
        Blade::directive('enddroit', function () {
            return '<?php endif; ?>';
        });

        // @undroit('a', 'b') ... @endundroit — au moins l'un des droits cités.
        // Un groupe de menu s'ouvre dès que l'une de ses entrées est permise.
        Blade::directive('undroit', function ($expression) {
            return "<?php \$__resolveur = app(\\App\\Services\\PermissionResolver::class);"
                 . " if(collect([{$expression}])->contains(fn (\$d) => \$__resolveur->allows(auth()->user(), \$d))): ?>";
        });
        Blade::directive('endundroit', function () {
            return '<?php endif; ?>';
        });

        Blade::directive('role', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->hasAnyRole([{$expression}])): ?>";
        });
        Blade::directive('endrole', function () {
            return '<?php endif; ?>';
        });

        // @hasnotRole('housekeeping') ... @endhasnotRole
        Blade::directive('hasnotRole', function ($expression) {
            return "<?php if(!auth()->check() || !auth()->user()->hasAnyRole([{$expression}])): ?>";
        });
        Blade::directive('endhasnotRole', function () {
            return '<?php endif; ?>';
        });

        // @module('restaurant') ... @endmodule
        // Vérifie que le module est activé pour l'établissement ET que l'utilisateur courant y a accès
        Blade::directive('module', function ($expression) {
            return "<?php if(\\App\\Support\\TenantModules::has({$expression}) && (!auth()->check() || auth()->user()->hasModuleAccess({$expression}))): ?>";
        });
        Blade::directive('endmodule', function () {
            return '<?php endif; ?>';
        });

        // @canAccessModule('restaurant') ... @endcanAccessModule
        Blade::directive('canAccessModule', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->hasModuleAccess({$expression})): ?>";
        });
        Blade::directive('endcanAccessModule', function () {
            return '<?php endif; ?>';
        });
    }

    private function shareDiscussionUnreadState(): void
    {
        View::composer('layouts.hotel', function ($view) {
            $hasUnreadDiscussions = false;
            $totalUnreadDiscussions = 0;

            if (auth()->check()
                && Schema::hasTable('discussion_conversation_user')
                && Schema::hasTable('discussion_messages')
                && Schema::hasColumn('discussion_conversation_user', 'last_read_at')
                && Schema::hasColumn('discussion_conversation_user', 'archived_at')
                && Schema::hasColumn('discussion_conversation_user', 'deleted_at')
                && Schema::hasColumn('discussion_messages', 'conversation_id')) {

                $userId = auth()->id();

                $totalUnreadDiscussions = DB::table('discussion_conversation_user as dcu')
                    ->join('discussion_messages as dm', 'dm.conversation_id', '=', 'dcu.discussion_conversation_id')
                    ->where('dcu.user_id', $userId)
                    ->whereNull('dcu.archived_at')
                    ->whereNull('dcu.deleted_at')
                    ->where('dm.user_id', '!=', $userId)
                    ->where(function ($query) {
                        $query->whereNull('dcu.last_read_at')
                            ->orWhereColumn('dm.created_at', '>', 'dcu.last_read_at');
                    })
                    ->count();

                $hasUnreadDiscussions = $totalUnreadDiscussions > 0;
            }

            $view->with('hasUnreadDiscussions', $hasUnreadDiscussions)
                ->with('totalUnreadDiscussions', $totalUnreadDiscussions);
        });
    }
}
