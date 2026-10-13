<?php
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance();

$orders = $db->fetchAll(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
    [$user['id']]
);

$statusColors = ['pending'=>'badge-yellow','processing'=>'badge-blue','completed'=>'badge-green','cancelled'=>'badge-red','paid'=>'badge-green'];

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-brown">My Orders</h1>
        <p class="text-warm mt-1">Track and manage your orders</p>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <!-- Portal Nav Tabs -->
        <div class="flex gap-2 mb-8 border-b border-gold/20 pb-0">
            <a href="<?= pageUrl('portal') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Dashboard</a>
            <a href="<?= pageUrl('portal.orders') ?>" class="tab-btn active border-b-2 border-brown rounded-b-none pb-3">Orders</a>
            <a href="<?= pageUrl('portal.subscriptions') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Subscriptions</a>
            <a href="<?= pageUrl('portal.profile') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Profile</a>
        </div>

        <?php if (empty($orders)): ?>
        <div class="card p-16 text-center">
            <i class="fa-solid fa-receipt text-5xl text-brown opacity-20 mb-4"></i>
            <h2 class="text-xl font-bold text-brown mb-2">No orders yet</h2>
            <p class="text-warm mb-6">Start shopping to place your first order!</p>
            <a href="<?= pageUrl('shop') ?>" class="btn-primary px-8 py-3">Browse Menu</a>
        </div>
        <?php else: ?>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order):
                        $itemCount = $db->fetchOne("SELECT COUNT(*) as c FROM order_items WHERE order_id = ?", [$order['id']])['c'];
                    ?>
                    <tr>
                        <td class="font-bold text-brown"><?= e($order['order_number']) ?></td>
                        <td class="text-warm text-sm"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                        <td class="text-warm text-sm"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></td>
                        <td class="font-bold text-brown"><?= formatMoney($order['total']) ?></td>
                        <td><span class="badge <?= $statusColors[$order['status']] ?? 'badge-gray' ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td class="text-warm text-sm max-w-xs truncate"><?= $order['delivery_address'] ? e($order['delivery_address']) : 'In-store pickup' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('My Orders', $content);
