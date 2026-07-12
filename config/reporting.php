<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret de service de l'API de reporting business
    |--------------------------------------------------------------------------
    | Jeton bearer partagé entre pms (console business du propriétaire) et
    | chaque application établissement. pms l'envoie dans l'en-tête
    | Authorization pour consommer les données financières agrégées. Injecté
    | dans les containers au provisioning (voir TenantProvisioningService).
    | Vide = API reporting désactivée (tout appel est refusé).
    */
    'secret' => env('REPORTING_SECRET', ''),

];
