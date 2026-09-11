<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | The single unrestricted account, created by OrganizationSeeder. Set these
    | in the environment before seeding a production deployment — the fallback
    | password below exists only so a local `migrate:fresh --seed` works out of
    | the box, and the seeder never overwrites the password of an account that
    | already exists.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Asif Nawaz'),
        'email' => env('SUPER_ADMIN_EMAIL', 'asif@bargoventures.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', '123456789'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial password for seeded employees
    |--------------------------------------------------------------------------
    |
    | Applied on creation only. Everyone is expected to change it at first
    | sign-in; two-factor is on for every seeded account.
    |
    */

    'default_password' => env('SEED_DEFAULT_PASSWORD', '123456789'),

    /*
    |--------------------------------------------------------------------------
    | Legacy accounts to remove
    |--------------------------------------------------------------------------
    |
    | Email domains belonging to superseded demo/seed data. OrganizationSeeder
    | deletes any account on these domains so re-seeding an existing install
    | leaves exactly the roster below. Accounts created by admins through the
    | UI are never touched.
    |
    */

    'purge_domains' => array_filter(explode(',', (string) env('RBAC_PURGE_DOMAINS', 'raqtan.com'))),

];
