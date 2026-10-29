<?php

namespace App\Support\Corbeille;

/**
 * Ce que la corbeille sait des tables de l'établissement : comment nommer ce
 * qu'elle reçoit (type, service) et quelles colonnes désignent un fichier à
 * mettre de côté avec la fiche.
 *
 * Une table absente d'ici passe tout de même par la corbeille quand son
 * modèle porte VaALaCorbeille : elle y figure sous un nom technique.
 */
final class Registre
{
    /** Les services, tels que la console les filtre. */
    public const MODULES = [
        'economat'     => 'Économat',
        'hebergement'  => 'Hébergement',
        'restaurant'   => 'Restauration',
        'boutique'     => 'Boutique',
        'comptabilite' => 'Comptabilité',
        'planning'     => 'Planning',
        'parametres'   => 'Paramètres',
        'personnel'    => 'Personnel & droits',
        'discussions'  => 'Discussions',
    ];

    /** @var array<string, array{0: string, 1: string}> table => [type lisible, service] */
    private const TABLES = [
        'stock_items'                 => ['Article', 'economat'],
        'stock_categories'            => ['Catégorie d\'articles', 'economat'],
        'stock_units'                 => ['Unité de stockage', 'economat'],
        'service_stores'              => ['Dépôt de service', 'economat'],
        'suppliers'                   => ['Fournisseur', 'economat'],
        'goods_receipt_attachments'   => ['Bordereau de livraison', 'economat'],
        'rooms'                       => ['Chambre', 'hebergement'],
        'room_images'                 => ['Photo de chambre', 'hebergement'],
        'room_types'                  => ['Type de chambre', 'hebergement'],
        'room_rates'                  => ['Tarif de chambre', 'hebergement'],
        'room_cost_items'             => ['Poste de fiche technique', 'hebergement'],
        'room_packages'               => ['Pack de séjour', 'hebergement'],
        'folio_items'                 => ['Prestation du folio', 'hebergement'],
        'cancellation_policies'       => ['Politique d\'annulation', 'hebergement'],
        'partner_organizations'       => ['Organisation partenaire', 'hebergement'],
        'service_items'               => ['Prestation du catalogue', 'parametres'],
        'restaurant_menu_categories'  => ['Catégorie de la carte', 'restaurant'],
        'restaurant_menu_items'       => ['Plat de la carte', 'restaurant'],
        'restaurant_pantry_categories' => ['Catégorie du garde-manger', 'restaurant'],
        'restaurant_pantry_items'     => ['Article du garde-manger', 'restaurant'],
        'restaurant_recipes'          => ['Fiche technique', 'restaurant'],
        'restaurant_stock_counts'     => ['Comptage du restaurant', 'restaurant'],
        'shop_products'               => ['Produit de la boutique', 'boutique'],
        'expenses'                    => ['Dépense', 'comptabilite'],
        'work_shifts'                 => ['Quart de travail', 'planning'],
        'shift_assignments'           => ['Affectation au planning', 'planning'],
        'departments'                 => ['Département', 'personnel'],
        'permission_grants'           => ['Exception de droits', 'personnel'],
        'users'                       => ['Compte utilisateur', 'personnel'],
        'discussion_conversations'    => ['Conversation', 'discussions'],
    ];

    /**
     * Colonnes qui désignent un fichier, et leur disque. Valent aussi pour
     * les lignes emportées en cascade (les photos d'une chambre supprimée).
     *
     * @var array<string, array<string, string>>
     */
    private const FICHIERS = [
        'shop_products'             => ['image_path' => 'public'],
        'restaurant_menu_items'     => ['image_path' => 'public'],
        'room_images'               => ['path' => 'public'],
        'room_types'                => ['image_path' => 'public'],
        'expenses'                  => ['receipt_path' => 'public'],
        // Le disque d'un bordereau est écrit sur sa ligne.
        'goods_receipt_attachments' => ['path' => '@disk'],
    ];

    /** Tables jamais photographiées : la corbeille elle-même. */
    public const EXCLUES = ['trash_entries'];

    public static function type(string $table): string
    {
        return self::TABLES[$table][0] ?? $table;
    }

    public static function module(string $table): string
    {
        return self::TABLES[$table][1] ?? 'parametres';
    }

    /** @return array<string, string> colonne => disque (« @disk » : lu sur la ligne) */
    public static function fichiers(string $table): array
    {
        return self::FICHIERS[$table] ?? [];
    }

    /** @return array<string, string> table => type lisible, pour les filtres */
    public static function types(): array
    {
        return array_map(fn (array $t) => $t[0], self::TABLES);
    }
}
