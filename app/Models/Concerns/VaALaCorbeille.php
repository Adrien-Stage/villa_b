<?php

namespace App\Models\Concerns;

use App\Services\Corbeille;
use Illuminate\Database\Eloquent\Model;

/**
 * Le modèle passe par la corbeille quand on l'efface : juste avant,
 * il est photographié avec ce qui part avec lui ; une fois l'effacement
 * réussi, la photographie entre dans la corbeille et ses fichiers sont mis
 * de côté. Un effacement refusé par la base n'y laisse rien.
 *
 * Le libellé de l'entrée vient de libelleCorbeille() quand le modèle la
 * définit, sinon de son nom, de son numéro…
 */
trait VaALaCorbeille
{
    /** Photographie prise avant l'effacement, déposée après. */
    public ?array $photoCorbeille = null;

    public static function bootVaALaCorbeille(): void
    {
        static::deleting(function (Model $modele): void {
            $modele->photoCorbeille = app(Corbeille::class)->photographier($modele);
        });

        static::deleted(function (Model $modele): void {
            if ($modele->photoCorbeille !== null) {
                app(Corbeille::class)->deposer($modele, $modele->photoCorbeille);
                $modele->photoCorbeille = null;
            }
        });
    }
}
