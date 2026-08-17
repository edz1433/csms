<?php

/*
|--------------------------------------------------------------------------
| Page-level access keys (single source of truth)
|--------------------------------------------------------------------------
| One key per sidebar page. Used by the CheckPageAccess middleware, the
| sidebar View Composer, and the User Management access checkbox list.
| Keep this list in sync with the sidebar nav.
*/

return [

    'pages' => [
        'dashboard',
        'items',
        'inventory',         // Physical inventory (QR stock take)
        'receiving',
        'iars',
        'releasing',
        'suppliers',
        'locations',        // Campuses/Offices
        'units',
        'fund_clusters',
        'account_titles',
        'reports',
        'users',
    ],

    // Human labels for the User Management checkbox list.
    'labels' => [
        'dashboard'      => 'Dashboard',
        'items'          => 'Items / Stocks',
        'inventory'      => 'Physical Inventory',
        'receiving'      => 'Receiving',
        'iars'           => 'IAR',
        'releasing'      => 'Releasing',
        'suppliers'      => 'Suppliers',
        'locations'      => 'Campuses/Offices',
        'units'          => 'Units',
        'fund_clusters'  => 'Fund Clusters',
        'account_titles' => 'Account Titles',
        'reports'        => 'Reports',
        'users'          => 'User Management',
    ],
];
