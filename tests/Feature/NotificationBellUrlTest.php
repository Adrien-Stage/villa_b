<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiscussionConversation;
use App\Models\DiscussionMessage;
use App\Models\HousekeepingTeam;
use App\Models\PurchaseRequest;
use App\Models\Room;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ComplimentaryBookingRequested;
use App\Notifications\DiscussionMessageReceived;
use App\Notifications\HousekeepingRoomsAssigned;
use App\Notifications\NewBookingCreated;
use App\Notifications\PurchaseRequestUpdated;
use App\Notifications\WebsiteBookingReceived;
use App\Support\NotificationUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('NotificationUrl normalise les URL locales, loopback, réseau privé et Docker en chemins relatifs', function () {
    $lanRequest = Request::create('http://192.168.6.127:8082/notifications/unread');

    expect(NotificationUrl::toAppPath('http://127.0.0.1:8082/housekeeping', $lanRequest))->toBe('/housekeeping')
        ->and(NotificationUrl::toAppPath('http://localhost:8081/economat/demandes-achat/4?tab=lignes#top', $lanRequest))
            ->toBe('/economat/demandes-achat/4?tab=lignes#top')
        ->and(NotificationUrl::toAppPath('http://[::1]:8082/rooms', $lanRequest))->toBe('/rooms')
        ->and(NotificationUrl::toAppPath('http://0.0.0.0:8082/bookings/5', $lanRequest))->toBe('/bookings/5')
        ->and(NotificationUrl::toAppPath('http://192.168.6.127:8082/bookings/12', $lanRequest))->toBe('/bookings/12')
        ->and(NotificationUrl::toAppPath('http://10.0.0.5:8082/restaurant/kitchen', $lanRequest))->toBe('/restaurant/kitchen')
        ->and(NotificationUrl::toAppPath('http://meka-erp-zingana-app/bookings/7', $lanRequest))->toBe('/bookings/7')
        ->and(NotificationUrl::toAppPath('https://zingana.local/pilotage', $lanRequest))->toBe('/pilotage')
        ->and(NotificationUrl::toAppPath('//127.0.0.1:8082/housekeeping', $lanRequest))->toBe('/housekeeping')
        ->and(NotificationUrl::toAppPath('https://192.168.6.127:8443', $lanRequest))->toBe('/')
        ->and(NotificationUrl::toAppPath('/housekeeping?filter=dirty', $lanRequest))->toBe('/housekeeping?filter=dirty')
        ->and(NotificationUrl::toAppPath('housekeeping', $lanRequest))->toBe('/housekeeping');
});

test('NotificationUrl respecte le domaine applicatif courant, préserve les liens externes publics et neutralise les schémas dangereux', function () {
    config(['app.url' => 'https://pms.zingana.cm']);
    $domainRequest = Request::create('https://app.hotel-zingana.cm/notifications/unread');

    expect(NotificationUrl::toAppPath('https://pms.zingana.cm/bookings/9', $domainRequest))->toBe('/bookings/9')
        ->and(NotificationUrl::toAppPath('https://app.hotel-zingana.cm/rooms/3', $domainRequest))->toBe('/rooms/3')
        ->and(NotificationUrl::toAppPath('https://docs.example.org/guide?page=2', $domainRequest))
            ->toBe('https://docs.example.org/guide?page=2')
        ->and(NotificationUrl::toAppPath('javascript:alert(1)', $domainRequest))->toBeNull()
        ->and(NotificationUrl::toAppPath('data:text/html,<script>alert(1)</script>', $domainRequest))->toBeNull()
        ->and(NotificationUrl::toAppPath('//evil.example.org/redirect', $domainRequest))->toBeNull()
        ->and(NotificationUrl::toAppPath('', $domainRequest))->toBeNull()
        ->and(NotificationUrl::toAppPath(null, $domainRequest))->toBeNull();
});

test('GET /notifications/unread normalise les URL historiques 127.0.0.1 et localhost quand l’utilisateur accède via l’IP LAN', function () {
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\HousekeepingRoomsAssigned',
        'data' => [
            'title' => 'Nouvelles chambres à nettoyer',
            'message' => 'Chambre 101 à traiter.',
            'url' => 'http://127.0.0.1:8082/housekeeping',
        ],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\PurchaseRequestUpdated',
        'data' => [
            'title' => 'Demande d’achat à approuver',
            'message' => 'DA-2026-0002',
            'url' => 'http://localhost:8081/economat/demandes-achat/4?tab=lignes#resume',
        ],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\WebsiteBookingReceived',
        'data' => [
            'title' => 'Réservation en ligne',
            'message' => 'Demande web',
            'url' => 'http://meka-erp-zingana-app/bookings/15',
        ],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\InvalidTest',
        'data' => [
            'title' => 'Lien invalide',
            'message' => 'Test sécurité',
            'url' => 'javascript:alert(1)',
        ],
    ]);

    $response = $this->actingAs($user)
        ->getJson('http://192.168.6.127:8082/notifications/unread')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('total_unread', 4);

    $urls = collect($response->json('notifications'))
        ->pluck('data.url', 'data.title')
        ->all();

    expect($urls['Nouvelles chambres à nettoyer'])->toBe('/housekeeping')
        ->and($urls['Demande d’achat à approuver'])->toBe('/economat/demandes-achat/4?tab=lignes#resume')
        ->and($urls['Réservation en ligne'])->toBe('/bookings/15')
        ->and($urls['Lien invalide'])->toBeNull();
});

test('POST /notifications/{id}/read reste sur l’origine LAN en JSON et en redirection HTTP même si la notification stockait 127.0.0.1', function () {
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $notifJson = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\HousekeepingRoomsAssigned',
        'data' => [
            'title' => 'Nettoyage',
            'message' => 'Chambre 102',
            'url' => 'http://127.0.0.1:8082/housekeeping?floor=1',
        ],
    ]);

    $notifRedirect = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\NewBookingCreated',
        'data' => [
            'title' => 'Réservation',
            'message' => 'Nouvelle réservation',
            'url' => 'http://localhost:8082/bookings/22',
        ],
    ]);

    $notifFallback = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Generic',
        'data' => [
            'title' => 'Info',
            'message' => 'Sans lien',
        ],
    ]);

    $this->actingAs($user)
        ->postJson("http://192.168.6.127:8082/notifications/{$notifJson->id}/read")
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'url' => '/housekeeping?floor=1',
        ]);

    $this->actingAs($user)
        ->post("http://192.168.6.127:8082/notifications/{$notifRedirect->id}/read")
        ->assertRedirect('http://192.168.6.127:8082/bookings/22');

    $this->actingAs($user)
        ->postJson("http://192.168.6.127:8082/notifications/{$notifFallback->id}/read")
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'url' => '/dashboard',
        ]);
});

test('les notifications nouvellement émises enregistrent des chemins relatifs sans APP_URL 127.0.0.1 tout en gardant les liens email absolus', function () {
    config(['app.url' => 'http://127.0.0.1:8082']);

    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $room = Room::first();
    $customer = Customer::factory()->create();
    $booking = Booking::create([
        'booking_number' => 'RES-TEST-001',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDay(),
        'check_out' => now()->addDays(2),
        'total_nights' => 1,
        'price_per_night' => 3500000,
        'total_room_amount' => 3500000,
        'adults_count' => 1,
        'children_count' => 0,
        'total_amount' => 3500000,
        'balance_due' => 3500000,
        'status' => \App\Enums\BookingStatus::CONFIRMED,
        'created_by' => $user->id,
    ]);

    $team = HousekeepingTeam::create(['name' => 'Équipe Matin']);

    $purchaseRequest = PurchaseRequest::create([
        'number' => 'DA-TEST-001',
        'department' => 'housekeeping',
        'priority' => PurchaseRequest::PRIORITY_NORMAL,
        'status' => PurchaseRequest::STATUS_PENDING,
        'requested_by' => $user->id,
        'total_estimated_amount' => 150000,
    ]);

    $conversation = DiscussionConversation::create(['title' => 'Réception', 'created_by' => $user->id]);
    $message = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Client VIP en route.',
    ]);

    $newBooking = new NewBookingCreated($booking, $user->name);
    $webBooking = new WebsiteBookingReceived($booking);
    $hkAssigned = new HousekeepingRoomsAssigned($team, [$room->number]);
    $prUpdated = new PurchaseRequestUpdated($purchaseRequest, PurchaseRequestUpdated::SOUMISE);
    $discReceived = new DiscussionMessageReceived($conversation, $message, $user->name);
    $compRequested = new ComplimentaryBookingRequested($booking);

    foreach ([$newBooking, $webBooking, $hkAssigned, $prUpdated, $discReceived] as $notif) {
        $arr = $notif->toArray($user);
        $push = $notif->toWebPush($user);

        expect($arr['url'])->toStartWith('/')
            ->and($arr['url'])->not->toContain('127.0.0.1')
            ->and($arr['url'])->not->toContain('localhost')
            ->and($push['url'])->toStartWith('/')
            ->and($push['url'])->not->toContain('127.0.0.1')
            ->and($push['url'])->not->toContain('localhost');

        if (isset($push['icon'])) {
            expect($push['icon'])->toBe('/favicon.ico');
        }
    }

    expect($compRequested->toArray($user)['url'])->toBe("/bookings/{$booking->id}");

    $mail = $compRequested->toMail($user);
    expect($mail->viewData['actionUrl'])->toStartWith('http');

    // WebPushChannel normalise également les URL héritées d'un toWebPush().
    $legacyNotification = new class extends Notification {
        public function via(object $notifiable): array
        {
            return ['webpush'];
        }

        public function toWebPush(object $notifiable): array
        {
            return [
                'title' => 'Alerte',
                'body' => 'Test',
                'url' => 'http://127.0.0.1:8082/housekeeping',
                'icon' => 'http://localhost:8082/favicon.ico',
                'badge' => 'http://127.0.0.1:8082/pwa/icon/192',
            ];
        }
    };

    $channel = new WebPushChannel();
    $normalizeMethod = new ReflectionMethod($channel, 'normalizePayload');
    $normalizeMethod->setAccessible(true);
    $normalizedPush = $normalizeMethod->invoke($channel, $legacyNotification->toWebPush($user));

    expect($normalizedPush['url'])->toBe('/housekeeping')
        ->and($normalizedPush['icon'])->toBe('/favicon.ico')
        ->and($normalizedPush['badge'])->toBe('/pwa/icon/192');
});

test('le layout principal et le service worker embarquent la normalisation côté client pour la cloche et Web Push', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoleSeeder::class]);
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $html = $this->actingAs($user)
        ->get('http://192.168.6.127:8082/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('window.resolveAppNotificationUrl')
        ->toContain("fetch('/notifications/unread'")
        ->toContain('const targetUrl = this.normalizeItemUrl((payload && payload.url) || item?.data?.url)')
        ->not->toContain("fetch('http://127.0.0.1");

    $sw = file_get_contents(public_path('sw.js'));
    expect($sw)
        ->toContain('resolveNotificationTargetUrl')
        ->toContain('isInternalAppHost')
        ->toContain('targetUrl.startsWith(self.location.origin)');
});
