<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TrashEntry;
use App\Support\Corbeille\Registre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * La corbeille de l'établissement.
 *
 * Ce qu'efface un bouton « Supprimer » n'est pas perdu : juste avant
 * l'effacement, la fiche est photographiée telle qu'elle est en base, avec
 * tout ce qui part avec elle (les lignes que la base efface en cascade) et
 * les liens qu'elle tenait (les lignes que la base détache). Ses fichiers
 * sont mis de côté. La console, par son administrateur TECH, restaure ou
 * purge ; personne dans l'hôtel ne voit la corbeille.
 *
 * Ce qui part avec la fiche se lit dans les clés étrangères de la base, non
 * dans une liste tenue à la main : une table ajoutée demain sera emportée,
 * puis restaurée, sans qu'on y pense.
 */
class Corbeille
{
    /** Au-delà, une cascade est anormale : on s'arrête plutôt que de boucler. */
    private const PROFONDEUR_MAX = 6;

    /** @var array<string, list<array{table: string, colonne: string, cible: string, action: string}>>|null */
    private static ?array $references = null;

    /** @var array<string, list<string>> */
    private static array $colonnes = [];

    /**
     * Photographie la fiche avant son effacement.
     *
     * @return array{racine: array, enfants: list<array{table: string, lignes: list<array>}>, liens: list<array>, fichiers: list<array>}
     */
    public function photographier(Model $modele): array
    {
        $table = $modele->getTable();
        $cle = $modele->getKeyName();
        $ligne = (array) DB::table($table)->where($cle, $modele->getKey())->first();

        $photo = [
            'racine'   => ['table' => $table, 'cle' => $cle, 'ligne' => $ligne],
            'enfants'  => [],
            'liens'    => [],
            'fichiers' => $this->fichiersDe($table, [$ligne]),
        ];

        if ($ligne !== []) {
            $this->descendre($table, [$ligne], $photo, 0);
        }

        return $photo;
    }

    /** La fiche est effacée : sa photographie entre dans la corbeille, ses fichiers sont mis de côté. */
    public function deposer(Model $modele, array $photo): TrashEntry
    {
        $auteur = Auth::user();
        $table = $photo['racine']['table'];

        $entree = TrashEntry::create([
            'table_name'      => $table,
            'record_id'       => $modele->getKey(),
            'type'            => Registre::type($table),
            'module'          => Registre::module($table),
            'label'           => mb_substr($this->libelle($modele), 0, 255),
            'payload'         => $photo,
            'deleted_by'      => $auteur?->id,
            'deleted_by_name' => $auteur?->name ?? 'Console',
            'deleted_at'      => now(),
        ]);

        // Les fichiers vont dans corbeille/{entrée}/, sur leur disque : un
        // fichier qui manque déjà ne bloque pas la suppression.
        $fichiers = [];
        foreach ($photo['fichiers'] as $fichier) {
            $rangement = "corbeille/{$entree->id}/{$fichier['chemin']}";
            try {
                $disque = Storage::disk($fichier['disque']);
                if ($disque->exists($fichier['chemin'])) {
                    $disque->move($fichier['chemin'], $rangement);
                    $fichier['corbeille'] = $rangement;
                }
            } catch (\Throwable $e) {
                Log::warning("Corbeille : fichier {$fichier['chemin']} non mis de côté — {$e->getMessage()}");
            }
            $fichiers[] = $fichier;
        }
        if ($fichiers !== []) {
            $entree->update(['payload' => ['fichiers' => $fichiers] + $photo]);
        }

        return $entree;
    }

    /**
     * Remet la fiche telle qu'elle était : elle, ce qui était parti avec
     * elle, ses liens et ses fichiers. Refuse, avec le motif, ce que la base
     * n'accepte plus : un élément du même nom recréé depuis, un élément dont
     * elle dépendait et qui est lui-même dans la corbeille.
     */
    public function restaurer(TrashEntry $entree, string $par): TrashEntry
    {
        if ($entree->restored_at !== null) {
            throw new RuntimeException('Cette entrée a déjà été restaurée.');
        }

        $photo = $entree->payload;
        $racine = $photo['racine'];

        if (DB::table($racine['table'])->where($racine['cle'], $racine['ligne'][$racine['cle']] ?? null)->exists()) {
            throw new RuntimeException('Cet élément existe de nouveau dans l\'établissement : il n\'y a rien à restaurer.');
        }

        try {
            DB::transaction(function () use ($photo, $racine) {
                $this->inserer($racine['table'], [$racine['ligne']]);
                foreach ($photo['enfants'] as $groupe) {
                    $this->inserer($groupe['table'], $groupe['lignes']);
                }
                // Les lignes qu'elle tenait la retrouvent, si elles ne sont
                // pas rattachées ailleurs depuis.
                foreach ($photo['liens'] as $lien) {
                    foreach ($lien['paires'] as [$id, $valeur]) {
                        DB::table($lien['table'])->where('id', $id)->whereNull($lien['colonne'])
                            ->update([$lien['colonne'] => $valeur]);
                    }
                }
            });
        } catch (QueryException $e) {
            throw new RuntimeException($this->motifDuRefus($e), 0, $e);
        }

        foreach ($photo['fichiers'] as $fichier) {
            if (empty($fichier['corbeille'])) {
                continue;
            }
            $disque = Storage::disk($fichier['disque']);
            if ($disque->exists($fichier['corbeille']) && ! $disque->exists($fichier['chemin'])) {
                $disque->move($fichier['corbeille'], $fichier['chemin']);
            }
        }

        $entree->update(['restored_at' => now(), 'restored_by' => $par]);

        AuditLog::record(null, 'corbeille_restauration',
            "{$entree->type} « {$entree->label} » restauré depuis la corbeille par {$par}",
            $entree->module, ['trash_entry_id' => $entree->id, 'table' => $entree->table_name, 'id' => $entree->record_id]);

        return $entree->fresh();
    }

    /** Efface pour de bon une entrée et ses fichiers mis de côté. */
    public function purger(TrashEntry $entree, string $par): void
    {
        foreach ($entree->payload['fichiers'] ?? [] as $fichier) {
            if (! empty($fichier['corbeille'])) {
                Storage::disk($fichier['disque'])->delete($fichier['corbeille']);
            }
        }

        AuditLog::record(null, 'corbeille_purge',
            "{$entree->type} « {$entree->label} » purgé de la corbeille par {$par}",
            $entree->module, ['trash_entry_id' => $entree->id, 'table' => $entree->table_name, 'id' => $entree->record_id]);

        $entree->delete();
    }

    // ── Photographie ────────────────────────────────────────────────────────

    /**
     * Descend les clés étrangères qui pointent vers ces lignes : ce que la
     * base efface en cascade est photographié (puis ses propres enfants),
     * ce qu'elle détache est noté, pour être rattaché à la restauration.
     *
     * @param  list<array>  $lignes
     */
    private function descendre(string $table, array $lignes, array &$photo, int $profondeur): void
    {
        if ($profondeur >= self::PROFONDEUR_MAX) {
            return;
        }

        foreach ($this->references()[$table] ?? [] as $ref) {
            if (in_array($ref['table'], Registre::EXCLUES, true)) {
                continue;
            }

            $valeurs = array_values(array_filter(array_column($lignes, $ref['cible']), fn ($v) => $v !== null));
            if ($valeurs === []) {
                continue;
            }

            $enfants = DB::table($ref['table'])->whereIn($ref['colonne'], $valeurs)->get()->map(fn ($l) => (array) $l)->all();
            if ($enfants === []) {
                continue;
            }

            if ($ref['action'] === 'cascade') {
                $photo['enfants'][] = ['table' => $ref['table'], 'lignes' => $enfants];
                array_push($photo['fichiers'], ...$this->fichiersDe($ref['table'], $enfants));
                $this->descendre($ref['table'], $enfants, $photo, $profondeur + 1);
            } elseif ($ref['action'] === 'set null' && array_key_exists('id', $enfants[0])) {
                $photo['liens'][] = [
                    'table'   => $ref['table'],
                    'colonne' => $ref['colonne'],
                    'paires'  => array_map(fn (array $e) => [$e['id'], $e[$ref['colonne']]], $enfants),
                ];
            }
        }
    }

    /**
     * Les tables qui pointent vers chaque table, lues dans la base une fois
     * par processus.
     *
     * @return array<string, list<array{table: string, colonne: string, cible: string, action: string}>>
     */
    private function references(): array
    {
        if (self::$references !== null) {
            return self::$references;
        }

        $references = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $cle) {
                if (count($cle['columns']) !== 1) {
                    continue;
                }
                $references[$cle['foreign_table']][] = [
                    'table'   => $table,
                    'colonne' => $cle['columns'][0],
                    'cible'   => $cle['foreign_columns'][0],
                    'action'  => (string) $cle['on_delete'],
                ];
            }
        }

        return self::$references = $references;
    }

    /**
     * @param  list<array>  $lignes
     * @return list<array{disque: string, chemin: string}>
     */
    private function fichiersDe(string $table, array $lignes): array
    {
        $fichiers = [];
        foreach (Registre::fichiers($table) as $colonne => $disque) {
            foreach ($lignes as $ligne) {
                if (! empty($ligne[$colonne])) {
                    $fichiers[] = [
                        'disque' => $disque === '@disk' ? ($ligne['disk'] ?? 'local') : $disque,
                        'chemin' => (string) $ligne[$colonne],
                    ];
                }
            }
        }

        return $fichiers;
    }

    private function libelle(Model $modele): string
    {
        if (method_exists($modele, 'libelleCorbeille')) {
            return $modele->libelleCorbeille();
        }

        foreach (['name', 'label', 'title', 'number', 'reference', 'original_name', 'description'] as $champ) {
            if (! empty($modele->getAttribute($champ))) {
                return (string) $modele->getAttribute($champ);
            }
        }

        return '#' . $modele->getKey();
    }

    // ── Restauration ────────────────────────────────────────────────────────

    /** @param  list<array>  $lignes */
    private function inserer(string $table, array $lignes): void
    {
        // Une colonne retirée depuis la suppression ne bloque pas la restauration.
        $colonnes = self::$colonnes[$table] ??= Schema::getColumnListing($table);
        foreach ($lignes as $ligne) {
            DB::table($table)->insert(array_intersect_key($ligne, array_flip($colonnes)));
        }
    }

    private function motifDuRefus(QueryException $e): string
    {
        $code = (string) ($e->errorInfo[0] ?? $e->getCode());
        $message = $e->getMessage();

        if ($code === '23505' || str_contains($message, 'UNIQUE constraint failed')) {
            return "Un élément identique (même nom ou même code) existe de nouveau dans l'établissement : renommez-le ou supprimez-le, puis restaurez.";
        }
        if ($code === '23503' || str_contains($message, 'FOREIGN KEY constraint failed')) {
            return "Il dépend d'un élément qui n'existe plus (sa catégorie, son fournisseur, sa chambre…) : restaurez d'abord celui-ci depuis la corbeille.";
        }

        return 'La base a refusé la restauration : ' . mb_substr($message, 0, 200);
    }
}
