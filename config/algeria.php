<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wilayas d'Algérie
    |--------------------------------------------------------------------------
    |
    | Liste des 58 wilayas, utilisée pour les formulaires clients et
    | interventions ainsi que pour le filtrage géographique.
    |
    */

    'wilayas' => [
        'Adrar', 'Chlef', 'Laghouat', 'Oum El Bouaghi', 'Batna', 'Béjaïa', 'Biskra',
        'Béchar', 'Blida', 'Bouira', 'Tamanrasset', 'Tébessa', 'Tlemcen', 'Tiaret',
        'Tizi Ouzou', 'Alger', 'Djelfa', 'Jijel', 'Sétif', 'Saïda', 'Skikda',
        'Sidi Bel Abbès', 'Annaba', 'Guelma', 'Constantine', 'Médéa', 'Mostaganem',
        "M'Sila", 'Mascara', 'Ouargla', 'Oran', 'El Bayadh', 'Illizi',
        'Bordj Bou Arreridj', 'Boumerdès', 'El Tarf', 'Tindouf', 'Tissemsilt',
        'El Oued', 'Khenchela', 'Souk Ahras', 'Tipaza', 'Mila', 'Aïn Defla',
        'Naâma', 'Aïn Témouchent', 'Ghardaïa', 'Relizane', "El M'Ghair", 'El Meniaa',
        'Ouled Djellal', 'Bordj Baji Mokhtar', 'Béni Abbès', 'Timimoun', 'Touggourt',
        'Djanet', 'In Salah', 'In Guezzam',
    ],

    /*
    |--------------------------------------------------------------------------
    | Grandes villes par wilaya
    |--------------------------------------------------------------------------
    |
    | Quelques communes de référence pour accélérer la saisie.
    |
    */

    'communes' => [
        'Alger' => ['Bab Ezzouar', 'Dar El Beïda', 'Kouba', 'Hydra', 'Bir Mourad Raïs', 'Chéraga', 'Rouiba', 'Zeralda', 'El Harrach', 'Birtouta'],
        'Blida' => ['Blida', 'Boufarik', 'Larbaâ', 'Ouled Yaïch', 'Bougara', 'Mouzaïa'],
        'Boumerdès' => ['Boumerdès', 'Bordj Menaïel', 'Khemis El Khechna', 'Réghaïa', 'Corso'],
        'Oran' => ['Oran', 'Bir El Djir', 'Es Sénia', 'Aïn El Turk', 'Arzew', 'Gdyel'],
        'Constantine' => ['Constantine', 'El Khroub', 'Hamma Bouziane', 'Aïn Smara', 'Didouche Mourad'],
        'Sétif' => ['Sétif', 'El Eulma', 'Aïn Oulmene', 'Bougaa', 'Aïn Arnat'],
        'Annaba' => ['Annaba', 'El Bouni', 'Sidi Amar', 'El Hadjar', 'Berrahal'],
        'Tizi Ouzou' => ['Tizi Ouzou', 'Azazga', 'Draâ Ben Khedda', 'Tigzirt', 'Boghni'],
        'Béjaïa' => ['Béjaïa', 'Akbou', 'Amizour', 'El Kseur', 'Sidi Aïch'],
        'Tipaza' => ['Tipaza', 'Kolea', 'Hadjout', 'Cherchell', 'Fouka'],
        'Ouargla' => ['Ouargla', 'Hassi Messaoud', 'Touggourt', 'N’Goussa', 'Sidi Khouiled'],
        'Batna' => ['Batna', 'Barika', 'Merouana', 'Aïn Touta', 'Tazoult'],
    ],

];
