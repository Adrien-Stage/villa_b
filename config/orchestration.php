<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret de la console d'orchestration
    |--------------------------------------------------------------------------
    | Jeton bearer que seule la console d'orchestration (wetchah_erp) détient :
    | il ouvre la matrice des droits, les comptes administrateurs et les
    | départements. Distinct de REPORTING_SECRET, que reçoit aussi le module
    | GRC pour lire les données financières : lire des chiffres ne doit pas
    | permettre de se créer un compte administrateur.
    | Injecté au provisioning (voir TenantProvisioningService de l'ERP).
    | Vide = canal d'orchestration fermé.
    */
    'secret' => env('ORCHESTRATION_SECRET', ''),

];
