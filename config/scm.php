<?php

return [
    'upload_max_kb' => (int) env('SCM_UPLOAD_MAX_KB', 10240),
    'currencies' => ['IDR', 'USD', 'SGD', 'EUR'],
    'cbe_vat_percent' => 11,
    'companies' => [
        'APCA' => ['name' => 'APCA', 'number_prefix' => 'APCA-HO', 'logo' => 'img/brands/apca.png', 'logo_alt' => 'APCA Tirta Engineering'],
        'KPMOG' => ['name' => 'KPMOG', 'number_prefix' => 'KPMOG-OFC', 'logo' => 'img/brands/kpmog-request.jpg', 'logo_alt' => 'KPMOG'],
    ],
];
