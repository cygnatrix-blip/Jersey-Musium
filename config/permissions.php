<?php

/*
 * Admin RBAC permission catalogue.
 * Roles store a JSON array of these keys; the "super_admin" role implicitly has all of them.
 */
return [
    'dashboard.view'    => 'View dashboard & reports',
    'products.manage'   => 'Create / edit products, stock & images',
    'categories.manage' => 'Create / edit categories',
    'orders.view'       => 'View orders',
    'orders.manage'     => 'Update order status, shipments & cancellations',
    'payments.view'     => 'View payment transactions',
    'refunds.manage'    => 'Initiate & process refunds',
    'returns.manage'    => 'Handle return / exchange requests',
    'coupons.manage'    => 'Create / edit promo codes',
    'banners.manage'    => 'Manage offer banners',
    'customers.manage'  => 'View / block customers',
    'reviews.manage'    => 'Moderate product reviews',
    'pages.manage'      => 'Edit CMS pages',
    'settings.manage'   => 'Change store settings',
    'admins.manage'     => 'Manage admin users & roles',
    'audit.view'        => 'View audit log',
];
