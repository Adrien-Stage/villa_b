<?php

namespace Database\Seeders;

use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'villa-boutanga')->first();

        if (!$tenant) {
            return;
        }

        // Catégories
        $categories = [
            [
                'name' => 'Sculptures',
                'description' => 'Sculptures traditionnelles et contemporaines',
                'sort_order' => 1,
            ],
            [
                'name' => 'Textiles',
                'description' => 'Tissus, pagnes, et vêtements traditionnels',
                'sort_order' => 2,
            ],
            [
                'name' => 'Bijoux',
                'description' => 'Bijoux artisanaux et perles',
                'sort_order' => 3,
            ],
            [
                'name' => 'Artisanat',
                'description' => 'Objets artisanaux variés',
                'sort_order' => 4,
            ],
            [
                'name' => 'Souvenirs',
                'description' => 'Souvenirs et articles touristiques',
                'sort_order' => 5,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $category = ShopCategory::firstOrCreate(
                [
                    'name' => $categoryData['name'],
                ],
                [
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ]
            );
            $createdCategories[$categoryData['name']] = $category;
        }

        // Produits
        $products = [
            // Sculptures
            [
                'category' => 'Sculptures',
                'name' => 'Masque Bamiléké',
                'description' => 'Masque traditionnel Bamiléké en bois sculpté',
                'sku' => 'SCULP-001',
                'price' => 2500000, // 25000 FCFA
                'stock_quantity' => 5,
            ],
            [
                'category' => 'Sculptures',
                'name' => 'Statue Fang',
                'description' => 'Statue Fang originale 40cm',
                'sku' => 'SCULP-002',
                'price' => 4500000, // 45000 FCFA
                'stock_quantity' => 3,
            ],
            [
                'category' => 'Sculptures',
                'name' => 'Figurine Minsi',
                'description' => 'Petite figurine Minsi en bois',
                'sku' => 'SCULP-003',
                'price' => 1200000, // 12000 FCFA
                'stock_quantity' => 10,
            ],
            // Textiles
            [
                'category' => 'Textiles',
                'name' => 'Pagne Wax Premium',
                'description' => 'Pagne wax 6 mètres premium',
                'sku' => 'TEXT-001',
                'price' => 1500000, // 15000 FCFA
                'stock_quantity' => 15,
            ],
            [
                'category' => 'Textiles',
                'name' => 'Tissu Kente',
                'description' => 'Tissu Kente traditionnel 5 mètres',
                'sku' => 'TEXT-002',
                'price' => 2000000, // 20000 FCFA
                'stock_quantity' => 8,
            ],
            [
                'category' => 'Textiles',
                'name' => 'Ceinture traditionnelle',
                'description' => 'Ceinture tissée traditionnelle',
                'sku' => 'TEXT-003',
                'price' => 800000, // 8000 FCFA
                'stock_quantity' => 12,
            ],
            // Bijoux
            [
                'category' => 'Bijoux',
                'name' => 'Collier perles',
                'description' => 'Collier de perles traditionnelles',
                'sku' => 'BIJOU-001',
                'price' => 600000, // 6000 FCFA
                'stock_quantity' => 20,
            ],
            [
                'category' => 'Bijoux',
                'name' => 'Bracelet or massif',
                'description' => 'Bracelet en or massif artisanal',
                'sku' => 'BIJOU-002',
                'price' => 8000000, // 80000 FCFA
                'stock_quantity' => 4,
            ],
            [
                'category' => 'Bijoux',
                'name' => 'Bague copal ambre',
                'description' => 'Bague en copal ambre naturel',
                'sku' => 'BIJOU-003',
                'price' => 1500000, // 15000 FCFA
                'stock_quantity' => 7,
            ],
            // Artisanat
            [
                'category' => 'Artisanat',
                'name' => 'Pot en céramique',
                'description' => 'Pot traditionnel en céramique faite main',
                'sku' => 'ARTIS-001',
                'price' => 1800000, // 18000 FCFA
                'stock_quantity' => 6,
            ],
            [
                'category' => 'Artisanat',
                'name' => 'Panier tressé',
                'description' => 'Panier tressé Grand modèle',
                'sku' => 'ARTIS-002',
                'price' => 900000, // 9000 FCFA
                'stock_quantity' => 10,
            ],
            // Souvenirs
            [
                'category' => 'Souvenirs',
                'name' => 'Magnet Villa Boutanga',
                'description' => 'Magnet souvenir caché',
                'sku' => 'SOU-001',
                'price' => 150000, // 1500 FCFA
                'stock_quantity' => 50,
            ],
            [
                'category' => 'Souvenirs',
                'name' => 'Carte postale Cameroun',
                'description' => 'Lot de 5 cartes postales',
                'sku' => 'SOU-002',
                'price' => 250000, // 2500 FCFA
                'stock_quantity' => 100,
            ],
        ];

        foreach ($products as $productData) {
            $categoryName = $productData['category'];
            $category = $createdCategories[$categoryName] ?? null;

            if (!$category) {
                continue;
            }

            ShopProduct::firstOrCreate(
                [
                    'sku' => $productData['sku'],
                ],
                [
                    'shop_category_id' => $category->id,
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'price' => $productData['price'],
                    'stock_quantity' => $productData['stock_quantity'],
                    'reorder_level' => 5,
                    'is_active' => true,
                ]
            );
        }
    }
}
