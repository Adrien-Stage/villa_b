<?php

use App\Models\DiscussionConversation;
use App\Models\DiscussionMessage;
use App\Models\TrashEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    activerModules(['discussions']);
});

function discussionStaff(string $name, string $role = 'reception'): User
{
    return User::factory()->create([
        'name' => $name,
        'role' => $role,
        'is_active' => true,
    ]);
}

function createDirectDiscussion(User $creator, User $recipient): DiscussionConversation
{
    $conversation = DiscussionConversation::create([
        'title' => null,
        'is_group' => false,
        'created_by' => $creator->id,
    ]);

    $conversation->participants()->sync([
        $creator->id => ['last_read_at' => null, 'is_admin' => true, 'archived_at' => null, 'deleted_at' => null],
        $recipient->id => ['last_read_at' => null, 'is_admin' => false, 'archived_at' => null, 'deleted_at' => null],
    ]);

    return $conversation;
}

test("l'interface supprime directement une conversation et propose le choix uniquement sur les messages", function () {
    $alice = discussionStaff('Alice Reception');
    $bob = discussionStaff('Bob Manager', 'manager');
    $conversation = createDirectDiscussion($alice, $bob);

    $msgAlice = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Message envoyé par Alice',
    ]);
    $msgBob = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $bob->id,
        'body' => 'Réponse de Bob',
    ]);

    $response = $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk();

    $html = $response->getContent();

    // Suppression de conversation directe (sans modale de choix)
    expect($html)
        ->toContain('data-action="delete"')
        ->not->toContain('data-action="open-delete-modal"')
        ->not->toContain('id="delete-discussion-modal"')
        ->not->toContain('Supprimer pour tous');

    // Suppression de message individuel avec modale à deux choix
    expect($html)
        ->toContain('id="delete-message-modal"')
        ->toContain('id="delete-message-for-me-btn"')
        ->toContain('id="delete-message-for-all-btn"')
        ->toContain('Uniquement pour moi')
        ->toContain('Pour moi et mon interlocuteur')
        ->toContain(route('discussions.messages.destroy', $msgAlice))
        ->toContain(route('discussions.messages.destroy', $msgBob))
        ->toContain('data-can-delete-for-all="1"')
        ->toContain('data-can-delete-for-all="0"');
});

test("supprimer une conversation l'efface uniquement chez l'initiateur et jamais chez le destinataire", function () {
    $alice = discussionStaff('Alice Reception');
    $bob = discussionStaff('Bob Manager', 'manager');
    $conversation = createDirectDiscussion($alice, $bob);

    DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Client chambre 104 arrivé',
    ]);
    DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $bob->id,
        'body' => 'Bien reçu, merci Alice',
    ]);

    // Même si un appel envoie mode=all manuellement, la suppression reste strictement personnelle.
    $this->actingAs($alice)
        ->deleteJson(route('discussions.conversations.destroy', $conversation), ['mode' => 'all'])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'action' => 'deleted_for_me',
            'removed_conversation_id' => $conversation->id,
        ]);

    expect(DiscussionConversation::find($conversation->id))->not->toBeNull()
        ->and(TrashEntry::count())->toBe(0);

    // Alice ne voit plus la conversation dans son espace
    $this->actingAs($alice)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');

    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertDontSee('Client chambre 104 arrivé')
        ->assertDontSee('Bien reçu, merci Alice');

    // Bob conserve toujours la conversation et tout son historique dans son espace
    $this->actingAs($bob)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conversation->id);

    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Client chambre 104 arrivé')
        ->assertSee('Bien reçu, merci Alice');
});

test("supprimer une conversation de groupe la masque uniquement pour l'initiateur", function () {
    $alice = discussionStaff('Alice');
    $bob = discussionStaff('Bob');
    $charlie = discussionStaff('Charlie');

    $group = DiscussionConversation::create([
        'title' => 'Équipe Réception',
        'is_group' => true,
        'created_by' => $alice->id,
    ]);
    $group->participants()->sync([
        $alice->id => ['is_admin' => true],
        $bob->id => ['is_admin' => false],
        $charlie->id => ['is_admin' => false],
    ]);

    DiscussionMessage::create([
        'conversation_id' => $group->id,
        'user_id' => $alice->id,
        'body' => 'Briefing de 14h confirmé',
    ]);

    $this->actingAs($alice)
        ->deleteJson(route('discussions.conversations.destroy', $group))
        ->assertOk()
        ->assertJsonPath('action', 'deleted_for_me');

    $this->actingAs($alice)->getJson(route('discussions.conversations.list'))->assertJsonCount(0, 'conversations');
    $this->actingAs($bob)->getJson(route('discussions.conversations.list'))->assertJsonCount(1, 'conversations');
    $this->actingAs($charlie)->getJson(route('discussions.conversations.list'))->assertJsonCount(1, 'conversations');
});

test("après suppression d'une conversation, les anciens messages restent masqués pour l'initiateur si le fil reprend", function () {
    $alice = discussionStaff('Alice');
    $bob = discussionStaff('Bob');
    $conversation = createDirectDiscussion($alice, $bob);

    DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Ancien message un',
    ]);
    DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $bob->id,
        'body' => 'Ancien message deux',
    ]);

    // Alice supprime la conversation de son espace
    $this->actingAs($alice)
        ->deleteJson(route('discussions.conversations.destroy', $conversation))
        ->assertOk();

    $this->travel(2)->seconds();

    // Si Alice rouvre une nouvelle discussion avec Bob, l'ancien historique reste vide chez elle
    $this->actingAs($alice)
        ->post(route('discussions.conversations.store'), [
            'is_group' => 0,
            'participant_id' => $bob->id,
        ])
        ->assertRedirect(route('discussions.index', ['conversation' => $conversation->id]));

    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertDontSee('Ancien message un')
        ->assertDontSee('Ancien message deux');

    // Alice resupprime, puis Bob lui envoie un nouveau message
    $this->actingAs($alice)
        ->deleteJson(route('discussions.conversations.destroy', $conversation))
        ->assertOk();

    $this->travel(2)->seconds();

    $this->actingAs($bob)
        ->postJson(route('discussions.store'), [
            'conversation_id' => $conversation->id,
            'body' => 'Nouveau message après suppression',
        ])
        ->assertOk();

    // Alice voit uniquement le nouveau message, sans ressusciter l'historique effacé
    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertDontSee('Ancien message un')
        ->assertDontSee('Ancien message deux')
        ->assertSee('Nouveau message après suppression');

    // Bob garde l'intégralité des 3 messages dans son espace
    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Ancien message un')
        ->assertSee('Ancien message deux')
        ->assertSee('Nouveau message après suppression');
});

test("supprimer un message uniquement pour moi le cache seulement chez moi et le garde chez l'interlocuteur", function () {
    $alice = discussionStaff('Alice');
    $bob = discussionStaff('Bob');
    $conversation = createDirectDiscussion($alice, $bob);

    $msg1 = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Première info correcte',
    ]);
    $msg2 = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Deuxième message à masquer chez moi',
    ]);

    $this->actingAs($alice)
        ->deleteJson(route('discussions.messages.destroy', $msg2), ['mode' => 'me'])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'action' => 'deleted_for_me',
            'removed_message_id' => $msg2->id,
        ]);

    // Alice ne voit plus msg2 ni dans le fil ni dans l'aperçu latéral, ni au poll
    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Première info correcte')
        ->assertDontSee('Deuxième message à masquer chez moi');

    $this->actingAs($alice)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonPath('conversations.0.last_body', 'Première info correcte');

    $this->actingAs($alice)
        ->getJson(route('discussions.conversations.poll', ['conversation' => $conversation->id, 'after_id' => $msg2->id]))
        ->assertOk()
        ->assertJsonPath('removed_message_ids', [$msg2->id]);

    // Bob voit toujours msg2 intact dans son fil et dans l'aperçu latéral
    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Première info correcte')
        ->assertSee('Deuxième message à masquer chez moi');

    $this->actingAs($bob)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonPath('conversations.0.last_body', 'Deuxième message à masquer chez moi');
});

test("supprimer un message pour moi et mon interlocuteur le retire des deux espaces et en direct via le polling", function () {
    $alice = discussionStaff('Alice');
    $bob = discussionStaff('Bob');
    $conversation = createDirectDiscussion($alice, $bob);

    $msg1 = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Message conservé',
    ]);
    $msg2 = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Erreur de frappe envoyée',
    ]);

    // Avant suppression : Bob a 2 messages non lus
    $this->actingAs($bob)
        ->getJson(route('discussions.unreadSummary'))
        ->assertOk()
        ->assertJsonPath('total_unread', 2);

    // Alice supprime msg2 pour elle et son interlocuteur
    $this->actingAs($alice)
        ->deleteJson(route('discussions.messages.destroy', $msg2), ['mode' => 'all'])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'action' => 'deleted_for_all',
            'removed_message_id' => $msg2->id,
        ]);

    // Disparaît chez Alice ET chez Bob
    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Message conservé')
        ->assertDontSee('Erreur de frappe envoyée');

    // Le compteur non lu de Bob baisse à 1 et son aperçu revient au message conservé
    $this->actingAs($bob)
        ->getJson(route('discussions.unreadSummary'))
        ->assertOk()
        ->assertJsonPath('total_unread', 1);

    $this->actingAs($bob)
        ->getJson(route('discussions.conversations.list'))
        ->assertOk()
        ->assertJsonPath('conversations.0.last_body', 'Message conservé');

    // Si Bob avait déjà la fenêtre ouverte sur msg2, le poll signale son retrait
    $this->actingAs($bob)
        ->getJson(route('discussions.conversations.poll', ['conversation' => $conversation->id, 'after_id' => $msg2->id]))
        ->assertOk()
        ->assertJsonPath('removed_message_ids', [$msg2->id]);

    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Message conservé')
        ->assertDontSee('Erreur de frappe envoyée');
});

test("seul l'auteur d'un message peut le supprimer pour tous ; un destinataire ne peut supprimer que pour lui ou reçoit 403", function () {
    $alice = discussionStaff('Alice');
    $bob = discussionStaff('Bob');
    $intrus = discussionStaff('Eve Intrus');
    $conversation = createDirectDiscussion($alice, $bob);

    $msgAlice = DiscussionMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $alice->id,
        'body' => 'Message original d Alice',
    ]);

    // Bob ne peut pas supprimer le message d'Alice pour tous -> 403
    $this->actingAs($bob)
        ->deleteJson(route('discussions.messages.destroy', $msgAlice), ['mode' => 'all'])
        ->assertForbidden();

    // Eve (non-participant) ne peut pas supprimer la conversation ni le message -> 403
    $this->actingAs($intrus)
        ->deleteJson(route('discussions.conversations.destroy', $conversation))
        ->assertForbidden();

    $this->actingAs($intrus)
        ->deleteJson(route('discussions.messages.destroy', $msgAlice), ['mode' => 'me'])
        ->assertForbidden();

    // En revanche, Bob peut supprimer le message reçu uniquement pour lui
    $this->actingAs($bob)
        ->deleteJson(route('discussions.messages.destroy', $msgAlice), ['mode' => 'me'])
        ->assertOk()
        ->assertJsonPath('action', 'deleted_for_me');

    $this->actingAs($bob)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertDontSee('Message original d Alice');

    $this->actingAs($alice)
        ->get(route('discussions.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Message original d Alice');
});
