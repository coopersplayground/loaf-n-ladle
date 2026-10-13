<?php
Auth::requireAdmin();
$db = Database::getInstance();

$totalOrders = $db->fetchOne("SELECT COUNT(*) as c FROM orders")['c'];
$totalRevenue = $db->fetchOne("SELECT SUM(total) as s FROM orders WHERE status NOT IN ('cancelled','pending')")['s'] ?? 0;
$totalCustomers = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'client'")['c'];
$activeSubscriptions = $db->fetchOne("SELECT COUNT(*) as c FROM subscriptions WHERE status = 'active'")['c'];
$pendingOrders = $db->fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'];
$totalProducts = $db->fetchOne("SELECT COUNT(*) as c FROM products WHERE available = 1")['c'];

$recentOrders = $db->fetchAll(
    "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 8"
);
$recentCustomers = $db->fetchAll(
    "SELECT * FROM users WHERE role = 'client' ORDER BY created_at DESC LIMIT 5"
);

$statusColors = ['pending'=>'badge-yellow','processing'=>'badge-blue','completed'=>'badge-green','cancelled'=>'badge-red','paid'=>'badge-green'];

ob_start();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Welcome back! Here's what's happening at Loaf'n Ladle.</p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Total Orders</div>
        <div class="text-3xl font-bold text-brown"><?= $totalOrders ?></div>
        <?php if ($pendingOrders > 0): ?>
        <div class="text-xs text-yellow-600 mt-1"><i class="fa-solid fa-clock mr-1"></i><?= $pendingOrders ?> pending</div>
        <?php endif; ?>
    </div>
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Revenue</div>
        <div class="text-3xl font-bold text-brown"><?= formatMoney($totalRevenue) ?></div>
        <div class="text-xs text-warm mt-1">All time</div>
    </div>
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Customers</div>
        <div class="text-3xl font-bold text-brown"><?= $totalCustomers ?></div>
        <div class="text-xs text-warm mt-1">Registered</div>
    </div>
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Subscriptions</div>
        <div class="text-3xl font-bold text-brown"><?= $activeSubscriptions ?></div>
        <div class="text-xs text-green-600 mt-1">Active</div>
    </div>
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Products</div>
        <div class="text-3xl font-bold text-brown"><?= $totalProducts ?></div>
        <div class="text-xs text-warm mt-1">Available</div>
    </div>
    <div class="card p-5 xl:col-span-1">
        <div class="text-warm text-xs uppercase tracking-wider mb-2">Sub Revenue</div>
        <?php
        $subRevenue = $db->fetchOne("SELECT SUM(p.price_per_week) as s FROM subscriptions s JOIN subscription_plans p ON s.plan_id = p.id WHERE s.status = 'active'")['s'] ?? 0;
        ?>
        <div class="text-3xl font-bold text-brown"><?= formatMoney($subRevenue) ?></div>
        <div class="text-xs text-warm mt-1">Per week</div>
    </div>
</div>

<!-- Stripe Warning -->
<?php if (!StripeService::isConfigured()): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex items-start gap-3">
    <i class="fa-solid fa-triangle-exclamation text-yellow-500 text-xl mt-0.5"></i>
    <div>
        <div class="font-semibold text-yellow-800">Stripe not configured</div>
        <div class="text-yellow-700 text-sm">Customers cannot complete payments until you add your Stripe API keys.</div>
        <a href="<?= pageUrl('admin.stripe') ?>" class="text-yellow-800 font-semibold underline text-sm mt-1 inline-block">Configure Stripe →</a>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Recent Orders -->
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-800">Recent Orders</h2>
            <a href="<?= pageUrl('admin.orders') ?>" class="text-brown text-sm hover:underline">View all</a>
        </div>
        <?php if (empty($recentOrders)): ?>
        <div class="p-8 text-center text-warm">No orders yet.</div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td class="font-semibold text-brown text-sm"><?= e($o['order_number']) ?></td>
                    <td class="text-sm text-warm"><?= e($o['customer_name'] ?? $o['delivery_name'] ?? 'Guest') ?></td>
                    <td class="font-bold text-brown text-sm"><?= formatMoney($o['total']) ?></td>
                    <td><span class="badge <?= $statusColors[$o['status']] ?? 'badge-gray' ?>"><?= ucfirst($o['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Recent Customers -->
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-800">Recent Customers</h2>
            <a href="<?= pageUrl('admin.customers') ?>" class="text-brown text-sm hover:underline">View all</a>
        </div>
        <?php if (empty($recentCustomers)): ?>
        <div class="p-8 text-center text-warm">No customers yet.</div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach ($recentCustomers as $c): ?>
                <tr>
                    <td class="font-semibold text-brown text-sm"><?= e($c['name']) ?></td>
                    <td class="text-sm text-warm"><?= e($c['email']) ?></td>
                    <td class="text-sm text-warm"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
    <a href="<?= pageUrl('admin.product.edit') ?>" class="card p-4 text-center hover:no-underline">
        <i class="fa-solid fa-plus text-brown text-xl mb-2"></i>
        <div class="text-sm font-semibold text-brown">Add Product</div>
    </a>
    <a href="<?= pageUrl('admin.orders') ?>" class="card p-4 text-center hover:no-underline">
        <i class="fa-solid fa-receipt text-brown text-xl mb-2"></i>
        <div class="text-sm font-semibold text-brown">Manage Orders</div>
    </a>
    <a href="<?= pageUrl('admin.stripe') ?>" class="card p-4 text-center hover:no-underline">
        <i class="fa-brands fa-stripe text-brown text-xl mb-2"></i>
        <div class="text-sm font-semibold text-brown">Stripe Settings</div>
    </a>
    <a href="<?= pageUrl('admin.settings') ?>" class="card p-4 text-center hover:no-underline">
        <i class="fa-solid fa-gear text-brown text-xl mb-2"></i>
        <div class="text-sm font-semibold text-brown">Settings</div>
    </a>
</div>
<?php
$content = ob_get_clean();
renderLayout('Admin Dashboard', $content, ['admin' => true]);
