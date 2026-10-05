<?php

namespace App\Support;

/**
 * Le nom des droits, dit en clair.
 *
 * Le catalogue (PermissionCatalog) nomme un droit comme sa route :
 * « economat.orders.send ». C'est exact et stable, mais illisible pour qui
 * règle les accès d'une personne depuis sa fiche. Chaque droit reçoit ici
 * l'écran qu'il concerne et l'action qu'il permet : « Bons de commande —
 * Envoyer au fournisseur ».
 *
 * Un droit ajouté au catalogue doit être nommé ici :
 * PermissionLabelsTest le vérifie. Sans nom, il s'affiche par son code.
 */
class PermissionLabels
{
    /** Services, dans l'ordre où l'application les présente. */
    public const MODULES = [
        'rooms' => 'Chambres', 'bookings' => 'Réservations', 'groups' => 'Groupes', 'agenda' => 'Agenda',
        'customers' => 'Clients', 'reception' => 'POS Réception', 'invoices' => 'Factures',
        'housekeeping' => 'Housekeeping', 'restaurant' => 'Restaurant', 'shop' => 'Boutique',
        'economat' => 'Économat', 'accounting' => 'Comptabilité', 'analytics' => 'Analytique',
        'settings' => 'Paramètres', 'users' => 'Utilisateurs', 'droits' => 'Rôles & droits',
        'interventions' => 'Interventions', 'audit' => "Journal d'audit", 'support' => 'Support',
        'test-popup' => 'Divers',
    ];

    /** Droit => [écran, action]. */
    private const LIBELLES = [
        // ── Comptabilité ──
        'accounting.voir' => ['Comptabilité', 'Consulter'],
        'accounting.cash' => ['Trésorerie', 'Consulter'],
        'accounting.cash_reviews' => ['Contrôle des caisses', 'Consulter'],
        'accounting.cash_reviews.creer' => ['Contrôle des caisses', 'Contresigner une clôture'],
        'accounting.expenses' => ['Dépenses', 'Consulter'],
        'accounting.expenses.creer' => ['Dépenses', 'Saisir'],
        'accounting.expenses.modifier' => ['Dépenses', 'Modifier'],
        'accounting.expenses.supprimer' => ['Dépenses', 'Supprimer'],
        'accounting.income_statement' => ['Compte de résultat', 'Consulter'],
        'accounting.journal' => ['Journal des opérations', 'Consulter'],
        'accounting.revenue_journal' => ['Journal des recettes', 'Consulter'],
        'accounting.receivables' => ['Créances clients', 'Consulter'],
        'accounting.ledger.voir' => ['Comptabilité générale', 'Consulter'],
        'accounting.ledger.accounts' => ['Plan comptable', 'Consulter'],
        'accounting.ledger.aged' => ['Balance âgée', 'Consulter'],
        'accounting.ledger.analytic' => ['Comptabilité analytique', 'Consulter'],
        'accounting.ledger.analytic.margins' => ['Comptabilité analytique', 'Consulter les marges'],
        'accounting.ledger.analytic.mirror' => ['Comptabilité analytique', 'Passer les écritures de reflet'],
        'accounting.ledger.auxiliary' => ['Comptes auxiliaires', 'Consulter'],
        'accounting.ledger.auxiliary.ledger' => ['Comptes auxiliaires', 'Consulter le grand livre auxiliaire'],
        'accounting.ledger.balance' => ['Balance', 'Consulter'],
        'accounting.ledger.general' => ['Grand livre', 'Consulter'],
        'accounting.ledger.journals' => ['Journaux comptables', 'Consulter'],
        'accounting.ledger.entry' => ['Écritures comptables', 'Consulter'],
        'accounting.ledger.entry.reverse' => ['Écritures comptables', 'Contrepasser'],
        'accounting.ledger.night_audit' => ['Audit de nuit', 'Consulter'],
        'accounting.ledger.night_audit.run' => ['Audit de nuit', 'Lancer'],
        'accounting.ledger.opening' => ['À-nouveaux', 'Consulter'],
        'accounting.ledger.opening.creer' => ['À-nouveaux', 'Saisir'],
        'accounting.ledger.periods' => ['Périodes comptables', 'Consulter'],
        'accounting.ledger.periods.lock' => ['Périodes comptables', 'Clôturer'],
        'accounting.ledger.reconcile' => ['Lettrage', 'Consulter'],
        'accounting.ledger.reconcile.auto' => ['Lettrage', 'Lettrer automatiquement'],
        'accounting.ledger.reconcile.undo' => ['Lettrage', 'Délettrer'],
        'accounting.ledger.suppliers' => ['Factures fournisseurs', 'Consulter la liste'],
        'accounting.ledger.suppliers.voir' => ['Factures fournisseurs', 'Consulter une facture'],
        'accounting.ledger.suppliers.creer' => ['Factures fournisseurs', 'Saisir'],
        'accounting.ledger.withholding' => ['Retenues à la source', 'Consulter'],
        'accounting.ledger.years.open' => ['Exercices comptables', 'Ouvrir un exercice'],

        // ── Agenda, analytique, journal ──
        'agenda.voir' => ['Agenda', 'Consulter'],
        'analytics.voir' => ['Tour de contrôle', 'Consulter'],
        'audit.voir' => ["Journal d'audit", 'Consulter'],

        // ── Réservations ──
        'bookings.voir' => ['Réservations', 'Consulter'],
        'bookings.creer' => ['Réservations', 'Créer'],
        'bookings.modifier' => ['Réservations', 'Modifier'],
        'bookings.confirm' => ['Réservations', 'Confirmer'],
        'bookings.approve' => ['Réservations', 'Valider un séjour offert'],
        'bookings.cancel' => ['Réservations', 'Annuler'],
        'bookings.cancellation_receipt' => ['Réservations', "Imprimer l'avis d'annulation"],
        'bookings.checkIn' => ['Séjours', 'Enregistrer une arrivée'],
        'bookings.checkOut' => ['Séjours', 'Enregistrer un départ'],
        'bookings.checkin_code.send' => ['Séjours', "Envoyer le code d'arrivée"],
        'bookings.folio.add' => ['Folio du séjour', 'Ajouter une prestation'],
        'bookings.folio.remove' => ['Folio du séjour', 'Retirer une prestation'],
        'bookings.payment.add' => ['Folio du séjour', 'Encaisser un paiement'],
        'bookings.drafts.voir' => ['Brouillons de réservation', 'Consulter'],
        'bookings.drafts.save' => ['Brouillons de réservation', 'Enregistrer'],
        'bookings.drafts.resume' => ['Brouillons de réservation', 'Rouvrir'],
        'bookings.drafts.continue' => ['Brouillons de réservation', 'Reprendre la saisie'],
        'bookings.drafts.supprimer' => ['Brouillons de réservation', 'Supprimer'],
        'bookings.cash_register.voir' => ['Caisse de la réception', 'Consulter'],
        'bookings.cash_register.open' => ['Caisse de la réception', "Afficher l'ouverture"],
        'bookings.cash_register.open.creer' => ['Caisse de la réception', 'Ouvrir la caisse'],
        'bookings.cash_register.close' => ['Caisse de la réception', 'Afficher la clôture'],
        'bookings.cash_register.close.creer' => ['Caisse de la réception', 'Clôturer la caisse'],
        'bookings.cash_register.disbursements.creer' => ['Caisse de la réception', 'Enregistrer une sortie'],

        // ── Clients ──
        'customers.voir' => ['Clients', 'Consulter'],
        'customers.creer' => ['Clients', 'Créer'],
        'customers.modifier' => ['Clients', 'Modifier'],
        'customers.export' => ['Clients', 'Exporter'],
        'customers.import' => ['Clients', 'Importer'],

        // ── Rôles & droits, interventions, support ──
        'droits.voir' => ['Rôles & droits', 'Consulter'],
        'droits.modifier' => ['Rôles & droits', 'Modifier les droits des rôles'],
        'droits.apercu' => ['Rôles & droits', 'Prévisualiser un changement'],
        'droits.exceptions.creer' => ['Exceptions nominatives', 'Accorder ou retirer un accès'],
        'droits.exceptions.supprimer' => ['Exceptions nominatives', 'Lever une exception'],
        'interventions.voir' => ['Interventions', 'Consulter'],
        'interventions.creer' => ['Interventions', 'Déclarer une intervention'],
        'interventions.terminer' => ['Interventions', 'Terminer une intervention'],
        'support.sessions.voir' => ["Sessions d'assistance", 'Consulter'],

        // ── Économat ──
        'economat.voir' => ['Économat', "Consulter la vue d'ensemble"],
        'economat.categories.voir' => ['Catégories d\'articles', 'Consulter'],
        'economat.categories.creer' => ['Catégories d\'articles', 'Créer'],
        'economat.categories.modifier' => ['Catégories d\'articles', 'Modifier'],
        'economat.categories.supprimer' => ['Catégories d\'articles', 'Supprimer'],
        'economat.items.voir' => ['Articles', 'Consulter'],
        'economat.items.creer' => ['Articles', 'Créer'],
        'economat.items.modifier' => ['Articles', 'Modifier'],
        'economat.items.supprimer' => ['Articles', 'Supprimer'],
        'economat.items.adjust' => ['Articles', 'Ajuster le stock'],
        'economat.items.opening' => ['Articles', 'Saisir le stock initial'],
        'economat.items.export' => ['Articles', 'Exporter'],
        'economat.items.import' => ['Articles', 'Importer'],
        'economat.suppliers.voir' => ['Fournisseurs', 'Consulter'],
        'economat.suppliers.creer' => ['Fournisseurs', 'Créer'],
        'economat.suppliers.modifier' => ['Fournisseurs', 'Modifier'],
        'economat.suppliers.supprimer' => ['Fournisseurs', 'Supprimer'],
        'economat.purchase_requests.voir' => ["Demandes d'achat", 'Consulter'],
        'economat.purchase_requests.creer' => ["Demandes d'achat", 'Créer'],
        'economat.purchase_requests.approve' => ["Demandes d'achat", 'Approuver'],
        'economat.purchase_requests.reject' => ["Demandes d'achat", 'Refuser'],
        'economat.purchase_requests.cancel' => ["Demandes d'achat", 'Annuler'],
        'economat.purchase_requests.convert' => ["Demandes d'achat", 'Transformer en bon de commande'],
        'economat.orders.voir' => ['Bons de commande', 'Consulter'],
        'economat.orders.creer' => ['Bons de commande', 'Créer'],
        'economat.orders.send' => ['Bons de commande', 'Envoyer au fournisseur'],
        'economat.orders.receive' => ['Bons de commande', 'Réceptionner'],
        'economat.orders.cancel' => ['Bons de commande', 'Annuler'],
        'economat.orders.export' => ['Bons de commande', 'Exporter'],
        'economat.receipts.voir' => ["Bons d'entrée", 'Consulter'],
        'economat.receipts.creer' => ["Bons d'entrée", 'Enregistrer une réception'],
        'economat.receipts.cancel' => ["Bons d'entrée", 'Annuler'],
        'economat.receipts.export' => ["Bons d'entrée", 'Exporter'],
        'economat.requisitions.voir' => ['Bons de réquisition', 'Consulter'],
        'economat.requisitions.creer' => ['Bons de réquisition', 'Demander'],
        'economat.requisitions.approve' => ['Bons de réquisition', 'Approuver'],
        'economat.requisitions.reject' => ['Bons de réquisition', 'Refuser'],
        'economat.requisitions.deliver' => ['Bons de réquisition', 'Livrer'],
        'economat.requisitions.cancel' => ['Bons de réquisition', 'Annuler'],
        'economat.requisitions.export' => ['Bons de réquisition', 'Exporter'],
        'economat.stock_counts.voir' => ['Inventaires du magasin', 'Consulter'],
        'economat.stock_counts.creer' => ['Inventaires du magasin', 'Ouvrir un inventaire'],
        'economat.stock_counts.modifier' => ['Inventaires du magasin', 'Saisir le comptage'],
        'economat.stock_counts.close' => ['Inventaires du magasin', 'Clôturer'],
        'economat.stock_counts.cancel' => ['Inventaires du magasin', 'Annuler'],
        'economat.stock_counts.report' => ['Inventaires du magasin', 'Imprimer le procès-verbal'],
        'economat.count_sheets.voir' => ['Fiches de comptage', 'Imprimer'],
        'economat.stores.voir' => ['Dépôts de service', 'Consulter'],
        'economat.stores.creer' => ['Dépôts de service', 'Créer'],
        'economat.stores.modifier' => ['Dépôts de service', 'Modifier'],
        'economat.stores.supprimer' => ['Dépôts de service', 'Supprimer'],
        'economat.stores.counts.voir' => ['Inventaires des dépôts', 'Consulter'],
        'economat.stores.counts.creer' => ['Inventaires des dépôts', 'Ouvrir un inventaire'],
        'economat.stores.counts.modifier' => ['Inventaires des dépôts', 'Saisir le comptage'],
        'economat.stores.counts.close' => ['Inventaires des dépôts', 'Clôturer'],
        'economat.stores.counts.cancel' => ['Inventaires des dépôts', 'Annuler'],
        'economat.control.voir' => ['Contrôle & ratios', 'Consulter'],
        'economat.control.variances.voir' => ['Contrôle & ratios', "Consulter les écarts d'inventaire"],
        'economat.control.suggestions.voir' => ["Propositions d'achat", 'Consulter'],
        'economat.control.suggestions.creer' => ["Propositions d'achat", 'Créer les demandes proposées'],

        // ── Groupes ──
        'groups.voir' => ['Groupes', 'Consulter'],
        'groups.creer' => ['Groupes', 'Créer'],
        'groups.modifier' => ['Groupes', 'Modifier'],
        'groups.cancel' => ['Groupes', 'Annuler'],
        'groups.addRoom' => ['Groupes', 'Ajouter une chambre'],
        'groups.removeRoom' => ['Groupes', 'Retirer une chambre'],
        'groups.checkInAll' => ['Groupes', 'Enregistrer toutes les arrivées'],
        'groups.checkOutAll' => ['Groupes', 'Enregistrer tous les départs'],
        'groups.folio.add' => ['Groupes', 'Ajouter une prestation au folio'],
        'groups.payment.add' => ['Groupes', 'Encaisser un paiement'],
        'groups.invoice' => ['Groupes', 'Éditer la facture'],

        // ── Housekeeping ──
        'housekeeping.voir' => ['Housekeeping', 'Consulter'],
        'housekeeping.assignments.creer' => ['Housekeeping', 'Affecter les chambres'],
        'housekeeping.teams.creer' => ['Housekeeping', 'Composer les équipes'],
        'housekeeping.clean' => ['Housekeeping', 'Signaler une chambre nettoyée'],
        'housekeeping.inspect' => ['Housekeeping', 'Inspecter une chambre'],
        'housekeeping.ready' => ['Housekeeping', 'Valider une chambre prête'],
        'housekeeping.reject' => ['Housekeeping', 'Refuser une chambre'],
        'housekeeping.available' => ['Housekeeping', 'Remettre une chambre en vente'],
        'housekeeping.issue' => ['Housekeeping', 'Signaler un problème'],

        // ── Factures, POS réception ──
        'invoices.voir' => ['Factures', 'Consulter'],
        'reception.pos.voir' => ['POS Réception', 'Consulter'],
        'reception.pos.sales.creer' => ['POS Réception', 'Encaisser une vente'],
        'reception.pos.receipt' => ['POS Réception', 'Imprimer un ticket'],
        'reception.pos.history' => ['POS Réception', "Consulter l'historique"],

        // ── Restaurant ──
        'restaurant.restaurants.voir' => ['Restaurants', 'Consulter'],
        'restaurant.restaurants.creer' => ['Restaurants', 'Créer'],
        'restaurant.restaurants.modifier' => ['Restaurants', 'Modifier (services, modes)'],
        'restaurant.restaurants.spaces.creer' => ['Restaurants', 'Ajouter une salle'],
        'restaurant.restaurants.spaces.modifier' => ['Restaurants', 'Modifier une salle'],
        'restaurant.restaurants.team.modifier' => ['Restaurants', "Composer l'équipe"],
        'restaurant.orders.voir' => ['Commandes', 'Consulter'],
        'restaurant.orders.creer' => ['Commandes', 'Prendre une commande'],
        'restaurant.orders.claim' => ['Commandes', 'Prendre en charge'],
        'restaurant.orders.reassign' => ['Commandes', 'Réaffecter à un serveur'],
        'restaurant.orders.send_to_kitchen' => ['Commandes', 'Envoyer en cuisine'],
        'restaurant.orders.preparing' => ['Commandes', 'Passer en préparation'],
        'restaurant.orders.ready' => ['Commandes', 'Signaler prête'],
        'restaurant.orders.bar_ready' => ['Commandes', 'Signaler les boissons prêtes'],
        'restaurant.orders.served' => ['Commandes', 'Signaler servie'],
        'restaurant.orders.status' => ['Commandes', 'Changer le statut'],
        'restaurant.kitchen.voir' => ['Cuisine', "Consulter l'écran cuisine"],
        'restaurant.bar.voir' => ['Bar', "Consulter l'écran bar"],
        'restaurant.breakfast.voir' => ['Petits-déjeuners', 'Consulter'],
        'restaurant.breakfast.serve' => ['Petits-déjeuners', 'Pointer un petit-déjeuner servi'],
        'restaurant.buffets.voir' => ['Buffets', 'Consulter'],
        'restaurant.buffets.creer' => ['Buffets', 'Ouvrir un buffet'],
        'restaurant.buffets.entries.creer' => ['Buffets', 'Enregistrer une entrée'],
        'restaurant.buffets.close' => ['Buffets', 'Clore un buffet'],
        'restaurant.banquets.voir' => ['Banquets', 'Consulter'],
        'restaurant.banquets.creer' => ['Banquets', 'Créer un devis'],
        'restaurant.banquets.modifier' => ['Banquets', 'Modifier'],
        'restaurant.banquets.status' => ['Banquets', 'Changer le statut'],
        'restaurant.banquets.payments.creer' => ['Banquets', 'Encaisser un acompte ou un solde'],
        'restaurant.menus.voir' => ['Carte', 'Consulter'],
        'restaurant.menus.categories.creer' => ['Carte', 'Créer une catégorie'],
        'restaurant.menus.categories.modifier' => ['Carte', 'Modifier une catégorie'],
        'restaurant.menus.categories.supprimer' => ['Carte', 'Supprimer une catégorie'],
        'restaurant.menus.items.creer' => ['Carte', 'Créer un plat'],
        'restaurant.menus.items.modifier' => ['Carte', 'Modifier un plat'],
        'restaurant.menus.items.supprimer' => ['Carte', 'Supprimer un plat'],
        'restaurant.menus.export' => ['Carte', 'Exporter'],
        'restaurant.menus.import' => ['Carte', 'Importer'],
        'restaurant.recipes.voir' => ['Fiches techniques', 'Consulter'],
        'restaurant.recipes.creer' => ['Fiches techniques', 'Créer'],
        'restaurant.recipes.modifier' => ['Fiches techniques', 'Modifier'],
        'restaurant.recipes.supprimer' => ['Fiches techniques', 'Supprimer'],
        'restaurant.recipes.produce' => ['Fiches techniques', 'Produire une préparation'],
        'restaurant.recipes.export' => ['Fiches techniques', 'Exporter'],
        'restaurant.recipes.import' => ['Fiches techniques', 'Importer'],
        'restaurant.pantry.voir' => ['Garde-manger', 'Consulter'],
        'restaurant.pantry.categories.creer' => ['Garde-manger', 'Créer une catégorie'],
        'restaurant.pantry.categories.modifier' => ['Garde-manger', 'Modifier une catégorie'],
        'restaurant.pantry.categories.supprimer' => ['Garde-manger', 'Supprimer une catégorie'],
        'restaurant.pantry.items.creer' => ['Garde-manger', 'Créer un article'],
        'restaurant.pantry.items.modifier' => ['Garde-manger', 'Modifier un article'],
        'restaurant.pantry.items.supprimer' => ['Garde-manger', 'Supprimer un article'],
        'restaurant.pantry.items.receive' => ['Garde-manger', 'Recevoir une livraison'],
        'restaurant.pantry.movements.creer' => ['Garde-manger', 'Enregistrer un mouvement'],
        'restaurant.pantry.export' => ['Garde-manger', 'Exporter'],
        'restaurant.pantry.import' => ['Garde-manger', 'Importer'],
        'restaurant.stock_counts.voir' => ['Inventaires du restaurant', 'Consulter'],
        'restaurant.stock_counts.creer' => ['Inventaires du restaurant', 'Ouvrir un inventaire'],
        'restaurant.stock_counts.modifier' => ['Inventaires du restaurant', 'Saisir le comptage'],
        'restaurant.stock_counts.close' => ['Inventaires du restaurant', 'Clôturer'],
        'restaurant.stock_counts.supprimer' => ['Inventaires du restaurant', 'Supprimer'],
        'restaurant.waste.voir' => ['Pertes & déchets', 'Consulter'],
        'restaurant.waste.creer' => ['Pertes & déchets', 'Déclarer une perte'],
        'restaurant.consumption.voir' => ['Consommation & ratios', 'Consulter'],
        'restaurant.shifts.open' => ['Services de salle', 'Ouvrir un service'],
        'restaurant.shifts.close' => ['Services de salle', 'Clôturer un service'],
        'restaurant.billing.voir' => ['Facturation', 'Consulter'],
        'restaurant.billing.paid' => ['Facturation', 'Encaisser une note'],
        'restaurant.billing.unpaid' => ['Facturation', 'Annuler un encaissement'],
        'restaurant.billing.receipt' => ['Facturation', 'Imprimer un ticket'],
        'restaurant.cash_register.voir' => ['Caisse du restaurant', 'Consulter'],
        'restaurant.cash_register.open' => ['Caisse du restaurant', "Afficher l'ouverture"],
        'restaurant.cash_register.open.creer' => ['Caisse du restaurant', 'Ouvrir la caisse'],
        'restaurant.cash_register.close' => ['Caisse du restaurant', 'Afficher la clôture'],
        'restaurant.cash_register.close.creer' => ['Caisse du restaurant', 'Clôturer la caisse'],
        'restaurant.cash_register.disbursements.creer' => ['Caisse du restaurant', 'Enregistrer une sortie'],

        // ── Chambres ──
        'rooms.voir' => ['Chambres', 'Consulter'],
        'rooms.creer' => ['Chambres', 'Créer'],
        'rooms.modifier' => ['Chambres', 'Modifier'],
        'rooms.supprimer' => ['Chambres', 'Supprimer'],
        'rooms.updateStatus' => ['Chambres', 'Changer le statut'],
        'rooms.images.supprimer' => ['Chambres', 'Supprimer une photo'],
        'rooms.export' => ['Chambres', 'Exporter'],
        'rooms.import' => ['Chambres', 'Importer'],
        'rooms.types.creer' => ['Types de chambre', 'Créer'],
        'rooms.types.modifier' => ['Types de chambre', 'Modifier'],
        'rooms.types.supprimer' => ['Types de chambre', 'Supprimer'],
        'rooms.types.export' => ['Types de chambre', 'Exporter'],
        'rooms.types.import' => ['Types de chambre', 'Importer'],
        'rooms.cost_sheets.voir' => ['Fiches techniques des chambres', 'Consulter'],
        'rooms.cost_sheets.items.creer' => ['Fiches techniques des chambres', 'Ajouter une ligne'],
        'rooms.cost_sheets.items.modifier' => ['Fiches techniques des chambres', 'Modifier une ligne'],
        'rooms.cost_sheets.items.supprimer' => ['Fiches techniques des chambres', 'Supprimer une ligne'],
        'rooms.cost_sheets.assumptions' => ['Fiches techniques des chambres', 'Régler les hypothèses'],
        'rooms.cost_sheets.starter' => ['Fiches techniques des chambres', 'Charger le modèle de départ'],
        'rooms.cost_sheets.document' => ['Fiches techniques des chambres', 'Imprimer'],
        'rooms.cost_sheets.export' => ['Fiches techniques des chambres', 'Exporter'],
        'rooms.cost_sheets.import' => ['Fiches techniques des chambres', 'Importer'],

        // ── Paramètres ──
        'settings.voir' => ['Paramètres', 'Consulter'],
        'settings.modifier' => ['Paramètres', 'Enregistrer les réglages'],
        'settings.export' => ['Paramètres', 'Exporter'],
        'settings.import' => ['Paramètres', 'Importer'],
        'settings.services.creer' => ['Prestations', 'Créer'],
        'settings.services.modifier' => ['Prestations', 'Modifier'],
        'settings.services.supprimer' => ['Prestations', 'Supprimer'],
        'settings.services.export' => ['Prestations', 'Exporter'],
        'settings.services.import' => ['Prestations', 'Importer'],
        'settings.partners.creer' => ['Partenaires', 'Créer'],
        'settings.partners.modifier' => ['Partenaires', 'Modifier'],
        'settings.partners.supprimer' => ['Partenaires', 'Supprimer'],
        'settings.partners.export' => ['Partenaires', 'Exporter'],
        'settings.partners.import' => ['Partenaires', 'Importer'],
        'settings.packages.creer' => ["Packs d'hébergement", 'Créer'],
        'settings.packages.modifier' => ["Packs d'hébergement", 'Modifier'],
        'settings.packages.supprimer' => ["Packs d'hébergement", 'Supprimer'],
        'settings.packages.export' => ["Packs d'hébergement", 'Exporter'],
        'settings.packages.import' => ["Packs d'hébergement", 'Importer'],
        'settings.cancellation_policies.creer' => ["Politiques d'annulation", 'Créer'],
        'settings.cancellation_policies.modifier' => ["Politiques d'annulation", 'Modifier'],
        'settings.cancellation_policies.supprimer' => ["Politiques d'annulation", 'Supprimer'],
        'settings.cancellation_policies.default' => ["Politiques d'annulation", 'Choisir la politique par défaut'],

        // ── Boutique ──
        'shop.products.voir' => ['Produits', 'Consulter'],
        'shop.products.creer' => ['Produits', 'Créer'],
        'shop.products.modifier' => ['Produits', 'Modifier'],
        'shop.products.supprimer' => ['Produits', 'Supprimer'],
        'shop.products.export' => ['Produits', 'Exporter'],
        'shop.products.import' => ['Produits', 'Importer'],
        'shop.orders.voir' => ['Ventes', 'Consulter'],
        'shop.orders.creer' => ['Ventes', 'Enregistrer une vente'],
        'shop.orders.paid' => ['Ventes', 'Encaisser'],
        'shop.orders.refund' => ['Ventes', 'Rembourser'],
        'shop.orders.receipt' => ['Ventes', 'Imprimer un ticket'],
        'shop.cash_register.voir' => ['Caisse de la boutique', 'Consulter'],
        'shop.cash_register.open' => ['Caisse de la boutique', "Afficher l'ouverture"],
        'shop.cash_register.open.creer' => ['Caisse de la boutique', 'Ouvrir la caisse'],
        'shop.cash_register.close' => ['Caisse de la boutique', 'Afficher la clôture'],
        'shop.cash_register.close.creer' => ['Caisse de la boutique', 'Clôturer la caisse'],
        'shop.cash_register.disbursements.creer' => ['Caisse de la boutique', 'Enregistrer une sortie'],

        // ── Utilisateurs ──
        'users.voir' => ['Personnel', 'Consulter'],
        'users.creer' => ['Personnel', 'Créer un compte'],
        'users.modifier' => ['Personnel', 'Modifier un compte'],
        'users.toggleStatus' => ['Personnel', 'Activer ou désactiver un compte'],

        // ── Divers ──
        'test-popup.voir' => ['Fenêtre de test', 'Consulter'],
    ];

    /** Libellé du service d'un droit. */
    public static function module(string $droit): string
    {
        $prefixe = explode('.', $droit)[0];

        return self::MODULES[$prefixe] ?? $prefixe;
    }

    /** Écran que le droit concerne ; le code faute de nom. */
    public static function ecran(string $droit): string
    {
        return self::LIBELLES[$droit][0] ?? $droit;
    }

    /** Action que le droit permet ; le code faute de nom. */
    public static function action(string $droit): string
    {
        return self::LIBELLES[$droit][1] ?? $droit;
    }

    /** « Bons de commande — Envoyer au fournisseur ». */
    public static function complet(string $droit): string
    {
        return isset(self::LIBELLES[$droit])
            ? self::LIBELLES[$droit][0] . ' — ' . self::LIBELLES[$droit][1]
            : $droit;
    }

    public static function estNomme(string $droit): bool
    {
        return isset(self::LIBELLES[$droit]);
    }

    /**
     * Droits rangés par service puis par écran, dans l'ordre des services.
     *
     * @param  iterable<string>  $droits
     * @return array<string, array<string, list<string>>>  service => écran => droits
     */
    public static function ranger(iterable $droits): array
    {
        $ranges = [];

        foreach ($droits as $droit) {
            $ranges[explode('.', $droit)[0]][self::ecran($droit)][] = $droit;
        }

        // Dans un écran, les actions dans l'ordre où elles sont nommées :
        // consulter d'abord, puis créer, modifier…
        $rang = array_flip(array_keys(self::LIBELLES));
        foreach ($ranges as &$ecrans) {
            foreach ($ecrans as &$liste) {
                usort($liste, fn (string $a, string $b): int => ($rang[$a] ?? PHP_INT_MAX) <=> ($rang[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b));
            }
            unset($liste);
        }
        unset($ecrans);

        $ordre = array_flip(array_keys(self::MODULES));
        uksort($ranges, fn (string $a, string $b): int => ($ordre[$a] ?? 999) <=> ($ordre[$b] ?? 999) ?: strcmp($a, $b));

        $resultat = [];
        foreach ($ranges as $prefixe => $ecrans) {
            $resultat[self::MODULES[$prefixe] ?? $prefixe] = $ecrans;
        }

        return $resultat;
    }
}
