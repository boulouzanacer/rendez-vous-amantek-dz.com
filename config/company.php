<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Informations de la société
    |--------------------------------------------------------------------------
    |
    | Ces valeurs alimentent l'en-tête de l'application, les documents
    | (devis, fiches d'intervention) et les coordonnées affichées.
    |
    */

    'name' => env('COMPANY_NAME', 'Amantek'),

    'phone' => env('COMPANY_PHONE', '+213 000 00 00 00'),

    'email' => env('COMPANY_EMAIL', 'contact@amantek-dz.com'),

    'address' => env('COMPANY_ADDRESS', 'Alger, Algérie'),

    /*
    |--------------------------------------------------------------------------
    | Devise et fiscalité
    |--------------------------------------------------------------------------
    |
    | Le dinar algérien est la devise par défaut. Le taux de TVA est appliqué
    | par défaut sur les nouveaux devis.
    |
    */

    'currency' => env('COMPANY_CURRENCY', 'DZD'),

    'currency_symbol' => env('COMPANY_CURRENCY_SYMBOL', 'DA'),

    'tax_rate' => (float) env('COMPANY_TAX_RATE', 19),

];
