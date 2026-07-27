<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\GroupBookingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\RestaurantMenuController;
use App\Http\Controllers\RestaurantPortalController;
use App\Http\Controllers\RestaurantOrderController;
use App\Http\Controllers\RestaurantBillingController;
use App\Http\Controllers\RestaurantPantryController;
use App\Http\Controllers\ShopProductController;
use App\Http\Controllers\ShopOrderController;
use App\Http\Controllers\Shop\CashRegisterController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\NotificationController;

// ===== PWA (application installable) =====
// Publiques : le navigateur récupère le manifeste et les icônes avant même
// que l'utilisateur soit connecté, sinon l'installation est impossible.
Route::get('/manifest.webmanifest', [App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa/icon-{size}.png', [App\Http\Controllers\PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
Route::get('/offline', [App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');

// ===== AUTH ROUTES (Breeze) =====
use App\Http\Controllers\Auth\AuthenticatedSessionController;
// Admin login
Route::get('/admin', [AdminAuthenticatedSessionController::class, 'create'])->name('admin.login');
Route::post('/admin', [AdminAuthenticatedSessionController::class, 'store'])->name('admin.login.store');

// Admin Global Routes
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminAuditController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{user}/toggle-active', [AdminAuditController::class, 'toggleUserActive'])->name('admin.users.toggle-active');
    Route::post('/admin/users/{user}/reset-password', [AdminAuditController::class, 'forcePasswordReset'])->name('admin.users.reset-password');
    Route::get('/admin/tenants/create', [AdminAuditController::class, 'createTenant'])->name('admin.tenants.create');
    Route::post('/admin/tenants', [AdminAuditController::class, 'storeTenant'])->name('admin.tenants.store');
    Route::get('/admin/tenants/{tenant}', [AdminAuditController::class, 'showTenant'])->name('admin.tenants.show');
    Route::post('/admin/tenants/{tenant}', [AdminAuditController::class, 'updateTenant'])->name('admin.tenants.update');
    Route::get('/admin/export/supervision', [AdminAuditController::class, 'exportSupervision'])->name('admin.export.supervision');
    Route::get('/admin/export/backup', [AdminAuditController::class, 'exportBackup'])->name('admin.export.backup');
});

// Entrée en mode assistance depuis le PMS (jeton signé, pas d'auth préalable)
Route::get('/assistance/enter', [\App\Http\Controllers\AssistanceController::class, 'enter'])->name('assistance.enter');

// Login
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// Logout
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Page d'accueil → dashboard si connecté, sinon login directement
Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// ===== PORTAIL CLIENT (QR MENU) =====
Route::prefix('portal')->name('portal.')->middleware('module:restaurant')->group(function () {
    Route::get('/{tenant:slug}/restaurant', [RestaurantPortalController::class, 'menu'])->name('restaurant.menu');
    Route::post('/{tenant:slug}/restaurant/orders', [RestaurantPortalController::class, 'store'])->name('restaurant.store');
    Route::get('/{tenant:slug}/restaurant/orders/{order}', [RestaurantPortalController::class, 'order'])->whereNumber('order')->name('restaurant.order');
});

// Toutes les routes de l'app nécessitent d'être connecté et vérifié
Route::middleware(['auth', 'verified'])->group(function () {

    // --- DASHBOARD ---
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // --- PARAMETRES ---
    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index'])
        ->name('settings.index')
        ->middleware('role:manager,reception,housekeeping_leader,restaurant_chief,shop_manager');
    Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update'])
        ->name('settings.update')
        ->middleware('role:manager,reception,housekeeping_leader,restaurant_chief,shop_manager');

    // Catalogue des prestations (onglet "Prestations" des paramètres)
    Route::middleware('role:manager')->group(function () {
        Route::post('/settings/services', [App\Http\Controllers\ServiceCatalogController::class, 'store'])
            ->name('settings.services.store');
        Route::put('/settings/services/{serviceItem}', [App\Http\Controllers\ServiceCatalogController::class, 'update'])
            ->name('settings.services.update');
        Route::delete('/settings/services/{serviceItem}', [App\Http\Controllers\ServiceCatalogController::class, 'destroy'])
            ->name('settings.services.destroy');

        // Organisations partenaires (onglet "Partenaires" des paramètres).
        // Réservé au manager : ces conventions engagent des remises.
        Route::post('/settings/partners', [App\Http\Controllers\PartnerOrganizationController::class, 'store'])
            ->name('settings.partners.store');
        Route::put('/settings/partners/{partnerOrganization}', [App\Http\Controllers\PartnerOrganizationController::class, 'update'])
            ->name('settings.partners.update');
        Route::delete('/settings/partners/{partnerOrganization}', [App\Http\Controllers\PartnerOrganizationController::class, 'destroy'])
            ->name('settings.partners.destroy');

        // Packs d'hébergement (onglet "Hébergement" des paramètres).
        Route::post('/settings/packages', [App\Http\Controllers\RoomPackageController::class, 'store'])
            ->name('settings.packages.store');
        Route::put('/settings/packages/{roomPackage}', [App\Http\Controllers\RoomPackageController::class, 'update'])
            ->name('settings.packages.update');
        Route::delete('/settings/packages/{roomPackage}', [App\Http\Controllers\RoomPackageController::class, 'destroy'])
            ->name('settings.packages.destroy');
    });

    // --- ASSISTANT IA (Kuété) ---
    Route::post('/ai-chat', [App\Http\Controllers\AiAssistantController::class, 'chat'])->name('ai.chat');

    // --- REPRISE DE CAISSE (HEBERGEMENT & BOUTIQUE) ---
    Route::post('/cash-register/resume', [\App\Http\Controllers\Reception\CashRegisterController::class, 'resume'])->name('cash_register.resume');

    // --- NOTIFICATIONS INTERNES ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    // Abonnements Web Push (notifications système)
    Route::prefix('push')->name('push.')->group(function () {
        Route::get('/vapid-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidKey'])->name('vapid');
        Route::post('/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('subscribe');
        Route::post('/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('unsubscribe');
    });

    // --- DISCUSSION INTERNE ---
    Route::prefix('discussions')->name('discussions.')->middleware('module:discussions')->group(function () {
        Route::get('/', [DiscussionController::class, 'index'])->name('index');
        Route::get('/conversations/list', [DiscussionController::class, 'conversationsList'])->name('conversations.list');
        Route::get('/unread-summary', [DiscussionController::class, 'unreadSummary'])->name('unreadSummary');
        Route::post('/conversations', [DiscussionController::class, 'createConversation'])->name('conversations.store');
        Route::get('/conversations/{conversation}/poll', [DiscussionController::class, 'poll'])->name('conversations.poll');
        Route::post('/conversations/{conversation}/archive', [DiscussionController::class, 'archiveConversation'])->name('conversations.archive');
        Route::delete('/conversations/{conversation}', [DiscussionController::class, 'destroyConversation'])->name('conversations.destroy');
        Route::post('/', [DiscussionController::class, 'store'])->name('store');
    });

    // --- PROFIL ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // --- CHAMBRES ---
    // Chambres : géré par manager/réception. Le housekeeping change les statuts
    // depuis son propre module, plus depuis cette rubrique.
    Route::prefix('rooms')->name('rooms.')->middleware(['role:manager,reception', 'module.access:hebergement'])->group(function () {
        Route::get('/',                [RoomController::class, 'index'])->name('index');
        Route::post('/',               [RoomController::class, 'store'])->middleware('role:manager,reception')->name('store');

        // Import / export CSV — déclarés avant /{room} pour ne pas être
        // capturés par le binding de modèle (sinon "export" = id de chambre)
        Route::get('/export',         [\App\Http\Controllers\RoomCsvController::class, 'exportRooms'])->middleware('role:manager,reception')->name('export');
        Route::post('/import',        [\App\Http\Controllers\RoomCsvController::class, 'importRooms'])->middleware('role:manager,reception')->name('import');
        Route::get('/types/export',   [\App\Http\Controllers\RoomCsvController::class, 'exportTypes'])->middleware('role:manager,reception')->name('types.export');
        Route::post('/types/import',  [\App\Http\Controllers\RoomCsvController::class, 'importTypes'])->middleware('role:manager,reception')->name('types.import');

        Route::get('/{room}',          [RoomController::class, 'show'])->name('show');
        Route::put('/{room}',          [RoomController::class, 'update'])->middleware('role:manager,reception')->name('update');
        Route::delete('/{room}',       [RoomController::class, 'destroy'])->middleware('role:manager,reception')->name('destroy');
        Route::delete('/{room}/images/{image}', [RoomController::class, 'destroyImage'])->middleware('role:manager,reception')->name('images.destroy');

        // Types de chambres - seulement manager
        Route::post('/types/store',         [RoomController::class, 'storeType'])->middleware('role:manager,reception')->name('types.store');
        Route::put('/types/{roomType}',     [RoomController::class, 'updateType'])->middleware('role:manager,reception')->name('types.update');
        Route::delete('/types/{roomType}',  [RoomController::class, 'destroyType'])->middleware('role:manager,reception')->name('types.destroy');
    });

    // --- FICHES TECHNIQUES DES CHAMBRES (marge sur une chambre louée) ---
    // Donnée de gestion : réservée au manager et au comptable. Préfixe hors du
    // groupe « rooms » pour ne pas heurter sa route rooms/{room}.
    Route::prefix('hebergement/fiches-techniques')->name('rooms.cost_sheets.')->middleware('role:manager,accountant')->group(function () {
        $c = App\Http\Controllers\RoomCostSheetController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/{roomType}', [$c, 'show'])->whereNumber('roomType')->name('show');
        Route::put('/{roomType}/hypotheses', [$c, 'updateAssumptions'])->whereNumber('roomType')->name('assumptions');
        Route::post('/{roomType}/demarrage-rapide', [$c, 'applyStarter'])->whereNumber('roomType')->name('starter');
        Route::post('/{roomType}/postes', [$c, 'storeItem'])->whereNumber('roomType')->name('items.store');
        Route::put('/{roomType}/postes/{item}', [$c, 'updateItem'])->whereNumber('roomType')->whereNumber('item')->name('items.update');
        Route::delete('/{roomType}/postes/{item}', [$c, 'destroyItem'])->whereNumber('roomType')->whereNumber('item')->name('items.destroy');
    });
    Route::post('/rooms/{room}/status',  [RoomController::class, 'updateStatus'])
        ->middleware('role:manager,reception')
        ->name('rooms.updateStatus');

    // --- RÉSERVATIONS ---
    Route::prefix('bookings')->name('bookings.')->middleware(['role:manager,reception', 'module.access:hebergement'])->group(function () {
        Route::get('/',                        [BookingController::class, 'index'])->name('index');
        Route::get('/create',                  [BookingController::class, 'create'])->name('create');
        Route::post('/',                       [BookingController::class, 'store'])->name('store');
        
        // Caisse Réception
        Route::get('/cash-register', [\App\Http\Controllers\Reception\CashRegisterController::class, 'index'])->name('cash_register.index');
        Route::get('/cash-register/open', [\App\Http\Controllers\Reception\CashRegisterController::class, 'showOpenForm'])->name('cash_register.open');
        Route::post('/cash-register/open', [\App\Http\Controllers\Reception\CashRegisterController::class, 'open'])->name('cash_register.open.store');
        Route::post('/cash-register/disbursements', [\App\Http\Controllers\Reception\CashRegisterController::class, 'storeDisbursement'])->name('cash_register.disbursements.store');
        // Fermeture : celui qui a ouvert la caisse la ferme (le contrôleur
        // scope la session à auth()->id()) — pas de restriction de rôle.
        Route::get('/cash-register/close', [\App\Http\Controllers\Reception\CashRegisterController::class, 'showCloseForm'])->name('cash_register.close');
        Route::post('/cash-register/close', [\App\Http\Controllers\Reception\CashRegisterController::class, 'close'])->name('cash_register.close.store');

        Route::get('/{booking}',               [BookingController::class, 'show'])->name('show');
        Route::get('/{booking}/edit',          [BookingController::class, 'edit'])->name('edit');

        // Actions métier : impossibles tant que la caisse n'est pas ouverte
        Route::middleware('caisse')->group(function () {
            Route::put('/{booking}',               [BookingController::class, 'update'])->name('update');
            Route::post('/{booking}/checkin',      [BookingController::class, 'checkIn'])->name('checkIn');
            Route::post('/{booking}/checkout',     [BookingController::class, 'checkOut'])->name('checkOut');
            Route::post('/{booking}/approve',      [BookingController::class, 'approve'])->name('approve');
            Route::post('/{booking}/confirm',      [BookingController::class, 'confirm'])->name('confirm');
            Route::post('/{booking}/cancel',       [BookingController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/folio',        [BookingController::class, 'addFolioItem'])->middleware('role:reception,manager,restaurant_chief,cashier')->name('folio.add');
            Route::delete('/{booking}/folio/{folioItem}', [BookingController::class, 'removeFolioItem'])->middleware('role:reception,manager,restaurant_chief,cashier')->name('folio.remove');
            Route::post('/{booking}/payment', [BookingController::class, 'addPayment'])->middleware('role:reception,manager,cashier')->name('payment.add');
        });
    });

    Route::prefix('groups')->name('groups.')->middleware(['role:manager,reception', 'module.access:hebergement'])->group(function () {
        Route::get('/',                          [GroupBookingController::class, 'index'])->name('index');
        Route::get('/create',                    [GroupBookingController::class, 'create'])->middleware('role:manager')->name('create');
        Route::post('/',                         [GroupBookingController::class, 'store'])->middleware('role:manager')->name('store');
        Route::get('/{groupBooking}',            [GroupBookingController::class, 'show'])->name('show');
        Route::post('/{groupBooking}/room',      [GroupBookingController::class, 'addRoom'])->middleware('role:manager')->name('addRoom');
        Route::delete('/{groupBooking}/room/{booking}', [GroupBookingController::class, 'removeRoom'])->middleware('role:manager')->name('removeRoom');
        Route::post('/{groupBooking}/checkin',   [GroupBookingController::class, 'checkInAll'])->name('checkInAll');
        Route::post('/{groupBooking}/checkout',  [GroupBookingController::class, 'checkOutAll'])->name('checkOutAll');
        Route::post('/{groupBooking}/folio', [GroupBookingController::class, 'addGroupFolioItem'])->name('folio.add');
        Route::post('/{groupBooking}/payment', [GroupBookingController::class, 'addGroupPayment'])->middleware('role:manager,reception,cashier')->name('payment.add');
        Route::get('/{groupBooking}/invoice', [GroupBookingController::class, 'invoice'])->middleware('role:cashier,manager,reception')->name('invoice');
        Route::get('/{groupBooking}/edit',   [GroupBookingController::class, 'edit'])->middleware('role:manager')->name('edit');
        Route::put('/{groupBooking}',        [GroupBookingController::class, 'update'])->middleware('role:manager')->name('update');
        Route::post('/{groupBooking}/cancel', [GroupBookingController::class, 'cancel'])->middleware('role:manager')->name('cancel');
    });

    // --- CLIENTS ---
    Route::prefix('customers')->name('customers.')->middleware(['role:manager,reception,cashier', 'module.access:hebergement'])->group(function () {
        Route::get('/',               [CustomerController::class, 'index'])->name('index');
        // Import / export CSV — déclarés avant /{customer} pour ne pas être capturés par le binding.
        Route::get('/export',         [App\Http\Controllers\CustomerCsvController::class, 'export'])->middleware('role:reception,manager')->name('export');
        Route::post('/import',        [App\Http\Controllers\CustomerCsvController::class, 'import'])->middleware('role:reception,manager')->name('import');
        Route::get('/create',         [CustomerController::class, 'create'])->middleware('role:reception,manager')->name('create');
        Route::post('/',              [CustomerController::class, 'store'])->middleware('role:reception,manager')->name('store');
        Route::get('/{customer}',     [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->middleware('role:reception,manager')->name('edit');
        Route::put('/{customer}',     [CustomerController::class, 'update'])->middleware('role:reception,manager')->name('update');
    });

    // --- HOUSEKEEPING ---
    Route::prefix('housekeeping')->name('housekeeping.')->middleware(['role:housekeeping_leader,housekeeping_staff,housekeeping,manager', 'module:housekeeping', 'module.access:housekeeping'])->group(function () {
        Route::get('/',                    [HousekeepingController::class, 'index'])->name('index');
        Route::post('/teams',              [HousekeepingController::class, 'storeTeam'])->middleware('role:housekeeping_leader,manager')->name('teams.store');
        Route::post('/assignments',        [HousekeepingController::class, 'assignRooms'])->middleware('role:housekeeping_leader,manager')->name('assignments.store');
        Route::post('/{room}/clean',       [HousekeepingController::class, 'markCleaning'])->name('clean');
        Route::post('/{room}/ready',       [HousekeepingController::class, 'markReady'])->name('ready');
        Route::post('/{room}/inspect',     [HousekeepingController::class, 'markInspected'])->name('inspect');
        Route::post('/{room}/available',   [HousekeepingController::class, 'markAvailable'])->name('available');
        Route::post('/{room}/reject',      [HousekeepingController::class, 'rejectCleaning'])->name('reject');
        Route::post('/{room}/issue',       [HousekeepingController::class, 'reportIssue'])->name('issue');
    });

    // --- RESTAURANT (menus) ---
    // Lecture (manager peut consulter), Écriture réservée au staff restaurant
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:manager,restaurant_chief,restaurant_staff,restaurant_cook', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::get('/menus', [RestaurantMenuController::class, 'index'])->name('menus.index');
        Route::get('/menus-export', [App\Http\Controllers\RestaurantCsvController::class, 'exportMenus'])->name('menus.export');
        Route::get('/pantry-export', [App\Http\Controllers\RestaurantCsvController::class, 'exportPantry'])->name('pantry.export');
        Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::get('/kitchen', [App\Http\Controllers\RestaurantKitchenController::class, 'index'])->name('kitchen.index');
        Route::get('/pantry', [RestaurantPantryController::class, 'index'])->name('pantry.index');
        Route::get('/recipes', [App\Http\Controllers\RestaurantRecipeController::class, 'index'])->name('recipes.index');
        Route::get('/stock-counts', [App\Http\Controllers\RestaurantStockCountController::class, 'index'])->name('stock_counts.index');
        Route::get('/stock-counts/{stockCount}', [App\Http\Controllers\RestaurantStockCountController::class, 'show'])->whereNumber('stockCount')->name('stock_counts.show');
    });

    // Cuisine : réception des bons et signalement des plats prêts (cuisinier + chef)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:restaurant_cook,restaurant_chief', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::post('/orders/{order}/preparing', [RestaurantOrderController::class, 'markPreparing'])->whereNumber('order')->name('orders.preparing');
        Route::post('/orders/{order}/ready', [RestaurantOrderController::class, 'markReady'])->whereNumber('order')->name('orders.ready');
    });

    // Salle : prise de service, transmission en cuisine, service (serveur + chef)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:restaurant_staff,restaurant_chief', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::post('/shifts/open', [App\Http\Controllers\RestaurantShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shifts/close', [App\Http\Controllers\RestaurantShiftController::class, 'close'])->name('shifts.close');

        Route::post('/orders/{order}/send-to-kitchen', [RestaurantOrderController::class, 'sendToKitchen'])->whereNumber('order')->name('orders.send_to_kitchen');
        Route::post('/orders/{order}/served', [RestaurantOrderController::class, 'markServed'])->whereNumber('order')->name('orders.served');
        Route::post('/orders/{order}/claim', [RestaurantOrderController::class, 'claim'])->whereNumber('order')->name('orders.claim');
        Route::post('/orders/{order}/reassign', [RestaurantOrderController::class, 'reassign'])->whereNumber('order')->name('orders.reassign');
    });

    // Écriture RESTAURANT — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:restaurant_chief,restaurant_staff', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::post('/orders', [RestaurantOrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/status', [RestaurantOrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');
        Route::post('/pantry/items/{item}/movements', [RestaurantPantryController::class, 'storeMovement'])->name('pantry.movements.store');

        Route::middleware('role:restaurant_chief')->group(function () {
            Route::post('/menus/categories', [RestaurantMenuController::class, 'storeCategory'])->name('menus.categories.store');
            Route::put('/menus/categories/{category}', [RestaurantMenuController::class, 'updateCategory'])->name('menus.categories.update');
            Route::delete('/menus/categories/{category}', [RestaurantMenuController::class, 'destroyCategory'])->name('menus.categories.destroy');

            Route::post('/menus-import', [App\Http\Controllers\RestaurantCsvController::class, 'importMenus'])->name('menus.import');
            Route::post('/pantry-import', [App\Http\Controllers\RestaurantCsvController::class, 'importPantry'])->name('pantry.import');
            Route::post('/menus/items', [RestaurantMenuController::class, 'storeItem'])->name('menus.items.store');
            Route::put('/menus/items/{item}', [RestaurantMenuController::class, 'updateItem'])->name('menus.items.update');
            Route::delete('/menus/items/{item}', [RestaurantMenuController::class, 'destroyItem'])->name('menus.items.destroy');

            Route::post('/pantry/categories', [RestaurantPantryController::class, 'storeCategory'])->name('pantry.categories.store');
            Route::put('/pantry/categories/{category}', [RestaurantPantryController::class, 'updateCategory'])->name('pantry.categories.update');
            Route::delete('/pantry/categories/{category}', [RestaurantPantryController::class, 'destroyCategory'])->name('pantry.categories.destroy');

            Route::post('/pantry/items', [RestaurantPantryController::class, 'storeItem'])->name('pantry.items.store');
            Route::put('/pantry/items/{item}', [RestaurantPantryController::class, 'updateItem'])->name('pantry.items.update');
            Route::delete('/pantry/items/{item}', [RestaurantPantryController::class, 'destroyItem'])->name('pantry.items.destroy');

            // Réception de marchandise : saisie en unités d'achat, valorisée.
            Route::post('/pantry/items/{item}/receive', [RestaurantPantryController::class, 'receive'])->name('pantry.items.receive');

            // Fiches techniques
            Route::post('/recipes', [App\Http\Controllers\RestaurantRecipeController::class, 'store'])->name('recipes.store');
            Route::put('/recipes/{recipe}', [App\Http\Controllers\RestaurantRecipeController::class, 'update'])->name('recipes.update');
            Route::delete('/recipes/{recipe}', [App\Http\Controllers\RestaurantRecipeController::class, 'destroy'])->name('recipes.destroy');
            Route::post('/recipes/{recipe}/produce', [App\Http\Controllers\RestaurantRecipeController::class, 'produce'])->name('recipes.produce');

            // Inventaire physique
            Route::post('/stock-counts', [App\Http\Controllers\RestaurantStockCountController::class, 'store'])->name('stock_counts.store');
            Route::put('/stock-counts/{stockCount}', [App\Http\Controllers\RestaurantStockCountController::class, 'update'])->name('stock_counts.update');
            Route::post('/stock-counts/{stockCount}/close', [App\Http\Controllers\RestaurantStockCountController::class, 'close'])->name('stock_counts.close');
            Route::delete('/stock-counts/{stockCount}', [App\Http\Controllers\RestaurantStockCountController::class, 'destroy'])->name('stock_counts.destroy');
        });
    });

    // --- RESTAURANT (facturation interne) ---
    // Lecture (manager peut consulter)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:manager,restaurant_chief,cashier', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::get('/billing', [RestaurantBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{order}', [RestaurantBillingController::class, 'show'])->whereNumber('order')->name('billing.show');
        Route::get('/billing/{order}/receipt', [RestaurantBillingController::class, 'receipt'])->whereNumber('order')->name('billing.receipt');
    });

    // Écriture facturation — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware(['role:restaurant_chief,cashier', 'module:restaurant', 'module.access:restaurant'])->group(function () {
        Route::post('/billing/{order}/paid', [RestaurantBillingController::class, 'markPaid'])->whereNumber('order')->name('billing.paid');
        Route::post('/billing/{order}/unpaid', [RestaurantBillingController::class, 'markUnpaid'])->whereNumber('order')->name('billing.unpaid');
    });

    Route::prefix('invoices')->name('invoices.')->middleware('role:manager,reception,cashier')->group(function () {
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    });

    // --- UTILISATEURS (staff) ---
    Route::prefix('users')->name('users.')->middleware('role:manager')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggleStatus');
    });

    // --- COMPTABILITÉ (comptabilité de caisse : hébergement + resto + boutique) ---
    Route::prefix('accounting')->name('accounting.')->middleware('role:accountant,manager,admin')->group(function () {
        $c = App\Http\Controllers\AccountingController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/journal', [$c, 'journal'])->name('journal');
        Route::get('/compte-de-resultat', [$c, 'incomeStatement'])->name('income_statement');
        Route::get('/creances', [$c, 'receivables'])->name('receivables');
        Route::get('/caisse', [$c, 'cash'])->name('cash');

        Route::get('/depenses', [$c, 'expenses'])->name('expenses');
        Route::post('/depenses', [$c, 'storeExpense'])->name('expenses.store');
        Route::put('/depenses/{expense}', [$c, 'updateExpense'])->name('expenses.update');
        Route::delete('/depenses/{expense}', [$c, 'destroyExpense'])->name('expenses.destroy');
    });

    // --- ÉCONOMAT / INVENTAIRE ---
    Route::prefix('economat')->name('economat.')->group(function () {
        $eco = 'App\\Http\\Controllers\\Economat\\';

        // Gestion du magasin : réservée à l'économe (et manager/admin).
        Route::middleware(['role:econome,manager,admin', 'module.access:economat'])->group(function () use ($eco) {
            Route::get('/', [$eco . 'EconomatController', 'index'])->name('index');

            // Articles
            Route::get('/articles', [$eco . 'StockItemController', 'index'])->name('items.index');
            Route::post('/articles', [$eco . 'StockItemController', 'store'])->name('items.store');
            // Import / export CSV — avant /articles/{item} (contrainte numérique, pas de collision).
            Route::get('/articles-export', [App\Http\Controllers\StockItemCsvController::class, 'export'])->name('items.export');
            Route::post('/articles-import', [App\Http\Controllers\StockItemCsvController::class, 'import'])->name('items.import');
            Route::get('/articles/{item}', [$eco . 'StockItemController', 'show'])->whereNumber('item')->name('items.show');
            Route::put('/articles/{item}', [$eco . 'StockItemController', 'update'])->whereNumber('item')->name('items.update');
            Route::post('/articles/{item}/ajustement', [$eco . 'StockItemController', 'adjust'])->whereNumber('item')->name('items.adjust');
            Route::delete('/articles/{item}', [$eco . 'StockItemController', 'destroy'])->whereNumber('item')->name('items.destroy');

            // Fournisseurs
            Route::get('/fournisseurs', [$eco . 'SupplierController', 'index'])->name('suppliers.index');
            Route::post('/fournisseurs', [$eco . 'SupplierController', 'store'])->name('suppliers.store');
            Route::put('/fournisseurs/{supplier}', [$eco . 'SupplierController', 'update'])->name('suppliers.update');
            Route::delete('/fournisseurs/{supplier}', [$eco . 'SupplierController', 'destroy'])->name('suppliers.destroy');

            // Bons de commande
            Route::get('/bons', [$eco . 'PurchaseOrderController', 'index'])->name('orders.index');
            Route::get('/bons/nouveau', [$eco . 'PurchaseOrderController', 'create'])->name('orders.create');
            Route::post('/bons', [$eco . 'PurchaseOrderController', 'store'])->name('orders.store');
            Route::get('/bons/{order}', [$eco . 'PurchaseOrderController', 'show'])->whereNumber('order')->name('orders.show');
            Route::post('/bons/{order}/envoyer', [$eco . 'PurchaseOrderController', 'send'])->whereNumber('order')->name('orders.send');
            Route::post('/bons/{order}/reception', [$eco . 'PurchaseOrderController', 'receive'])->whereNumber('order')->name('orders.receive');
            Route::post('/bons/{order}/annuler', [$eco . 'PurchaseOrderController', 'cancel'])->whereNumber('order')->name('orders.cancel');

            // Traitement des demandes (validation / livraison)
            Route::post('/demandes/{requisition}/valider', [$eco . 'StockRequisitionController', 'approve'])->whereNumber('requisition')->name('requisitions.approve');
            Route::post('/demandes/{requisition}/refuser', [$eco . 'StockRequisitionController', 'reject'])->whereNumber('requisition')->name('requisitions.reject');
            Route::post('/demandes/{requisition}/livrer', [$eco . 'StockRequisitionController', 'deliver'])->whereNumber('requisition')->name('requisitions.deliver');
        });

        // Demandes : ouvertes aussi aux responsables de département qui
        // sollicitent l'économat. Le contrôleur cloisonne à leurs propres demandes.
        Route::middleware('role:econome,manager,admin,reception,housekeeping_leader,restaurant_chief,shop_manager')->group(function () use ($eco) {
            Route::get('/demandes', [$eco . 'StockRequisitionController', 'index'])->name('requisitions.index');
            Route::get('/demandes/nouvelle', [$eco . 'StockRequisitionController', 'create'])->name('requisitions.create');
            Route::post('/demandes', [$eco . 'StockRequisitionController', 'store'])->name('requisitions.store');
            Route::get('/demandes/{requisition}', [$eco . 'StockRequisitionController', 'show'])->whereNumber('requisition')->name('requisitions.show');
            Route::post('/demandes/{requisition}/annuler', [$eco . 'StockRequisitionController', 'cancel'])->whereNumber('requisition')->name('requisitions.cancel');
        });
    });

    // --- SHOP ---
    // Lecture seule pour le manager (GET uniquement)
    Route::prefix('shop')->name('shop.')->middleware(['role:shop_manager,shop_cashier,manager', 'module:shop', 'module.access:boutique'])->group(function () {
        Route::get('/cash-register', [CashRegisterController::class, 'index'])->middleware('role:shop_manager,manager')->name('cash_register.index');
        Route::get('/products', [ShopProductController::class, 'index'])->middleware('role:shop_manager,manager')->name('products.index');
        Route::get('/products-export', [App\Http\Controllers\ShopProductCsvController::class, 'export'])->middleware('role:shop_manager,manager')->name('products.export');
        Route::get('/orders', [ShopOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/receipt', [ShopOrderController::class, 'receipt'])->whereNumber('order')->name('orders.receipt');
        Route::get('/orders/{order}', [ShopOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    });

    // Écriture SHOP — manager totalement exclu
    Route::prefix('shop')->name('shop.')->middleware(['role:shop_manager,shop_cashier', 'module:shop', 'module.access:boutique'])->group(function () {
        // Caisse — ouverture : shop_manager + shop_cashier
        Route::get('/cash-register/open', [CashRegisterController::class, 'showOpenForm'])->name('cash_register.open');
        Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->name('cash_register.open.store');
        Route::post('/cash-register/disbursements', [CashRegisterController::class, 'storeDisbursement'])->name('cash_register.disbursements.store');

        // Caisse — fermeture : celui qui a ouvert la caisse la ferme (le
        // contrôleur scope la session à auth()->id()) — pas de restriction
        // supplémentaire au-delà du groupe (shop_manager + shop_cashier).
        Route::get('/cash-register/close', [CashRegisterController::class, 'showCloseForm'])->name('cash_register.close');
        Route::post('/cash-register/close', [CashRegisterController::class, 'close'])->name('cash_register.close.store');

        // Commandes
        Route::get('/orders/create', [ShopOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [ShopOrderController::class, 'store'])->name('orders.store');
        Route::patch('/orders/{order}/paid', [ShopOrderController::class, 'markAsPaid'])->whereNumber('order')->name('orders.paid');
        Route::patch('/orders/{order}/refund', [ShopOrderController::class, 'refund'])->whereNumber('order')->name('orders.refund');

        // Articles — shop_manager uniquement
        Route::middleware('role:shop_manager')->group(function () {
            Route::get('/products/create', [ShopProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ShopProductController::class, 'store'])->name('products.store');
            Route::post('/products-import', [App\Http\Controllers\ShopProductCsvController::class, 'import'])->name('products.import');
            Route::get('/products/{product}/edit', [ShopProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
            Route::patch('/products/{product}', [ShopProductController::class, 'update'])->whereNumber('product')->name('products.update');
            Route::delete('/products/{product}', [ShopProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
        });
    });
});

// ===== ROUTE DE TEST POUR POPUP =====
Route::middleware(['auth'])->group(function () {
    Route::get('/test-popup', function () {
        return response()->json(['access_denied' => true, 'message' => 'Ceci est un test du popup d\'accès refusé']);
    })->middleware('role:admin')->name('test-popup');

    // Statut de la liaison avec le site vitrine (badge du header)
    Route::get('/site-sync/status', [\App\Http\Controllers\SiteSyncController::class, 'status'])->name('site-sync.status');
});

// ==========================================
// ANALYTICS (Manager uniquement)
// ==========================================
Route::middleware(['auth', 'role:manager', 'module:analytics'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
    Route::get('/print', [\App\Http\Controllers\AnalyticsController::class, 'print'])->name('print');
});
// Routes Breeze (login, register, etc.) — déjà générées, ne pas toucher
//require __DIR__.'/auth.php';
