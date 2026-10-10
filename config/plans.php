<?php

/*
| What each subscription plan allows. The plans themselves are the cases of
| App\Enums\Plan; their prices and website wording are in
| config/marketing.php and should be kept in step with this file.
|
| Shown in the platform panel under Plans. The limits are enforced when a
| tenant adds a branch, a POS terminal or a staff account.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | The most a tenant on each plan may have. Null means unlimited. Lowering
    | a limit never removes what a tenant already has; it only stops more
    | being added.
    |
    */

    'limits' => [
        'branches' => [
            'label' => 'Branches',
            'singular' => 'branch',
            'plural' => 'branches',
            'plans' => ['starter' => 1, 'growth' => 5, 'enterprise' => null],
        ],
        'terminals' => [
            'label' => 'POS terminals',
            'singular' => 'POS terminal',
            'plural' => 'POS terminals',
            'plans' => ['starter' => 2, 'growth' => null, 'enterprise' => null],
        ],
        'staff' => [
            'label' => 'Staff accounts',
            'singular' => 'staff account',
            'plural' => 'staff accounts',
            'plans' => ['starter' => 5, 'growth' => null, 'enterprise' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Which plans include each feature. "live" says whether the feature
    | exists in RasaHub yet; the others are what the plan will include.
    |
    */

    'features' => [
        'pos' => [
            'label' => 'POS for dine-in and takeaway',
            'plans' => ['starter', 'growth', 'enterprise'],
            'live' => false,
        ],
        'menus' => [
            'label' => 'Menus, categories and modifiers',
            'plans' => ['starter', 'growth', 'enterprise'],
            'live' => false,
        ],
        'daily_sales_summary' => [
            'label' => 'Daily sales summary',
            'plans' => ['starter', 'growth', 'enterprise'],
            'live' => false,
        ],
        'branch_staff_assignment' => [
            'label' => 'Roles and branch assignment for staff',
            'plans' => ['starter', 'growth', 'enterprise'],
            'live' => true,
        ],
        'multiple_branches' => [
            'label' => 'More than one branch',
            'plans' => ['growth', 'enterprise'],
            'live' => true,
        ],
        'kitchen_display' => [
            'label' => 'Kitchen display',
            'plans' => ['growth', 'enterprise'],
            'live' => false,
        ],
        'table_management' => [
            'label' => 'Table management',
            'plans' => ['growth', 'enterprise'],
            'live' => false,
        ],
        'qr_ordering' => [
            'label' => 'QR ordering',
            'plans' => ['growth', 'enterprise'],
            'live' => false,
        ],
        'branch_pricing_and_reports' => [
            'label' => 'Per-branch pricing and reports',
            'plans' => ['growth', 'enterprise'],
            'live' => false,
        ],
        'inventory' => [
            'label' => 'Inventory across outlets',
            'plans' => ['enterprise'],
            'live' => false,
        ],
        'group_reporting' => [
            'label' => 'Consolidated group reporting',
            'plans' => ['enterprise'],
            'live' => false,
        ],
        'offline_mode' => [
            'label' => 'Offline mode on every terminal',
            'plans' => ['enterprise'],
            'live' => false,
        ],
        'onboarding' => [
            'label' => 'Onboarding and menu migration',
            'plans' => ['enterprise'],
            'live' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    */

    'support' => [
        'starter' => 'Email',
        'growth' => 'Priority',
        'enterprise' => 'Dedicated account manager',
    ],

];
