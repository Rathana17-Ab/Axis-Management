<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared Super Admin Account
    |--------------------------------------------------------------------------
    |
    | These values seed a normal sign-in account with full workspace access.
    | Override them in the deployed .env file to control the shared credentials.
    |
    */

    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
    'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@eocontent.com'),
    'password' => env('SUPER_ADMIN_PASSWORD', 'AxisSuperAdmin##2026'),

];
