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
use App\Http\Controllers\NotificationController;

// ===== PWA (application installable) =====
// Publiques : le navigateur récupère le manifeste et les icônes avant même
// que l'utilisateur soit connecté, sinon l'installation est impossible.
Route::get('/manifest.webmanifest', [App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
// Sans extension « .png » : une URL se terminant par .png serait interceptée
// par la règle nginx des fichiers statiques (try_files $uri =404) et n'arriverait
// jamais jusqu'à Laravel, l'icône étant générée à la volée. Le type est de toute
// façon annoncé par l'en-tête Content-Type et par le champ « type » du manifeste.
Route::get('/pwa/icon/{size}', [App\Http\Controllers\PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
// Chemin historique demandé d'office par les navigateurs, les marque-pages et
// les aperçus de lien — et référencé comme icône par les notifications push.
// Il servait un fichier vide de 0 octet hérité du squelette Laravel.
Route::get('/favicon.ico', [App\Http\Controllers\PwaController::class, 'favicon'])->name('pwa.favicon');
Route::get('/offline', [App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');

// ===== AUTH ROUTES (Breeze) =====
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// L'ancienne console « admin global » (/admin) est retirée : reste de
// l'époque multi-établissements, elle gérait des établissements depuis
// l'intérieur de l'un d'eux. L'administrateur — le service informatique de
// l'hôtel — se connecte comme tout le monde ; ses écrans (journal d'audit,
// rôles et droits, interventions) vivent dans l'application.

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
        ->middleware('permission');
    Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update'])
        ->name('settings.update')
        ->middleware('permission');
    Route::get('/settings/export/{tab}', [App\Http\Controllers\SettingsCsvController::class, 'exportSettings'])
        ->name('settings.export')
        ->middleware('permission');
    Route::post('/settings/import/{tab}', [App\Http\Controllers\SettingsCsvController::class, 'importSettings'])
        ->name('settings.import')
        ->middleware('permission');

    // Catalogue des prestations (onglet "Prestations" des paramètres)
    Route::middleware('permission')->group(function () {
        Route::post('/settings/services', [App\Http\Controllers\ServiceCatalogController::class, 'store'])
            ->name('settings.services.store');
        Route::put('/settings/services/{serviceItem}', [App\Http\Controllers\ServiceCatalogController::class, 'update'])
            ->name('settings.services.update');
        Route::delete('/settings/services/{serviceItem}', [App\Http\Controllers\ServiceCatalogController::class, 'destroy'])
            ->name('settings.services.destroy');

        Route::get('/settings/services-export', [App\Http\Controllers\SettingsCsvController::class, 'exportServices'])
            ->name('settings.services.export');
        Route::post('/settings/services-import', [App\Http\Controllers\SettingsCsvController::class, 'importServices'])
            ->name('settings.services.import');

        // Organisations partenaires (onglet "Partenaires" des paramètres).
        // Réservé au manager : ces conventions engagent des remises.
        Route::post('/settings/partners', [App\Http\Controllers\PartnerOrganizationController::class, 'store'])
            ->name('settings.partners.store');
        Route::put('/settings/partners/{partnerOrganization}', [App\Http\Controllers\PartnerOrganizationController::class, 'update'])
            ->name('settings.partners.update');
        Route::delete('/settings/partners/{partnerOrganization}', [App\Http\Controllers\PartnerOrganizationController::class, 'destroy'])
            ->name('settings.partners.destroy');
        Route::get('/settings/partners-export', [App\Http\Controllers\SettingsCsvController::class, 'exportPartners'])
            ->name('settings.partners.export');
        Route::post('/settings/partners-import', [App\Http\Controllers\SettingsCsvController::class, 'importPartners'])
            ->name('settings.partners.import');

        // Packs d'hébergement (onglet "Hébergement" des paramètres).
        Route::post('/settings/packages', [App\Http\Controllers\RoomPackageController::class, 'store'])
            ->name('settings.packages.store');
        Route::put('/settings/packages/{roomPackage}', [App\Http\Controllers\RoomPackageController::class, 'update'])
            ->name('settings.packages.update');
        Route::delete('/settings/packages/{roomPackage}', [App\Http\Controllers\RoomPackageController::class, 'destroy'])
            ->name('settings.packages.destroy');
        Route::get('/settings/packages-export', [App\Http\Controllers\SettingsCsvController::class, 'exportPackages'])
            ->name('settings.packages.export');
        Route::post('/settings/packages-import', [App\Http\Controllers\SettingsCsvController::class, 'importPackages'])
            ->name('settings.packages.import');

        // Politiques d'annulation (onglet "Hébergement" des paramètres).
        Route::post('/settings/cancellation-policies', [App\Http\Controllers\CancellationPolicyController::class, 'store'])
            ->name('settings.cancellation_policies.store');
        Route::put('/settings/cancellation-policies/{cancellationPolicy}', [App\Http\Controllers\CancellationPolicyController::class, 'update'])
            ->name('settings.cancellation_policies.update');
        Route::delete('/settings/cancellation-policies/{cancellationPolicy}', [App\Http\Controllers\CancellationPolicyController::class, 'destroy'])
            ->name('settings.cancellation_policies.destroy');
        Route::post('/settings/cancellation-policies/{cancellationPolicy}/default', [App\Http\Controllers\CancellationPolicyController::class, 'setDefault'])
            ->name('settings.cancellation_policies.default');
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

    // --- TICKETS DE SUPPORT (bouton « Suggestion » de la barre supérieure) ---
    // Ouvert à tous les rôles : c'est le canal par lequel le personnel remonte
    // un problème ou une amélioration au support technique, qui les traite
    // depuis l'ERP. Aucun module ni rôle requis, sinon la remontée se perdrait
    // exactement là où elle est la plus utile.
    Route::prefix('support-tickets')->name('support-tickets.')->group(function () {
        Route::get('/', [App\Http\Controllers\SupportTicketController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\SupportTicketController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
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

    // Mot de passe provisoire : l'employé choisit le sien avant d'aller plus
    // loin (ExigerNouveauMotDePasse).
    Route::get('/mot-de-passe/nouveau', [App\Http\Controllers\Auth\NouveauMotDePasseController::class, 'edit'])->name('password.nouveau');
    Route::put('/mot-de-passe/nouveau', [App\Http\Controllers\Auth\NouveauMotDePasseController::class, 'update'])->name('password.nouveau.update');

    // --- CHAMBRES ---
    // Chambres : géré par manager/réception. Le housekeeping change les statuts
    // depuis son propre module, plus depuis cette rubrique.
    Route::prefix('rooms')->name('rooms.')->middleware('permission')->group(function () {
        Route::get('/',                [RoomController::class, 'index'])->name('index');
        Route::post('/',               [RoomController::class, 'store'])->middleware('permission')->name('store');

        // Import / export CSV — déclarés avant /{room} pour ne pas être
        // capturés par le binding de modèle (sinon "export" = id de chambre)
        Route::get('/export',         [\App\Http\Controllers\RoomCsvController::class, 'exportRooms'])->middleware('permission')->name('export');
        Route::post('/import',        [\App\Http\Controllers\RoomCsvController::class, 'importRooms'])->middleware('permission')->name('import');
        Route::get('/types/export',   [\App\Http\Controllers\RoomCsvController::class, 'exportTypes'])->middleware('permission')->name('types.export');
        Route::post('/types/import',  [\App\Http\Controllers\RoomCsvController::class, 'importTypes'])->middleware('permission')->name('types.import');

        Route::get('/{room}',          [RoomController::class, 'show'])->name('show');
        Route::put('/{room}',          [RoomController::class, 'update'])->middleware('permission')->name('update');
        Route::delete('/{room}',       [RoomController::class, 'destroy'])->middleware('permission')->name('destroy');
        Route::delete('/{room}/images/{image}', [RoomController::class, 'destroyImage'])->middleware('permission')->name('images.destroy');

        // Types de chambres - seulement manager
        Route::post('/types/store',         [RoomController::class, 'storeType'])->middleware('permission')->name('types.store');
        Route::put('/types/{roomType}',     [RoomController::class, 'updateType'])->middleware('permission')->name('types.update');
        Route::delete('/types/{roomType}',  [RoomController::class, 'destroyType'])->middleware('permission')->name('types.destroy');
    });

    // --- FICHES TECHNIQUES DES CHAMBRES (marge sur une chambre louée) ---
    // Donnée de gestion : réservée au manager et au comptable. Préfixe hors du
    // groupe « rooms » pour ne pas heurter sa route rooms/{room}.
    Route::prefix('hebergement/fiches-techniques')->name('rooms.cost_sheets.')->middleware('permission')->group(function () {
        $c = App\Http\Controllers\RoomCostSheetController::class;

        Route::get('/', [$c, 'index'])->name('index');
        // Déclaré avant /{roomType} par prudence, même si la contrainte
        // numérique protège déjà : l'ordre reste lisible pour la suite.
        Route::get('/export', [App\Http\Controllers\RoomCostSheetCsvController::class, 'export'])->name('export');
        // Document consolidé — impression, PDF, tableur, traitement de texte.
        // Distinct du classeur ci-dessus, qui sert à remplir les fiches et se
        // réimporte : celui-ci se lit et se classe.
        Route::get('/document', [$c, 'document'])->name('document');
        Route::post('/import', [App\Http\Controllers\RoomCostSheetCsvController::class, 'import'])->name('import');
        Route::get('/{roomType}', [$c, 'show'])->whereNumber('roomType')->name('show');
        Route::put('/{roomType}/hypotheses', [$c, 'updateAssumptions'])->whereNumber('roomType')->name('assumptions');
        Route::post('/{roomType}/demarrage-rapide', [$c, 'applyStarter'])->whereNumber('roomType')->name('starter');
        Route::post('/{roomType}/postes', [$c, 'storeItem'])->whereNumber('roomType')->name('items.store');
        Route::put('/{roomType}/postes/{item}', [$c, 'updateItem'])->whereNumber('roomType')->whereNumber('item')->name('items.update');
        Route::delete('/{roomType}/postes/{item}', [$c, 'destroyItem'])->whereNumber('roomType')->whereNumber('item')->name('items.destroy');
    });
    Route::post('/rooms/{room}/status',  [RoomController::class, 'updateStatus'])
        ->middleware('permission')
        ->name('rooms.updateStatus');

    // --- AGENDA ---
    // L'agenda a sa propre entrée de menu : le calendrier des séjours n'est
    // plus une seconde vue de la liste des réservations.
    Route::get('/agenda', [BookingController::class, 'agenda'])
        ->middleware('permission')
        ->name('agenda.index');

    // --- MODE POS RÉCEPTION ---
    Route::prefix('reception/pos')->name('reception.pos.')->middleware('permission')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reception\ReceptionPosController::class, 'index'])->name('index');
        Route::post('/sales', [\App\Http\Controllers\Reception\ReceptionPosController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}/receipt', [\App\Http\Controllers\Reception\ReceptionPosController::class, 'receipt'])->name('receipt');
        Route::get('/history', [\App\Http\Controllers\Reception\ReceptionPosController::class, 'history'])->name('history');
    });

    // --- RÉSERVATIONS ---
    Route::prefix('bookings')->name('bookings.')->middleware('permission')->group(function () {
        Route::get('/',                        [BookingController::class, 'index'])->name('index');
        Route::get('/create',                  [BookingController::class, 'create'])->name('create');
        Route::post('/',                       [BookingController::class, 'store'])->name('store');

        // --- BROUILLONS DE RÉSERVATION ---
        Route::prefix('drafts')->name('drafts.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\BookingDraftController::class, 'index'])->name('index');
            Route::post('/save',               [\App\Http\Controllers\BookingDraftController::class, 'save'])->name('save');
            Route::get('/{token}/resume',      [\App\Http\Controllers\BookingDraftController::class, 'resume'])->name('resume');
            Route::get('/{token}/continue',    [\App\Http\Controllers\BookingDraftController::class, 'continue'])->name('continue');
            Route::delete('/{token}',          [\App\Http\Controllers\BookingDraftController::class, 'destroy'])->name('destroy');
        });

        
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
        Route::get('/{booking}/summary',       [BookingController::class, 'summary'])->name('summary');
        Route::get('/{booking}/edit',          [BookingController::class, 'edit'])->name('edit');

        // Envoi du code de check-in : hors caisse, expédier un courriel
        // n'engage aucun mouvement d'espèces.
        Route::post('/{booking}/code-checkin', [BookingController::class, 'sendCheckinCode'])->name('checkin_code.send');

        // Validation d'un séjour offert : aucun argent ne circule, et la
        // traçabilité ne vient pas du verrou de caisse mais de l'écriture au
        // journal d'audit que pose approve() — approbateur, client, valeur du
        // manque à gagner. Exiger une caisse ouverte bloquait l'arbitrage du
        // manager au moment même où on le sollicite, et le faisait en
        // redirigeant en silence : l'approbation semblait aboutir sans l'avoir
        // fait.
        Route::post('/{booking}/approve',      [BookingController::class, 'approve'])->name('approve');
        Route::get('/{booking}/cancellation-receipt', [BookingController::class, 'cancellationReceipt'])->name('cancellation_receipt');

        // Actions métier : impossibles tant que la caisse n'est pas ouverte
        Route::middleware('caisse')->group(function () {
            Route::put('/{booking}',               [BookingController::class, 'update'])->name('update');
            Route::post('/{booking}/checkin',      [BookingController::class, 'checkIn'])->name('checkIn');
            Route::post('/{booking}/checkout',     [BookingController::class, 'checkOut'])->name('checkOut');
            Route::post('/{booking}/confirm',      [BookingController::class, 'confirm'])->name('confirm');
            Route::post('/{booking}/cancel',       [BookingController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/folio',        [BookingController::class, 'addFolioItem'])->middleware('permission')->name('folio.add');
            Route::delete('/{booking}/folio/{folioItem}', [BookingController::class, 'removeFolioItem'])->middleware('permission')->name('folio.remove');
            Route::post('/{booking}/payment', [BookingController::class, 'addPayment'])->middleware('permission')->name('payment.add');
        });
    });

    Route::prefix('groups')->name('groups.')->middleware('permission')->group(function () {
        Route::get('/',                          [GroupBookingController::class, 'index'])->name('index');
        Route::get('/create',                    [GroupBookingController::class, 'create'])->middleware('permission')->name('create');
        Route::post('/',                         [GroupBookingController::class, 'store'])->middleware('permission')->name('store');
        Route::get('/{groupBooking}',            [GroupBookingController::class, 'show'])->name('show');
        Route::post('/{groupBooking}/room',      [GroupBookingController::class, 'addRoom'])->middleware('permission')->name('addRoom');
        Route::delete('/{groupBooking}/room/{booking}', [GroupBookingController::class, 'removeRoom'])->middleware('permission')->name('removeRoom');
        Route::post('/{groupBooking}/checkin',   [GroupBookingController::class, 'checkInAll'])->name('checkInAll');
        Route::post('/{groupBooking}/checkout',  [GroupBookingController::class, 'checkOutAll'])->name('checkOutAll');
        Route::post('/{groupBooking}/folio', [GroupBookingController::class, 'addGroupFolioItem'])->name('folio.add');
        Route::post('/{groupBooking}/payment', [GroupBookingController::class, 'addGroupPayment'])->middleware('permission')->name('payment.add');
        Route::get('/{groupBooking}/invoice', [GroupBookingController::class, 'invoice'])->middleware('permission')->name('invoice');
        Route::get('/{groupBooking}/edit',   [GroupBookingController::class, 'edit'])->middleware('permission')->name('edit');
        Route::put('/{groupBooking}',        [GroupBookingController::class, 'update'])->middleware('permission')->name('update');
        Route::post('/{groupBooking}/cancel', [GroupBookingController::class, 'cancel'])->middleware('permission')->name('cancel');
    });

    // --- CLIENTS ---
    Route::prefix('customers')->name('customers.')->middleware('permission')->group(function () {
        Route::get('/',               [CustomerController::class, 'index'])->name('index');
        // Import / export CSV — déclarés avant /{customer} pour ne pas être capturés par le binding.
        Route::get('/export',         [App\Http\Controllers\CustomerCsvController::class, 'export'])->middleware('permission')->name('export');
        Route::post('/import',        [App\Http\Controllers\CustomerCsvController::class, 'import'])->middleware('permission')->name('import');
        Route::get('/create',         [CustomerController::class, 'create'])->middleware('permission')->name('create');
        Route::post('/',              [CustomerController::class, 'store'])->middleware('permission')->name('store');
        Route::get('/{customer}',     [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission')->name('edit');
        Route::put('/{customer}',     [CustomerController::class, 'update'])->middleware('permission')->name('update');
    });

    // --- HOUSEKEEPING ---
    Route::prefix('housekeeping')->name('housekeeping.')->middleware(['permission', 'module:housekeeping'])->group(function () {
        Route::get('/',                    [HousekeepingController::class, 'index'])->name('index');
        Route::post('/teams',              [HousekeepingController::class, 'storeTeam'])->middleware('permission')->name('teams.store');
        Route::post('/assignments',        [HousekeepingController::class, 'assignRooms'])->middleware('permission')->name('assignments.store');
        Route::post('/{room}/clean',       [HousekeepingController::class, 'markCleaning'])->name('clean');
        Route::post('/{room}/ready',       [HousekeepingController::class, 'markReady'])->name('ready');
        Route::post('/{room}/inspect',     [HousekeepingController::class, 'markInspected'])->name('inspect');
        Route::post('/{room}/available',   [HousekeepingController::class, 'markAvailable'])->name('available');
        Route::post('/{room}/reject',      [HousekeepingController::class, 'rejectCleaning'])->name('reject');
        Route::post('/{room}/issue',       [HousekeepingController::class, 'reportIssue'])->name('issue');
    });

    // --- RESTAURANT (menus) ---
    // Restaurant dans lequel on travaille : le choix ne donne aucun droit,
    // il ne fait que borner ce que les écrans montrent.
    Route::post('/restaurant/restaurant-courant', [App\Http\Controllers\RestaurantContextController::class, 'choisir'])
        ->middleware('module:restaurant')->name('restaurant.courant');

    // Lecture (manager peut consulter), Écriture réservée au staff restaurant
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::get('/menus', [RestaurantMenuController::class, 'index'])->name('menus.index');
        Route::get('/menus-export', [App\Http\Controllers\RestaurantCsvController::class, 'exportMenus'])->name('menus.export');
        Route::get('/breakfast', [App\Http\Controllers\RestaurantBreakfastController::class, 'index'])->name('breakfast.index');
        Route::post('/breakfast/{entitlement}/serve', [App\Http\Controllers\RestaurantBreakfastController::class, 'serve'])->whereNumber('entitlement')->name('breakfast.serve');
        Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::get('/kitchen', [App\Http\Controllers\RestaurantKitchenController::class, 'index'])->name('kitchen.index');
        // Chaque restaurant a son bar : les boissons y sont préparées.
        Route::get('/bar', [App\Http\Controllers\RestaurantKitchenController::class, 'bar'])->name('bar.index');
    });

    // Gestion du restaurant : coûts, stocks et inventaires. La salle en est
    // écartée — masquer le lien sans fermer l'URL n'aurait rien masqué.
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::get('/pantry-export', [App\Http\Controllers\RestaurantCsvController::class, 'exportPantry'])->name('pantry.export');
        Route::get('/recipes-export', [App\Http\Controllers\RestaurantCsvController::class, 'exportRecipes'])->name('recipes.export');
        Route::get('/pantry', [RestaurantPantryController::class, 'index'])->name('pantry.index');
        Route::get('/recipes', [App\Http\Controllers\RestaurantRecipeController::class, 'index'])->name('recipes.index');
        Route::get('/stock-counts', [App\Http\Controllers\RestaurantStockCountController::class, 'index'])->name('stock_counts.index');
        Route::get('/stock-counts/{stockCount}', [App\Http\Controllers\RestaurantStockCountController::class, 'show'])->whereNumber('stockCount')->name('stock_counts.show');
        Route::get('/waste', [App\Http\Controllers\RestaurantWasteController::class, 'index'])->name('waste.index');
        Route::get('/waste/create', [App\Http\Controllers\RestaurantWasteController::class, 'create'])->name('waste.create');
        Route::get('/waste/{waste}', [App\Http\Controllers\RestaurantWasteController::class, 'show'])->whereNumber('waste')->name('waste.show');
        Route::get('/waste/{waste}/print', [App\Http\Controllers\RestaurantWasteController::class, 'print'])->whereNumber('waste')->name('waste.print');
        Route::get('/consumption', [App\Http\Controllers\RestaurantConsumptionController::class, 'index'])->name('consumption.index');
    });

    // Cuisine : réception des bons et signalement des plats prêts (cuisinier + chef)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::post('/orders/{order}/preparing', [RestaurantOrderController::class, 'markPreparing'])->whereNumber('order')->name('orders.preparing');
        Route::post('/orders/{order}/ready', [RestaurantOrderController::class, 'markReady'])->whereNumber('order')->name('orders.ready');
    });

    // Salle : prise de service, transmission en cuisine, service (serveur + chef)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::post('/shifts/open', [App\Http\Controllers\RestaurantShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shifts/close', [App\Http\Controllers\RestaurantShiftController::class, 'close'])->name('shifts.close');

        Route::post('/orders/{order}/send-to-kitchen', [RestaurantOrderController::class, 'sendToKitchen'])->whereNumber('order')->name('orders.send_to_kitchen');
        Route::post('/orders/{order}/served', [RestaurantOrderController::class, 'markServed'])->whereNumber('order')->name('orders.served');
        Route::post('/orders/{order}/bar-ready', [App\Http\Controllers\RestaurantKitchenController::class, 'barReady'])->whereNumber('order')->name('orders.bar_ready');
        Route::post('/orders/{order}/claim', [RestaurantOrderController::class, 'claim'])->whereNumber('order')->name('orders.claim');
        Route::post('/orders/{order}/reassign', [RestaurantOrderController::class, 'reassign'])->whereNumber('order')->name('orders.reassign');
    });

    // Écriture RESTAURANT — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::post('/orders', [RestaurantOrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/status', [RestaurantOrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');
        // Le serveur n'ouvre plus le garde-manger : il n'a plus à en sortir
        // des mouvements de stock.
        Route::post('/pantry/items/{item}/movements', [RestaurantPantryController::class, 'storeMovement'])->middleware('permission')->name('pantry.movements.store');

        Route::middleware('permission')->group(function () {
            Route::post('/menus/categories', [RestaurantMenuController::class, 'storeCategory'])->name('menus.categories.store');
            Route::put('/menus/categories/{category}', [RestaurantMenuController::class, 'updateCategory'])->name('menus.categories.update');
            Route::delete('/menus/categories/{category}', [RestaurantMenuController::class, 'destroyCategory'])->name('menus.categories.destroy');

            Route::post('/menus-import', [App\Http\Controllers\RestaurantCsvController::class, 'importMenus'])->name('menus.import');
            Route::post('/pantry-import', [App\Http\Controllers\RestaurantCsvController::class, 'importPantry'])->name('pantry.import');
            Route::post('/recipes-import', [App\Http\Controllers\RestaurantCsvController::class, 'importRecipes'])->name('recipes.import');
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

            // Déclaration de pertes & gaspillage
            Route::post('/waste', [App\Http\Controllers\RestaurantWasteController::class, 'store'])->name('waste.store');
        });
    });

    // --- RESTAURANT (facturation interne) ---
    // Lecture (manager peut consulter)
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::get('/billing', [RestaurantBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{order}', [RestaurantBillingController::class, 'show'])->whereNumber('order')->name('billing.show');
        Route::get('/billing/{order}/receipt', [RestaurantBillingController::class, 'receipt'])->whereNumber('order')->name('billing.receipt');

        // Caisse du restaurant : une par restaurant, une session par personne.
        Route::get('/cash-register', [App\Http\Controllers\RestaurantCashRegisterController::class, 'index'])->name('cash_register.index');
        Route::get('/cash-register/open', [App\Http\Controllers\RestaurantCashRegisterController::class, 'showOpenForm'])->name('cash_register.open');
        Route::get('/cash-register/close', [App\Http\Controllers\RestaurantCashRegisterController::class, 'showCloseForm'])->name('cash_register.close');
    });

    // Écriture facturation — manager exclu
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::post('/billing/{order}/paid', [RestaurantBillingController::class, 'markPaid'])->whereNumber('order')->name('billing.paid');
        Route::post('/billing/{order}/unpaid', [RestaurantBillingController::class, 'markUnpaid'])->whereNumber('order')->name('billing.unpaid');

        Route::post('/cash-register/open', [App\Http\Controllers\RestaurantCashRegisterController::class, 'open'])->name('cash_register.open.store');
        Route::post('/cash-register/close', [App\Http\Controllers\RestaurantCashRegisterController::class, 'close'])->name('cash_register.close.store');
        Route::post('/cash-register/disbursements', [App\Http\Controllers\RestaurantCashRegisterController::class, 'storeDisbursement'])->name('cash_register.disbursements.store');
    });

    // --- RESTAURANTS MULTIPLES ---
    // Les restaurants de l'hôtel, leurs salles et leurs équipes ; le buffet au
    // forfait ; les banquets réservés dans l'un ou l'autre restaurant.
    Route::prefix('restaurant')->name('restaurant.')->middleware(['permission', 'module:restaurant'])->group(function () {
        Route::get('/restaurants', [App\Http\Controllers\RestaurantSetupController::class, 'index'])->name('restaurants.index');
        Route::post('/restaurants', [App\Http\Controllers\RestaurantSetupController::class, 'store'])->name('restaurants.store');
        Route::put('/restaurants/{restaurant}', [App\Http\Controllers\RestaurantSetupController::class, 'update'])->whereNumber('restaurant')->name('restaurants.update');
        Route::put('/restaurants/{restaurant}/equipe', [App\Http\Controllers\RestaurantSetupController::class, 'updateTeam'])->whereNumber('restaurant')->name('restaurants.team.update');
        Route::post('/restaurants/{restaurant}/salles', [App\Http\Controllers\RestaurantSetupController::class, 'storeSpace'])->whereNumber('restaurant')->name('restaurants.spaces.store');
        Route::put('/salles/{space}', [App\Http\Controllers\RestaurantSetupController::class, 'updateSpace'])->whereNumber('space')->name('restaurants.spaces.update');

        Route::get('/buffets', [App\Http\Controllers\RestaurantBuffetController::class, 'index'])->name('buffets.index');
        Route::post('/buffets', [App\Http\Controllers\RestaurantBuffetController::class, 'store'])->name('buffets.store');
        Route::get('/buffets/{buffet}', [App\Http\Controllers\RestaurantBuffetController::class, 'show'])->whereNumber('buffet')->name('buffets.show');
        Route::post('/buffets/{buffet}/entries', [App\Http\Controllers\RestaurantBuffetController::class, 'storeEntry'])->whereNumber('buffet')->name('buffets.entries.store');
        Route::post('/buffets/{buffet}/close', [App\Http\Controllers\RestaurantBuffetController::class, 'close'])->whereNumber('buffet')->name('buffets.close');

        Route::get('/banquets', [App\Http\Controllers\RestaurantBanquetController::class, 'index'])->name('banquets.index');
        Route::post('/banquets', [App\Http\Controllers\RestaurantBanquetController::class, 'store'])->name('banquets.store');
        Route::get('/banquets/{banquet}', [App\Http\Controllers\RestaurantBanquetController::class, 'show'])->whereNumber('banquet')->name('banquets.show');
        Route::put('/banquets/{banquet}', [App\Http\Controllers\RestaurantBanquetController::class, 'update'])->whereNumber('banquet')->name('banquets.update');
        Route::post('/banquets/{banquet}/status', [App\Http\Controllers\RestaurantBanquetController::class, 'status'])->whereNumber('banquet')->name('banquets.status');
        Route::post('/banquets/{banquet}/payments', [App\Http\Controllers\RestaurantBanquetController::class, 'storePayment'])->whereNumber('banquet')->name('banquets.payments.store');
    });

    Route::prefix('invoices')->name('invoices.')->middleware('permission')->group(function () {
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    });

    // --- ÉDITIONS ---
    // Le point où l'on retrouve tout ce qui s'imprime : registres, situations,
    // pièces. Chaque édition pose en plus le droit des données qu'elle montre.
    Route::middleware('permission')->prefix('editions')->name('editions.')->group(function () {
        $editions = App\Http\Controllers\EditionController::class;
        Route::get('/', [$editions, 'index'])->name('index');
        Route::get('/{edition}', [$editions, 'show'])->where('edition', '[a-z0-9-]+')->name('show');
        Route::get('/{edition}/imprimer', [$editions, 'print'])->where('edition', '[a-z0-9-]+')->name('print');
        Route::get('/{edition}/telecharger', [$editions, 'export'])->where('edition', '[a-z0-9-]+')->name('export');
    });

    // --- PLANNING DES QUARTS ---
    // Chaque chef place son personnel sur les quarts de l'hôtel, semaine par
    // semaine ; chacun consulte ses quarts.
    Route::middleware('permission')->group(function () {
        Route::get('/planning', [App\Http\Controllers\PlanningController::class, 'index'])->name('planning.index');
        Route::post('/planning/affectations', [App\Http\Controllers\PlanningController::class, 'store'])->name('planning.affectations.store');
        Route::delete('/planning/affectations/{affectation}', [App\Http\Controllers\PlanningController::class, 'destroy'])->whereNumber('affectation')->name('planning.affectations.destroy');
        Route::post('/planning/recopier', [App\Http\Controllers\PlanningController::class, 'recopier'])->name('planning.recopier');
        Route::post('/planning/publier', [App\Http\Controllers\PlanningController::class, 'publier'])->name('planning.publier');

        // Les quarts de l'hôtel, définis par la direction (Paramètres › Quarts).
        Route::post('/settings/quarts', [App\Http\Controllers\WorkShiftController::class, 'store'])->name('settings.quarts.store');
        Route::put('/settings/quarts/{quart}', [App\Http\Controllers\WorkShiftController::class, 'update'])->whereNumber('quart')->name('settings.quarts.update');
        Route::delete('/settings/quarts/{quart}', [App\Http\Controllers\WorkShiftController::class, 'destroy'])->whereNumber('quart')->name('settings.quarts.destroy');
    });

    // --- UTILISATEURS (staff) ---
    Route::prefix('users')->name('users.')->middleware('permission')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}', [UserManagementController::class, 'show'])->whereNumber('user')->name('show');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggleStatus');
        Route::post('/{user}/reinitialiser-mot-de-passe', [UserManagementController::class, 'resetPassword'])->whereNumber('user')->name('resetPassword');
    });

    // --- ADMINISTRATION (service informatique) ---
    // L'administrateur règle les rôles et les droits de l'hôtel — la couche de
    // l'établissement —, consulte le journal, et n'écrit dans l'exploitation
    // que pendant une intervention déclarée, motivée et tracée.
    Route::middleware('permission')->group(function () {
        $droits = App\Http\Controllers\RoleRightsController::class;
        Route::get('/droits', [$droits, 'index'])->name('droits.index');
        Route::put('/droits', [$droits, 'update'])->name('droits.update');
        Route::post('/droits/apercu', [$droits, 'apercu'])->name('droits.apercu');
        Route::post('/droits/exceptions', [$droits, 'storeException'])->name('droits.exceptions.store');
        Route::delete('/droits/exceptions/{grant}', [$droits, 'destroyException'])->whereNumber('grant')->name('droits.exceptions.destroy');

        $interventions = App\Http\Controllers\InterventionController::class;
        Route::get('/interventions', [$interventions, 'index'])->name('interventions.index');
        Route::post('/interventions', [$interventions, 'store'])->name('interventions.store');
        Route::post('/interventions/{intervention}/terminer', [$interventions, 'terminer'])->whereNumber('intervention')->name('interventions.terminer');

        Route::get('/journal-audit', [App\Http\Controllers\AuditJournalController::class, 'index'])->name('audit.index');
        Route::get('/support/sessions', [App\Http\Controllers\SupportSessionController::class, 'index'])->name('support.sessions.index');
    });

    // --- COMPTABILITÉ (comptabilité de caisse : hébergement + resto + boutique) ---
    Route::prefix('accounting')->name('accounting.')->middleware('permission')->group(function () {
        $c = App\Http\Controllers\AccountingController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/journal', [$c, 'journal'])->name('journal');
        Route::get('/compte-de-resultat', [$c, 'incomeStatement'])->name('income_statement');

        // Comptage contradictoire : file des caisses comptées attendant la
        // contresignature d'un tiers. Le contrôleur vérifie lui-même que
        // l'utilisateur est habilité selon la politique de l'établissement,
        // et qu'il n'est pas le déclarant.
        $rev = App\Http\Controllers\CashClosureReviewController::class;

        Route::get('/controles-caisse', [$rev, 'index'])->name('cash_reviews');
        Route::post('/controles-caisse/{session}', [$rev, 'store'])->whereNumber('session')->name('cash_reviews.store');
        Route::get('/creances', [$c, 'receivables'])->name('receivables');
        Route::get('/caisse', [$c, 'cash'])->name('cash');
        Route::get('/journal-encaissements', [$c, 'revenueJournal'])->name('revenue_journal');

        Route::get('/depenses', [$c, 'expenses'])->name('expenses');
        Route::post('/depenses', [$c, 'storeExpense'])->name('expenses.store');
        Route::put('/depenses/{expense}', [$c, 'updateExpense'])->name('expenses.update');
        Route::delete('/depenses/{expense}', [$c, 'destroyExpense'])->name('expenses.destroy');

        // --- COMPTABILITÉ AVANCÉE (grand livre SYSCOHADA) ---
        // S'ajoute à la comptabilité de caisse ci-dessus sans la remplacer :
        // l'une répond au quotidien de la réception, l'autre à l'obligation
        // légale et au pilotage.
        // Activable par établissement depuis l'ERP : tout le monde n'a pas
        // besoin d'un grand livre. La comptabilité de caisse ci-dessus, elle,
        // reste toujours accessible.
        Route::prefix('ledger')->name('ledger.')->middleware('module:ledger')->group(function () {
            $l = App\Http\Controllers\LedgerController::class;

            Route::get('/', [$l, 'index'])->name('index');
            Route::get('/plan-de-comptes', [$l, 'accounts'])->name('accounts');
            Route::get('/journaux', [$l, 'journals'])->name('journals');
            Route::get('/grand-livre', [$l, 'generalLedger'])->name('general');
            Route::get('/balance', [$l, 'balance'])->name('balance');

            // Clôture journalière — déclarée avant /ecritures/{entry}.
            Route::get('/cloture', [$l, 'nightAudits'])->name('night_audit');
            Route::post('/cloture', [$l, 'runNightAudit'])->name('night_audit.run');

            // Périodes et exercices — le verrouillage matérialise l'Article 22.
            Route::get('/periodes', [$l, 'periods'])->name('periods');
            Route::post('/exercices', [$l, 'openYear'])->name('years.open');
            Route::post('/periodes/{period}/verrouiller', [$l, 'lockPeriod'])->name('periods.lock');

            // Reprise des à-nouveaux — déclarée avant /ecritures/{entry}.
            Route::get('/a-nouveaux', [$l, 'openingBalance'])->name('opening');
            Route::post('/a-nouveaux', [$l, 'storeOpeningBalance'])->name('opening.store');

            // Comptabilité auxiliaire et lettrage : le détail par tiers derrière
            // les comptes collectifs, et la balance âgée qui en découle.
            $aux = App\Http\Controllers\AuxiliaryController::class;

            Route::get('/auxiliaire', [$aux, 'index'])->name('auxiliary');
            Route::get('/auxiliaire/tiers', [$aux, 'ledger'])->name('auxiliary.ledger');
            Route::get('/balance-agee', [$aux, 'agedBalance'])->name('aged');
            Route::post('/lettrage', [$aux, 'reconcile'])->name('reconcile');
            Route::post('/lettrage/annuler', [$aux, 'unreconcile'])->name('reconcile.undo');
            Route::post('/lettrage/auto', [$aux, 'autoReconcile'])->name('reconcile.auto');

            // Fournisseurs : factures reçues et retenues à la source.
            $fou = App\Http\Controllers\SupplierInvoiceController::class;

            Route::get('/fournisseurs', [$fou, 'index'])->name('suppliers');
            Route::get('/fournisseurs/nouvelle', [$fou, 'create'])->name('suppliers.create');
            Route::post('/fournisseurs', [$fou, 'store'])->name('suppliers.store');
            Route::get('/retenues', [$fou, 'withholdingStatement'])->name('withholding');
            Route::get('/fournisseurs/{invoice}', [$fou, 'show'])->whereNumber('invoice')->name('suppliers.show');

            // Analytique (classe 9) : rentabilité par point de vente.
            $ana = App\Http\Controllers\AnalyticController::class;

            Route::get('/analytique', [$ana, 'index'])->name('analytic');
            Route::get('/analytique/marges', [$ana, 'margins'])->name('analytic.margins');
            Route::post('/analytique/reflet', [$ana, 'postMirror'])->name('analytic.mirror');

            Route::get('/ecritures/{entry}', [$l, 'show'])->whereNumber('entry')->name('entry');
            Route::post('/ecritures/{entry}/extourne', [$l, 'reverse'])->whereNumber('entry')->name('entry.reverse');
        });
    });

    // --- ÉCONOMAT / INVENTAIRE ---
    Route::prefix('economat')->name('economat.')->group(function () {
        $eco = 'App\\Http\\Controllers\\Economat\\';

        // Gestion du magasin : réservée à l'économe (et manager/admin).
        Route::middleware('permission')->group(function () use ($eco) {
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
            Route::post('/articles/{item}/reprise', [$eco . 'StockItemController', 'opening'])->whereNumber('item')->name('items.opening');
            Route::delete('/articles/{item}', [$eco . 'StockItemController', 'destroy'])->whereNumber('item')->name('items.destroy');

            // Unités de stockage des articles (réglées dans Paramètres › Économat)
            Route::post('/unites', [$eco . 'StockUnitController', 'store'])->name('units.store');
            Route::put('/unites/{unite}', [$eco . 'StockUnitController', 'update'])->whereNumber('unite')->name('units.update');
            Route::delete('/unites/{unite}', [$eco . 'StockUnitController', 'destroy'])->whereNumber('unite')->name('units.destroy');

            // Mouvements de stock : tous les articles, ou la fiche de stock d'un seul
            Route::get('/mouvements', [$eco . 'StockMovementController', 'index'])->name('movements.index');
            Route::get('/mouvements/export', [$eco . 'StockMovementController', 'export'])->name('movements.export');

            // Catégories d'articles et leur compte de stock
            Route::get('/categories', [$eco . 'StockCategoryController', 'index'])->name('categories.index');
            Route::post('/categories', [$eco . 'StockCategoryController', 'store'])->name('categories.store');
            Route::put('/categories/{category}', [$eco . 'StockCategoryController', 'update'])->whereNumber('category')->name('categories.update');
            Route::delete('/categories/{category}', [$eco . 'StockCategoryController', 'destroy'])->whereNumber('category')->name('categories.destroy');

            // Dépôts de service (étages, mini-bar, bar, pâtisserie…)
            Route::get('/depots', [$eco . 'ServiceStoreController', 'index'])->name('stores.index');
            Route::post('/depots', [$eco . 'ServiceStoreController', 'store'])->name('stores.store');
            Route::get('/depots/{store}', [$eco . 'ServiceStoreController', 'show'])->whereNumber('store')->name('stores.show');
            Route::put('/depots/{store}', [$eco . 'ServiceStoreController', 'update'])->whereNumber('store')->name('stores.update');
            Route::delete('/depots/{store}', [$eco . 'ServiceStoreController', 'destroy'])->whereNumber('store')->name('stores.destroy');
            Route::post('/depots/{store}/inventaires', [$eco . 'ServiceStoreCountController', 'store'])->whereNumber('store')->name('stores.counts.store');
            Route::get('/depots/inventaires/{count}', [$eco . 'ServiceStoreCountController', 'show'])->whereNumber('count')->name('stores.counts.show');
            Route::put('/depots/inventaires/{count}', [$eco . 'ServiceStoreCountController', 'update'])->whereNumber('count')->name('stores.counts.update');
            Route::post('/depots/inventaires/{count}/cloturer', [$eco . 'ServiceStoreCountController', 'close'])->whereNumber('count')->name('stores.counts.close');
            Route::post('/depots/inventaires/{count}/annuler', [$eco . 'ServiceStoreCountController', 'cancel'])->whereNumber('count')->name('stores.counts.cancel');

            // Fournisseurs
            Route::get('/fournisseurs', [$eco . 'SupplierController', 'index'])->name('suppliers.index');
            Route::post('/fournisseurs', [$eco . 'SupplierController', 'store'])->name('suppliers.store');
            Route::put('/fournisseurs/{supplier}', [$eco . 'SupplierController', 'update'])->name('suppliers.update');
            Route::delete('/fournisseurs/{supplier}', [$eco . 'SupplierController', 'destroy'])->name('suppliers.destroy');

            // Bons de commande
            Route::get('/bons', [$eco . 'PurchaseOrderController', 'index'])->name('orders.index');
            Route::get('/bons/export', [$eco . 'PurchaseOrderController', 'export'])->name('orders.export');
            Route::get('/bons/nouveau', [$eco . 'PurchaseOrderController', 'create'])->name('orders.create');
            Route::post('/bons', [$eco . 'PurchaseOrderController', 'store'])->name('orders.store');
            Route::get('/bons/{order}', [$eco . 'PurchaseOrderController', 'show'])->whereNumber('order')->name('orders.show');
            Route::get('/bons/{order}/imprimer', [$eco . 'PurchaseOrderController', 'print'])->whereNumber('order')->name('orders.print');
            Route::post('/bons/{order}/envoyer', [$eco . 'PurchaseOrderController', 'send'])->whereNumber('order')->name('orders.send');
            // Bon parvenu au fournisseur sans email : main propre, téléphone, WhatsApp.
            Route::post('/bons/{order}/transmis', [$eco . 'PurchaseOrderController', 'transmit'])->whereNumber('order')->name('orders.transmit');
            Route::post('/bons/{order}/reception', [$eco . 'PurchaseOrderController', 'receive'])->whereNumber('order')->name('orders.receive');
            Route::post('/bons/{order}/annuler', [$eco . 'PurchaseOrderController', 'cancel'])->whereNumber('order')->name('orders.cancel');

            // Bons de réception (Goods Receipts)
            Route::get('/receptions', [$eco . 'GoodsReceiptController', 'index'])->name('receipts.index');
            Route::get('/receptions/export', [$eco . 'GoodsReceiptController', 'export'])->name('receipts.export');
            // Réception directe : marchandise arrivée sans bon de commande.
            Route::get('/receptions/directe', [$eco . 'GoodsReceiptController', 'createDirect'])->name('receipts.direct.create');
            Route::post('/receptions/directe', [$eco . 'GoodsReceiptController', 'storeDirect'])->name('receipts.direct.store');
            Route::get('/bons/{order}/receptionner', [$eco . 'GoodsReceiptController', 'create'])->whereNumber('order')->name('receipts.create');
            Route::post('/bons/{order}/receptionner', [$eco . 'GoodsReceiptController', 'store'])->whereNumber('order')->name('receipts.store');
            Route::get('/receptions/{receipt}', [$eco . 'GoodsReceiptController', 'show'])->whereNumber('receipt')->name('receipts.show');
            Route::get('/receptions/{receipt}/imprimer', [$eco . 'GoodsReceiptController', 'print'])->whereNumber('receipt')->name('receipts.print');
            Route::post('/receptions/{receipt}/annuler', [$eco . 'GoodsReceiptController', 'cancel'])->whereNumber('receipt')->name('receipts.cancel');

            // Traitement des demandes d'achat (approbation / rejet / conversion)
            Route::post('/demandes-achat/{purchaseRequest}/approuver', [$eco . 'PurchaseRequestController', 'approve'])->whereNumber('purchaseRequest')->name('purchase_requests.approve');
            Route::post('/demandes-achat/{purchaseRequest}/refuser', [$eco . 'PurchaseRequestController', 'reject'])->whereNumber('purchaseRequest')->name('purchase_requests.reject');
            Route::post('/demandes-achat/{purchaseRequest}/convertir', [$eco . 'PurchaseRequestController', 'convert'])->whereNumber('purchaseRequest')->name('purchase_requests.convert');

            // Traitement des demandes (validation / livraison)
            Route::post('/demandes/{requisition}/valider', [$eco . 'StockRequisitionController', 'approve'])->whereNumber('requisition')->name('requisitions.approve');
            Route::post('/demandes/{requisition}/refuser', [$eco . 'StockRequisitionController', 'reject'])->whereNumber('requisition')->name('requisitions.reject');
            Route::post('/demandes/{requisition}/livrer', [$eco . 'StockRequisitionController', 'deliver'])->whereNumber('requisition')->name('requisitions.deliver');

            // Fiches de comptage par service (inventaire général)
            Route::get('/fiches-comptage', [$eco . 'CountSheetController', 'index'])->name('count_sheets.index');
            Route::get('/fiches-comptage/imprimer', [$eco . 'CountSheetController', 'print'])->name('count_sheets.print');

            // Inventaires physiques & PV d'écarts
            Route::get('/inventaires', [$eco . 'StockCountController', 'index'])->name('stock_counts.index');
            Route::get('/inventaires/nouveau', [$eco . 'StockCountController', 'create'])->name('stock_counts.create');
            // Fichier de comptage (Excel) à remplir après le comptage, puis à importer.
            Route::get('/inventaires/fichier-comptage', [$eco . 'StockCountController', 'fichier'])->name('stock_counts.export');
            Route::post('/inventaires/{stockCount}/import', [$eco . 'StockCountController', 'import'])->whereNumber('stockCount')->name('stock_counts.import');
            Route::post('/inventaires', [$eco . 'StockCountController', 'store'])->name('stock_counts.store');
            Route::get('/inventaires/{stockCount}', [$eco . 'StockCountController', 'show'])->whereNumber('stockCount')->name('stock_counts.show');
            Route::put('/inventaires/{stockCount}', [$eco . 'StockCountController', 'update'])->whereNumber('stockCount')->name('stock_counts.update');
            Route::post('/inventaires/{stockCount}/cloturer', [$eco . 'StockCountController', 'close'])->whereNumber('stockCount')->name('stock_counts.close');
            Route::post('/inventaires/{stockCount}/annuler', [$eco . 'StockCountController', 'cancel'])->whereNumber('stockCount')->name('stock_counts.cancel');
            Route::get('/inventaires/{stockCount}/pv', [$eco . 'StockCountController', 'report'])->whereNumber('stockCount')->name('stock_counts.report');

            // Contrôle, alertes, propositions de commande et ratios
            Route::get('/controle', [$eco . 'StockControlController', 'index'])->name('control.index');
            Route::get('/controle/propositions', [$eco . 'StockControlController', 'suggestions'])->name('control.suggestions.index');
            Route::post('/controle/propositions', [$eco . 'StockControlController', 'generatePurchaseRequest'])->name('control.suggestions.store');
            Route::get('/controle/ecarts', [$eco . 'StockControlController', 'variances'])->name('control.variances.index');
            Route::get('/controle/imprimer', [$eco . 'StockControlController', 'printReport'])->name('control.print');
        });

        // Demandes & Demandes d'achat : ouvertes aussi aux responsables de département qui
        // sollicitent l'économat. Le contrôleur cloisonne à leurs propres demandes.
        Route::middleware('permission')->group(function () use ($eco) {
            // Demandes d'achat internes
            Route::get('/demandes-achat', [$eco . 'PurchaseRequestController', 'index'])->name('purchase_requests.index');
            Route::get('/demandes-achat/nouvelle', [$eco . 'PurchaseRequestController', 'create'])->name('purchase_requests.create');
            Route::post('/demandes-achat', [$eco . 'PurchaseRequestController', 'store'])->name('purchase_requests.store');
            Route::get('/demandes-achat/{purchaseRequest}', [$eco . 'PurchaseRequestController', 'show'])->whereNumber('purchaseRequest')->name('purchase_requests.show');
            Route::post('/demandes-achat/{purchaseRequest}/annuler', [$eco . 'PurchaseRequestController', 'cancel'])->whereNumber('purchaseRequest')->name('purchase_requests.cancel');

            // Demandes internes
            Route::get('/demandes', [$eco . 'StockRequisitionController', 'index'])->name('requisitions.index');
            // Impression et export : mêmes filtres que l'écran, même garde.
            Route::get('/demandes/export', [$eco . 'StockRequisitionController', 'export'])->name('requisitions.export');
            Route::get('/demandes/nouvelle', [$eco . 'StockRequisitionController', 'create'])->name('requisitions.create');
            Route::post('/demandes', [$eco . 'StockRequisitionController', 'store'])->name('requisitions.store');
            Route::get('/demandes/{requisition}', [$eco . 'StockRequisitionController', 'show'])->whereNumber('requisition')->name('requisitions.show');
            Route::get('/demandes/{requisition}/imprimer', [$eco . 'StockRequisitionController', 'print'])->whereNumber('requisition')->name('requisitions.print');
            Route::post('/demandes/{requisition}/annuler', [$eco . 'StockRequisitionController', 'cancel'])->whereNumber('requisition')->name('requisitions.cancel');
            // Visa du chef de service avant l'économat (le contrôleur vérifie le service).
            Route::post('/demandes/{requisition}/viser', [$eco . 'StockRequisitionController', 'endorse'])->whereNumber('requisition')->name('requisitions.endorse');
        });
    });

    // --- SHOP ---
    // Lecture seule pour le manager (GET uniquement)
    Route::prefix('shop')->name('shop.')->middleware(['permission', 'module:shop'])->group(function () {
        Route::get('/cash-register', [CashRegisterController::class, 'index'])->middleware('permission')->name('cash_register.index');
        Route::get('/products', [ShopProductController::class, 'index'])->middleware('permission')->name('products.index');
        Route::get('/products-export', [App\Http\Controllers\ShopProductCsvController::class, 'export'])->middleware('permission')->name('products.export');
        Route::get('/orders', [ShopOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/receipt', [ShopOrderController::class, 'receipt'])->whereNumber('order')->name('orders.receipt');
        Route::get('/orders/{order}', [ShopOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    });

    // Écriture SHOP — manager totalement exclu
    Route::prefix('shop')->name('shop.')->middleware(['permission', 'module:shop'])->group(function () {
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
        Route::middleware('permission')->group(function () {
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
    })->middleware('permission')->name('test-popup');

    // Statut de la liaison avec le site vitrine (badge du header)
    Route::get('/site-sync/status', [\App\Http\Controllers\SiteSyncController::class, 'status'])->name('site-sync.status');
});

// ==========================================
// ANALYTICS (Manager uniquement)
// ==========================================
Route::middleware(['auth', 'permission', 'module:analytics'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
    Route::get('/print', [\App\Http\Controllers\AnalyticsController::class, 'print'])->name('print');
});
// Routes Breeze (login, register, etc.) — déjà générées, ne pas toucher
//require __DIR__.'/auth.php';
