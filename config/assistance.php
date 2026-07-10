<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret de vérification des jetons d'assistance
    |--------------------------------------------------------------------------
    | Même valeur que le secret de pms (côté qui signe le jeton). Injecté
    | dans ce container au provisioning via ASSISTANCE_SECRET. Vide = mode
    | assistance désactivé (le endpoint /assistance/enter refuse tout jeton).
    */
    'secret' => env('ASSISTANCE_SECRET', ''),

];
