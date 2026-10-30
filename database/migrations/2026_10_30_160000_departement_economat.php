<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le département Économat, présent dès la création d'un établissement : le
 * magasin central a son personnel (économe, magasiniers), son planning de
 * quarts et ses comptes rattachés, comme les autres services.
 *
 * Un établissement qui l'a déjà créé depuis la console — même nom, même
 * identifiant — le garde tel quel : rien n'est dupliqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments') || $this->existe()) {
            return;
        }

        DB::table('departments')->insert([
            'code'        => 'ECO',
            'slug'        => 'economat',
            'name'        => 'Économat',
            'description' => 'Magasin central : réceptions des livraisons, stock, dépôts de service, inventaires et réquisitions des services.',
            'icon'        => 'warehouse',
            'accent'      => 'amber',
            'sort_order'  => 11,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** Déjà là : par son identifiant, ou par son nom, accents et casse mis à part. */
    private function existe(): bool
    {
        if (DB::table('departments')->where('slug', 'economat')->exists()) {
            return true;
        }

        return DB::table('departments')->pluck('name')
            ->contains(fn ($nom) => in_array(mb_strtolower(trim((string) $nom)), ['économat', 'economat'], true));
    }

    public function down(): void
    {
        // Un département créé par la console avant cette migration n'est pas
        // le sien : seul celui qu'elle a posé, sans personne rattachée, part.
        if (Schema::hasTable('departments')) {
            $id = DB::table('departments')->where('slug', 'economat')->where('code', 'ECO')->value('id');

            if ($id && (! Schema::hasColumn('users', 'department_id') || ! DB::table('users')->where('department_id', $id)->exists())) {
                DB::table('departments')->where('id', $id)->delete();
            }
        }
    }
};
