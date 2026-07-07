<?php

return [
    'public_key'    => env('WOMPI_PUBLIC_KEY', ''),
    'private_key'   => env('WOMPI_PRIVATE_KEY', ''),
    'integrity_key' => env('WOMPI_INTEGRITY_KEY', ''),
    'events_secret' => env('WOMPI_EVENTS_SECRET', ''),
    'api_url'       => env('WOMPI_API_URL', 'https://api.sandbox.wompi.co'),
    'checkout_url'  => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
    'frontend_url'  => env('FRONTEND_URL', 'http://localhost:5173'),

    // Payouts
    'payouts_api_key' => env('WOMPI_PAYOUTS_API_KEY', ''),
    'payouts_user_id' => env('WOMPI_PAYOUTS_USER_ID', ''),
    'payouts_api_url'      => env('WOMPI_PAYOUTS_API_URL', 'https://api.payouts.wompi.co/v1'),
    'payouts_events_secret'    => env('WOMPI_PAYOUTS_EVENTS_SECRET', ''),
    'payouts_fixed_fee'        => env('WOMPI_PAYOUTS_FIXED_FEE', 1849),    
    'payouts_commission_pct'   => env('WOMPI_PAYOUTS_COMMISSION_PCT', 0.4), 
    'payouts_iva_pct'          => env('WOMPI_PAYOUTS_IVA_PCT', 19),         
];
