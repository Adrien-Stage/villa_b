<?php

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Bouton « Suggestion » de la barre supérieure : le canal par lequel le
 * personnel remonte un problème au support technique, qui le traite depuis
 * l'ERP. Il est ouvert à tous les rôles — un serveur bloqué doit pouvoir
 * signaler sans passer par son manager, c'est tout l'intérêt du bouton.
 */

function personnel(string $role): User
{
    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

test('n\'importe quel rôle peut ouvrir un ticket', function (string $role) {
    $this->actingAs(personnel($role))
        ->postJson(route('support-tickets.store'), [
            'type'        => 'probleme',
            'subject'     => 'Impossible d\'encaisser',
            'message'     => 'Le bouton reste grisé après avoir choisi le mode de paiement.',
            'context_url' => '/restaurant/billing',
        ])
        ->assertCreated();

    expect(SupportTicket::count())->toBe(1);
})->with(['manager', 'reception', 'restaurant_staff', 'housekeeping_staff', 'econome', 'cashier']);

test('le ticket fige son auteur et part à l\'état « nouveau »', function () {
    $serveur = personnel('restaurant_staff');

    $this->actingAs($serveur)->postJson(route('support-tickets.store'), [
        'type'    => 'suggestion',
        'subject' => 'Trier les commandes par table',
        'message' => 'Ce serait plus rapide au coup d\'œil pendant le service du soir.',
    ])->assertCreated();

    $ticket = SupportTicket::first();

    // L'auteur est recopié : un compte désactivé plus tard ne doit pas rendre
    // le ticket anonyme côté support.
    expect($ticket->author_name)->toBe($serveur->name)
        ->and($ticket->author_role)->toBe('restaurant_staff')
        ->and($ticket->user_id)->toBe($serveur->id)
        ->and($ticket->status)->toBe('nouveau')
        ->and($ticket->type)->toBe('suggestion');
});

test('un ticket vide ou trop court est refusé', function () {
    $this->actingAs(personnel('reception'))
        ->postJson(route('support-tickets.store'), ['type' => 'probleme', 'subject' => 'Bug', 'message' => 'court'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'message']);

    expect(SupportTicket::count())->toBe(0);
});

test('un type inventé est refusé', function () {
    $this->actingAs(personnel('reception'))
        ->postJson(route('support-tickets.store'), [
            'type'    => 'reclamation_client',
            'subject' => 'Objet suffisant',
            'message' => 'Description suffisamment longue pour passer la validation.',
        ])
        ->assertStatus(422);
});

test('chacun ne voit que ses propres tickets', function () {
    $serveur   = personnel('restaurant_staff');
    $collegue  = personnel('reception');

    SupportTicket::create([
        'user_id' => $serveur->id, 'author_name' => $serveur->name, 'author_role' => 'restaurant_staff',
        'type' => 'probleme', 'subject' => 'Le mien', 'message' => 'Message du serveur.', 'status' => 'nouveau',
    ]);
    SupportTicket::create([
        'user_id' => $collegue->id, 'author_name' => $collegue->name, 'author_role' => 'reception',
        'type' => 'probleme', 'subject' => 'Celui du collègue', 'message' => 'Message de la réception.', 'status' => 'nouveau',
    ]);

    $reponse = $this->actingAs($serveur)->getJson(route('support-tickets.index'));

    $reponse->assertOk();
    expect($reponse->json('tickets'))->toHaveCount(1)
        ->and($reponse->json('tickets.0.subject'))->toBe('Le mien');
});

test('le compteur du bouton ne retient que les tickets encore ouverts', function () {
    $auteur = personnel('housekeeping_leader');

    foreach (['nouveau', 'en_cours', 'resolu', 'rejete'] as $statut) {
        SupportTicket::create([
            'user_id' => $auteur->id, 'author_name' => $auteur->name, 'author_role' => 'housekeeping_leader',
            'type' => 'probleme', 'subject' => 'Ticket ' . $statut, 'message' => 'Message de test suffisant.',
            'status' => $statut,
        ]);
    }

    $reponse = $this->actingAs($auteur)->getJson(route('support-tickets.index'));

    // Un badge qui compterait les tickets résolus resterait allumé à vie.
    expect($reponse->json('ouverts'))->toBe(2)
        ->and($reponse->json('tickets'))->toHaveCount(4);
});

test('la réponse du support est renvoyée à l\'auteur avec le statut', function () {
    $auteur = personnel('reception');

    SupportTicket::create([
        'user_id' => $auteur->id, 'author_name' => $auteur->name, 'author_role' => 'reception',
        'type' => 'probleme', 'subject' => 'Caisse bloquée', 'message' => 'Impossible de clôturer la session.',
        'status' => 'resolu', 'reply' => 'Corrigé, pensez à recharger la page.', 'handled_by' => 'Support',
    ]);

    $reponse = $this->actingAs($auteur)->getJson(route('support-tickets.index'));

    expect($reponse->json('tickets.0.reply'))->toBe('Corrigé, pensez à recharger la page.')
        ->and($reponse->json('tickets.0.label'))->toBe('Résolu');
});

test('un visiteur non connecté ne peut pas ouvrir de ticket', function () {
    $this->post(route('support-tickets.store'), [
        'type' => 'probleme', 'subject' => 'Objet suffisant', 'message' => 'Message suffisamment long pour valider.',
    ])->assertRedirect(route('login'));

    expect(SupportTicket::count())->toBe(0);
});
