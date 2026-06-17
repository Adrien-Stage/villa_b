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
    Route::post('/admin/tenants/{tenant}', [AdminAuditController::class, 'updateTenant'])->name('admin.tenants.update');
});

// Login
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// Logout
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Page d'accueil → redirige vers le dashboard si connecté
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// ===== PORTAIL CLIENT (QR MENU) =====
Route::prefix('portal')->name('portal.')->group(function () {
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

    // --- ASSISTANT IA (Kuété) ---
    Route::post('/ai-chat', [App\Http\Controllers\AiAssistantController::class, 'chat'])->name('ai.chat');

    // --- DISCUSSION INTERNE ---
    Route::prefix('discussions')->name('discussions.')->group(function () {
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
    Route::prefix('rooms')->name('rooms.')->middleware('role:manager,reception,housekeeping_leader')->group(function () {
        Route::get('/',                [RoomController::class, 'index'])->name('index');
        Route::post('/',               [RoomController::class, 'store'])->middleware('role:manager,reception')->name('store');
        Route::get('/{room}',          [RoomController::class, 'show'])->name('show');
        Route::put('/{room}',          [RoomController::class, 'update'])->middleware('role:manager,reception')->name('update');
        Route::delete('/{room}',       [RoomController::class, 'destroy'])->middleware('role:manager,reception')->name('destroy');
        Route::delete('/{room}/images/{image}', [RoomController::class, 'destroyImage'])->middleware('role:manager,reception')->name('images.destroy');

        // Types de chambres - seulement manager
        Route::post('/types/store',         [RoomController::class, 'storeType'])->middleware('role:manager,reception')->name('types.store');
        Route::put('/types/{roomType}',     [RoomController::class, 'updateType'])->middleware('role:manager,reception')->name('types.update');
        Route::delete('/types/{roomType}',  [RoomController::class, 'destroyType'])->middleware('role:manager,reception')->name('types.destroy');
    });
    Route::post('/rooms/{room}/status',  [RoomController::class, 'updateStatus'])
        ->middleware('role:manager,reception,housekeeping_leader,housekeeping_staff,housekeeping')
        ->name('rooms.updateStatus');

    // --- RÉSERVATIONS ---
    Route::prefix('bookings')->name('bookings.')->middleware('role:manager,reception')->group(function () {
        Route::get('/',                        [BookingController::class, 'index'])->name('index');
        Route::get('/create',                  [BookingController::class, 'create'])->name('create');
        Route::post('/',                       [BookingController::class, 'store'])->name('store');
        
        // Caisse Réception
        Route::get('/cash-register', [\App\Http\Controllers\Reception\CashRegisterController::class, 'index'])->name('cash_register.index');
        Route::get('/cash-register/open', [\App\Http\Controllers\Reception\CashRegisterController::class, 'showOpenForm'])->name('cash_register.open');
        Route::post('/cash-register/open', [\App\Http\Controllers\Reception\CashRegisterController::class, 'open'])->name('cash_register.open.store');
        Route::post('/cash-register/disbursements', [\App\Http\Controllers\Reception\CashRegisterController::class, 'storeDisbursement'])->name('cash_register.disbursements.store');
        Route::get('/cash-register/close', [\App\Http\Controllers\Reception\CashRegisterController::class, 'showCloseForm'])->middleware('role:manager')->name('cash_register.close');
        Route::post('/cash-register/close', [\App\Http\Controllers\Reception\CashRegisterController::class, 'close'])->middleware('role:manager')->name('cash_register.close.store');

        Route::get('/{booking}',               [BookingController::class, 'show'])->name('show');
        Route::get('/{booking}/edit',          [BookingController::class, 'edit'])->name('edit');
        Route::put('/{booking}',               [BookingController::class, 'update'])->name('update');
        Route::post('/{booking}/checkin',      [BookingController::class, 'checkIn'])->name('checkIn');
        Route::post('/{booking}/checkout',     [BookingController::class, 'checkOut'])->name('checkOut');
        Route::post('/{booking}/cancel',       [BookingController::class, 'cancel'])->name('cancel');
        Route::post('/{booking}/folio',        [BookingController::class, 'addFolioItem'])->middleware('role:reception,manager,restaurant_chief,cashier')->name('folio.add');
        Route::delete('/{booking}/folio/{folioItem}', [BookingController::class, 'removeFolioItem'])->middleware('role:reception,manager,restaurant_chief,cashier')->name('folio.remove');
        Route::post('/{booking}/payment', [BookingController::class, 'addPayment'])->middleware('role:reception,manager,cashier')->name('payment.add');
    });

    Route::prefix('groups')->name('groups.')->middleware('role:manager,reception')->group(function () {
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
    Route::prefix('customers')->name('customers.')->middleware('role:manager,reception,cashier')->group(function () {
        Route::get('/',               [CustomerController::class, 'index'])->name('index');
        Route::get('/create',         [CustomerController::class, 'create'])->middleware('role:reception,manager')->name('create');
        Route::post('/',              [CustomerController::class, 'store'])->middleware('role:reception,manager')->name('store');
        Route::get('/{customer}',     [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->middleware('role:reception,manager')->name('edit');
        Route::put('/{customer}',     [CustomerController::class, 'update'])->middleware('role:reception,manager')->name('update');
    });

    // --- HOUSEKEEPING ---
    Route::prefix('housekeeping')->name('housekeeping.')->middleware('role:housekeeping_leader,housekeeping_staff,housekeeping,manager')->group(function () {
        Route::get('/',                    [HousekeepingController::class, 'index'])->name('index');
        Route::post('/teams',              [HousekeepingController::class, 'storeTeam'])->middleware('role:housekeeping_leader,manager')->name('teams.store');
        Route::post('/assignments',        [HousekeepingController::class, 'assignRooms'])->middleware('role:housekeeping_leader,manager')->name('assignments.store');
        Route::post('/{room}/clean',       [HousekeepingController::class, 'markCleaning'])->name('clean');
        Route::post('/{room}/ready',       [HousekeepingController::class, 'markReady'])->name('ready');
        Route::post('/{room}/issue',       [HousekeepingController::class, 'reportIssue'])->name('issue');
    });

    // --- RESTAURANT (menus) ---
    // Lecture (manager peut consulter), Écriture réservée au staff restaurant
    Route::prefix('restaurant')->name('restaurant.')->middleware('role:manager,restaurant_chief,restaurant_staff')->group(function () {
        Route::get('/menus', [RestaurantMenuController::class, 'index'])->name('menus.index');
        Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::get('/pantry', [RestaurantPantryController::class, 'index'])->name('pantry.index');
    });

    // Écriture RESTAURANT — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware('role:restaurant_chief,restaurant_staff')->group(function () {
        Route::post('/orders', [RestaurantOrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/status', [RestaurantOrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');
        Route::post('/pantry/items/{item}/movements', [RestaurantPantryController::class, 'storeMovement'])->name('pantry.movements.store');

        Route::middleware('role:restaurant_chief')->group(function () {
            Route::post('/menus/categories', [RestaurantMenuController::class, 'storeCategory'])->name('menus.categories.store');
            Route::put('/menus/categories/{category}', [RestaurantMenuController::class, 'updateCategory'])->name('menus.categories.update');
            Route::delete('/menus/categories/{category}', [RestaurantMenuController::class, 'destroyCategory'])->name('menus.categories.destroy');

            Route::post('/menus/items', [RestaurantMenuController::class, 'storeItem'])->name('menus.items.store');
            Route::put('/menus/items/{item}', [RestaurantMenuController::class, 'updateItem'])->name('menus.items.update');
            Route::delete('/menus/items/{item}', [RestaurantMenuController::class, 'destroyItem'])->name('menus.items.destroy');

            Route::post('/pantry/categories', [RestaurantPantryController::class, 'storeCategory'])->name('pantry.categories.store');
            Route::put('/pantry/categories/{category}', [RestaurantPantryController::class, 'updateCategory'])->name('pantry.categories.update');
            Route::delete('/pantry/categories/{category}', [RestaurantPantryController::class, 'destroyCategory'])->name('pantry.categories.destroy');

            Route::post('/pantry/items', [RestaurantPantryController::class, 'storeItem'])->name('pantry.items.store');
            Route::put('/pantry/items/{item}', [RestaurantPantryController::class, 'updateItem'])->name('pantry.items.update');
            Route::delete('/pantry/items/{item}', [RestaurantPantryController::class, 'destroyItem'])->name('pantry.items.destroy');
        });
    });

    // --- RESTAURANT (facturation interne) ---
    // Lecture (manager peut consulter)
    Route::prefix('restaurant')->name('restaurant.')->middleware('role:manager,restaurant_chief,cashier')->group(function () {
        Route::get('/billing', [RestaurantBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{order}', [RestaurantBillingController::class, 'show'])->whereNumber('order')->name('billing.show');
        Route::get('/billing/{order}/receipt', [RestaurantBillingController::class, 'receipt'])->whereNumber('order')->name('billing.receipt');
    });

    // Écriture facturation — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware('role:restaurant_chief,cashier')->group(function () {
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

    // --- COMPTABILITÉ (futur module) ---
    Route::prefix('accounting')->name('accounting.')->middleware('role:accountant,manager,admin')->group(function () {
        // Ces routes seront implémentées quand le module comptable sera développé
    });

    // --- SHOP ---
    // Lecture seule pour le manager (GET uniquement)
    Route::prefix('shop')->name('shop.')->middleware('role:shop_manager,shop_cashier,manager')->group(function () {
        Route::get('/cash-register', [CashRegisterController::class, 'index'])->middleware('role:shop_manager,manager')->name('cash_register.index');
        Route::get('/products', [ShopProductController::class, 'index'])->middleware('role:shop_manager,manager')->name('products.index');
        Route::get('/orders', [ShopOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/receipt', [ShopOrderController::class, 'receipt'])->whereNumber('order')->name('orders.receipt');
        Route::get('/orders/{order}', [ShopOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    });

    // Écriture SHOP — manager totalement exclu
    Route::prefix('shop')->name('shop.')->middleware('role:shop_manager,shop_cashier')->group(function () {
        // Caisse — ouverture : shop_manager + shop_cashier
        Route::get('/cash-register/open', [CashRegisterController::class, 'showOpenForm'])->name('cash_register.open');
        Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->name('cash_register.open.store');
        Route::post('/cash-register/disbursements', [CashRegisterController::class, 'storeDisbursement'])->name('cash_register.disbursements.store');

        // Caisse — fermeture : shop_manager uniquement
        Route::middleware('role:shop_manager')->group(function () {
            Route::get('/cash-register/close', [CashRegisterController::class, 'showCloseForm'])->name('cash_register.close');
            Route::post('/cash-register/close', [CashRegisterController::class, 'close'])->name('cash_register.close.store');
        });

        // Commandes
        Route::get('/orders/create', [ShopOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [ShopOrderController::class, 'store'])->name('orders.store');
        Route::patch('/orders/{order}/paid', [ShopOrderController::class, 'markAsPaid'])->whereNumber('order')->name('orders.paid');
        Route::patch('/orders/{order}/refund', [ShopOrderController::class, 'refund'])->whereNumber('order')->name('orders.refund');

        // Articles — shop_manager uniquement
        Route::middleware('role:shop_manager')->group(function () {
            Route::get('/products/create', [ShopProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ShopProductController::class, 'store'])->name('products.store');
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
});

// ==========================================
// ANALYTICS (Manager uniquement)
// ==========================================
Route::middleware(['auth', 'role:manager'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
    Route::get('/print', [\App\Http\Controllers\AnalyticsController::class, 'print'])->name('print');
});
// Routes Breeze (login, register, etc.) — déjà générées, ne pas toucher
//require __DIR__.'/auth.php';
