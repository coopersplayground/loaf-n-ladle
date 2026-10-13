<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/stripe.php';

Auth::startSession();

// Initialize DB
Database::getInstance();

$page = get('page', 'shop');

// Route map
$routes = [
    // Public pages (removed home - will be handled by index.html)
    'shop'               => 'pages/shop.php',
    'product'            => 'pages/product.php',
    'cart'               => 'pages/cart.php',
    'checkout'           => 'pages/checkout.php',
    'checkout.success'   => 'pages/checkout_success.php',
    'checkout.cancel'    => 'pages/checkout_cancel.php',
    'subscriptions'      => 'pages/subscriptions.php',
    'subscribe.checkout' => 'pages/subscribe_checkout.php',
    'catering'           => 'pages/catering.php',
    'login'              => 'pages/login.php',
    'register'           => 'pages/register.php',
    'logout'             => 'pages/logout.php',
    'portal'             => 'pages/portal.php',
    'portal.orders'      => 'pages/portal_orders.php',
    'portal.subscriptions'=> 'pages/portal_subscriptions.php',
    'portal.profile'     => 'pages/portal_profile.php',
    // Admin
    'admin'               => 'pages/admin/dashboard.php',
    'admin.products'      => 'pages/admin/products.php',
    'admin.product.edit'  => 'pages/admin/product_edit.php',
    'admin.categories'    => 'pages/admin/categories.php',
    'admin.orders'        => 'pages/admin/orders.php',
    'admin.order.view'    => 'pages/admin/order_view.php',
    'admin.subscriptions' => 'pages/admin/subscriptions.php',
    'admin.customers'     => 'pages/admin/customers.php',
    'admin.settings'      => 'pages/admin/settings.php',
    'admin.stripe'        => 'pages/admin/stripe.php',
    'admin.plans'         => 'pages/admin/plans.php',
    'admin.page_management'=> 'pages/admin/page_management.php',
    'admin.api.products'  => 'pages/admin/api_products.php',
    'admin.bake_list'     => 'pages/admin/bake_list.php',
    'orders.screen'       => 'pages/orders_screen.php',
    'orders.screen.data'  => 'pages/orders_screen_data.php',
    // File uploads
    'uploads'            => 'pages/uploads.php',
    // AJAX / Actions
    'action.cart.add'     => 'actions/cart_add.php',
    'action.cart.update'  => 'actions/cart_update.php',
    'action.cart.remove'  => 'actions/cart_remove.php',
    'action.order.create' => 'actions/order_create.php',
    'webhook.stripe'      => 'actions/webhook_stripe.php',
];

$file = $routes[$page] ?? null;

if ($file && file_exists(__DIR__ . '/' . $file)) {
    require __DIR__ . '/' . $file;
} else {
    // 404
    ob_start();
    ?>
    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <div class="text-8xl font-bold text-brown opacity-20 mb-4">404</div>
            <h1 class="text-2xl font-bold text-brown mb-2">Page Not Found</h1>
            <p class="text-warm mb-6">The page you're looking for doesn't exist.</p>
            <a href="<?= pageUrl('home') ?>" class="btn-primary">Go Home</a>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    renderLayout('Page Not Found', $content);
}
