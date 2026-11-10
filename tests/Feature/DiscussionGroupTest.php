<?php

use App\Models\DiscussionConversation;
use App\Models\User;
use App\Notifications\DiscussionMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    activerModules(['discussions']);
});

function groupUser(string $name, string $role = 'reception'): User
{
    return User::factory()->create([
        'name' => $name,
        'role' => $role,
        'is_active' => true,
    ]);
}

test("l'écran discussions expose la création de groupe avec champ is_group et recherche à la saisie des membres", function () {
    $alice = groupUser('Alice');
    $bob = groupUser('Bob Réception');
    $charlie = groupUser('Charlie Restaurant', 'restaurant_staff');

    $html = $this->actingAs($alice)
        ->get(route('discussions.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('id="new-group-header-btn"')
        ->toContain('openNewDiscussionModal(true)')
        ->toContain('id="tab-group-discussion"')
        ->toContain('id="group-title-input"')
        ->toContain('id="group-member-search-input"')
        ->toContain('groupMemberPicker(')
        ->toContain('name="participant_ids[]"')
        ->toContain($bob->name)
        ->toContain($charlie->name);

    // Vérifie que <input type="hidden" name="is_group"...> est bien APRES <div x-data=...>
    $xDataPos = strpos($html, 'x-data="{');
    $hiddenIsGroupPos = strpos($html, 'name="is_group"');

    expect($xDataPos)->not->toBeFalse()
        ->and($hiddenIsGroupPos)->not->toBeFalse()
        ->and($hiddenIsGroupPos)->toBeGreaterThan($xDataPos);
});

test("créer un groupe fonctionne avec is_group='1' et rend le groupe visible à tous ses membres", function () {
    $alice = groupUser('Alice Admin', 'manager');
    $bob = groupUser('Bob Réception');
    $charlie = groupUser('Charlie Restaurant', 'restaurant_staff');
    $outsider = groupUser('Diane Boutique', 'shop_cashier');

    $response = $this->actingAs($alice)
        ->post(route('discussions.conversations.store'), [
            'is_group' => '1',
            'title' => '  Coordination Hôtel  ',
            'participant_ids' => [$bob->id, $charlie->id],
        ]);

    $conversation = DiscussionConversation::where('is_group', true)->sole();

    $response->assertRedirect(route('discussions.index', ['conversation' => $conversation->id]));

    expect($conversation->title)->toBe('Coordination Hôtel')
        ->and($conversation->is_group)->toBeTrue()
        ->and($conversation->participants()->count())->toBe(3);

    $alicePivot = $conversation->participants()->where('users.id', $alice->id)->first()?->pivot;
    $bobPivot = $conversation->participants()->where('users.id', $bob->id)->first()?->pivot;

    expect((bool) $alicePivot?->is_admin)->toBeTrue()
        ->and((bool) $bobPivot?->is_admin)->toBeFalse();

    // Liste des conversations côté Alice et Bob (badge Groupe + compteur membres)
    $this->actingAs($alice)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.title', 'Coordination Hôtel')
        ->assertJsonPath('conversations.0.is_group', true)
        ->assertJsonPath('conversations.0.participants_count', 3);

    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Coordination Hôtel')
        ->assertSee('3 membres')
        ->assertSee('Membres')
        ->assertDontSee('Gérer le groupe');

    // Outsider n'a pas accès au groupe
    $this->actingAs($outsider)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');

    $this->actingAs($outsider)
        ->postJson(route('discussions.store'), [
            'conversation_id' => $conversation->id,
            'body' => 'Intrus',
        ])
        ->assertForbidden();
});

test("la validation de création rejette proprement nom vide, groupe sans membres ou discussion 1-à-1 sans destinataire", function () {
    $alice = groupUser('Alice');
    $bob = groupUser('Bob');

    // Nom manquant en mode groupe : conserve les membres déjà sélectionnés au réaffichage
    $this->actingAs($alice)
        ->from(route('discussions.index'))
        ->post(route('discussions.conversations.store'), [
            'is_group' => '1',
            'title' => '   ',
            'participant_ids' => [$bob->id],
        ])
        ->assertSessionHasErrors('title');

    $retryHtml = $this->actingAs($alice)
        ->withSession(['_old_input' => ['is_group' => '1', 'participant_ids' => [(string) $bob->id]]])
        ->get(route('discussions.index'))
        ->assertOk()
        ->getContent();

    expect($retryHtml)->toContain('groupMemberPicker(')
        ->toContain('[' . $bob->id . ']');

    // Aucun autre membre sélectionné
    $this->actingAs($alice)
        ->post(route('discussions.conversations.store'), [
            'is_group' => '1',
            'title' => 'Équipe Test',
            'participant_ids' => [],
        ])
        ->assertSessionHasErrors('participant_ids');

    // Mode privé sans participant_id (avec is_group='0' envoyé par le formulaire)
    $this->actingAs($alice)
        ->post(route('discussions.conversations.store'), [
            'is_group' => '0',
            'participant_id' => '',
        ])
        ->assertSessionHasErrors('participant_id');

    expect(DiscussionConversation::count())->toBe(0);
});

test("l'envoi d'un message dans un groupe notifie tous les autres membres et met à jour les compteurs", function () {
    $alice = groupUser('Alice');
    $bob = groupUser('Bob');
    $charlie = groupUser('Charlie');

    $this->actingAs($alice)->post(route('discussions.conversations.store'), [
        'is_group' => '1',
        'title' => 'Briefing Matin',
        'participant_ids' => [$bob->id, $charlie->id],
    ])->assertRedirect();

    $group = DiscussionConversation::firstOrFail();

    $this->actingAs($alice)
        ->postJson(route('discussions.store'), [
            'conversation_id' => $group->id,
            'body' => 'VIP suite 201 à préparer avant midi.',
        ])
        ->assertOk();

    Notification::assertSentTo($bob, DiscussionMessageReceived::class);
    Notification::assertSentTo($charlie, DiscussionMessageReceived::class);
    Notification::assertNotSentTo($alice, DiscussionMessageReceived::class);

    $this->actingAs($bob)
        ->getJson(route('discussions.unreadSummary'))
        ->assertOk()
        ->assertJsonPath('total_unread', 1);

    $this->actingAs($charlie)
        ->get(route('discussions.index', ['conversation' => $group->id]))
        ->assertOk()
        ->assertSee('VIP suite 201 à préparer avant midi.');
});

test("l'administrateur du groupe peut le renommer, ajouter ou retirer des membres ; un non-admin ne le peut pas", function () {
    $alice = groupUser('Alice');
    $bob = groupUser('Bob');
    $charlie = groupUser('Charlie');

    $this->actingAs($alice)->post(route('discussions.conversations.store'), [
        'is_group' => '1',
        'title' => 'Ancien Nom',
        'participant_ids' => [$bob->id],
    ])->assertRedirect();

    $group = DiscussionConversation::firstOrFail();

    // L'admin voit la modale de gestion du groupe et le champ de recherche à la saisie pour ajouter des membres
    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $group->id]))
        ->assertOk()
        ->assertSee('Gérer le groupe')
        ->assertSee('id="manage-group-modal"', false)
        ->assertSee('id="manage-group-member-search-input"', false);

    // Non-admin refusé sur renommage / ajout / retrait -> 403
    $this->actingAs($bob)
        ->patch(route('discussions.conversations.update', $group), ['title' => 'Pirate'])
        ->assertForbidden();

    $this->actingAs($bob)
        ->post(route('discussions.conversations.members.store', $group), ['participant_ids' => [$charlie->id]])
        ->assertForbidden();

    $this->actingAs($bob)
        ->delete(route('discussions.conversations.members.destroy', [$group, $alice]))
        ->assertForbidden();

    // Admin renomme le groupe et ajoute Charlie
    $this->actingAs($alice)
        ->patch(route('discussions.conversations.update', $group), ['title' => 'Équipe Gouvernance'])
        ->assertRedirect(route('discussions.index', ['conversation' => $group->id]));

    $this->actingAs($alice)
        ->post(route('discussions.conversations.members.store', $group), ['participant_ids' => [$charlie->id]])
        ->assertRedirect(route('discussions.index', ['conversation' => $group->id]));

    expect($group->fresh()->title)->toBe('Équipe Gouvernance')
        ->and($group->participants()->count())->toBe(3);

    // Charlie voit bien le groupe
    $this->actingAs($charlie)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(1, 'conversations');

    // Admin retire Charlie
    $this->actingAs($alice)
        ->delete(route('discussions.conversations.members.destroy', [$group, $charlie]))
        ->assertRedirect(route('discussions.index', ['conversation' => $group->id]));

    expect($group->participants()->count())->toBe(2);

    $this->actingAs($charlie)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');

    $this->actingAs($charlie)
        ->getJson(route('discussions.conversations.poll', $group))
        ->assertForbidden();
});

test("si l'administrateur quitte le groupe, un autre membre restant hérite automatiquement du rôle d'admin", function () {
    $alice = groupUser('Alice');
    $bob = groupUser('Bob');
    $charlie = groupUser('Charlie');

    $this->actingAs($alice)->post(route('discussions.conversations.store'), [
        'is_group' => '1',
        'title' => 'Service Étage',
        'participant_ids' => [$bob->id, $charlie->id],
    ])->assertRedirect();

    $group = DiscussionConversation::firstOrFail();

    $this->actingAs($alice)
        ->post(route('discussions.conversations.leave', $group))
        ->assertRedirect(route('discussions.index'));

    expect($group->participants()->where('users.id', $alice->id)->exists())->toBeFalse()
        ->and($group->participants()->wherePivot('is_admin', true)->exists())->toBeTrue();

    // L'ancien créateur n'a plus accès au groupe
    $this->actingAs($alice)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');
});
