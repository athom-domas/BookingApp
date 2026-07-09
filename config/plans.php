<?php

return [
    'base' => [
        'price_id' => env('STRIPE_PRICE_ID_BASE', env('STRIPE_PRICE_ID')),
        'label'    => 'Base',
        'features' => [
            'Gestione appuntamenti',
            'Notifiche email',
            'Portale clienti',
            'Google Calendar sync',
        ],
    ],
    'plus' => [
        'price_id' => env('STRIPE_PRICE_ID_PLUS'),
        'label'    => 'Plus',
        'features' => [
            'Tutto il piano Base',
            'Assistente AI WhatsApp',
            'Prenotazioni via WhatsApp',
            'Cancellazioni via WhatsApp',
        ],
    ],

];
