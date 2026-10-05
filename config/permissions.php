<?php

/*
|--------------------------------------------------------------------------
| Role -> ability matrix
|--------------------------------------------------------------------------
| Each ability lists the roles allowed to do it. Super Admin is always allowed
| (see AuthorizationServiceProvider), so an empty list means "Super Admin only".
|
| Role names: super_admin, admin, officer (Petugas), viewer (Pimpinan).
| To add a permission, add a line here AND to the matrix in
| tests/Feature/Auth/RoleAccessTest.php.
*/

return [
    'abilities' => [
        'view-dashboard'     => ['admin', 'officer', 'viewer'],

        // Foundation / system
        'manage-users'       => [],
        'manage-settings'    => [],
        'view-activity-logs' => ['admin'],

        // Master data
        'manage-master-data' => ['admin'],             // waste categories + prices
        'manage-households'  => ['admin', 'officer'],
        'manage-customers'   => ['admin', 'officer'],

        // Household survey
        'survey-households'  => ['admin', 'officer'],
        'validate-surveys'   => ['admin'],

        // Transactions, monitoring, reports
        'manage-transactions' => ['admin', 'officer'], // deposits + sales
        'manage-monitoring'  => ['admin'],
        'view-reports'       => ['admin', 'viewer'],
    ],
];
