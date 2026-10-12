<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
| An item is shown only when (1) a route with that name exists and (2) the
| user passes the 'can' ability. So every planned module is listed here already
| and appears automatically once its developer registers the route.
|
| Labels are English translation keys (see lang/id.json).
*/

return [
    ['label' => null, 'items' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'can' => 'view-dashboard'],
    ]],
    ['label' => 'Master Data', 'items' => [
        ['label' => 'Households',       'route' => 'households.index',       'can' => 'manage-households'],
        ['label' => 'Customers',        'route' => 'customers.index',        'can' => 'manage-customers'],
        ['label' => 'Waste Categories', 'route' => 'waste-categories.index', 'can' => 'manage-master-data'],
        ['label' => 'Waste Prices',     'route' => 'waste-prices.index',     'can' => 'manage-master-data'],
    ]],
    ['label' => 'Household Survey', 'items' => [
        ['label' => 'Surveys', 'route' => 'household-surveys.index', 'can' => 'survey-households'],
    ]],
    ['label' => 'Transactions', 'items' => [
        ['label' => 'Deposits', 'route' => 'waste-deposits.index', 'can' => 'manage-transactions'],
        ['label' => 'Sales',    'route' => 'waste-sales.index',    'can' => 'manage-transactions'],
    ]],
    ['label' => 'Monitoring', 'items' => [
        ['label' => 'Waste Reduction', 'route' => 'waste-reductions.index', 'can' => 'manage-monitoring'],
    ]],
    ['label' => 'Reports', 'items' => [
        ['label' => 'Reports', 'route' => 'reports.index', 'can' => 'view-reports'],
    ]],
    ['label' => 'System', 'items' => [
        ['label' => 'Users',         'route' => 'users.index',         'can' => 'manage-users'],
        ['label' => 'Roles',         'route' => 'roles.index',         'can' => 'manage-users'],
        ['label' => 'Settings',      'route' => 'settings.index',      'can' => 'manage-settings'],
        ['label' => 'Activity Logs', 'route' => 'activity-logs.index', 'can' => 'view-activity-logs'],
    ]],
];
