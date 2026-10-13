<?php
Auth::requireAdmin();
$db = Database::getInstance();
$id = (int)get('id');
$order = $db->fetchOne("SELECT o.*, u.name as customer_name, u.email as customer_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?", [$id]);
if (!$order) { flash('error', 'Order not found.'); redirect(pageUrl('admin.orders')); }
$items = $db->fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$id]);
$statusColors = ['pending'=>'badge-yellow','processing'=>'badge-blue','completed'=>'badge-green','cancelled'=>'badge-red','paid'=>'badge-green'];
$statuses = ['pending','processing','paid','completed','cancelled'];

ob_start();
?>
<div class="flex items-center gap-3 mb-6">
    <a href="<?= pageUrl('admin.orders') ?>" class="text-warm hover:text-brown"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Order <?= e($order['order_number']) ?></h1>
        <p class="text-gray-500 text-sm mt-0.5"><?= date('F j, Y \a\t g:ia', strtotime($order['created_at'])) ?></p>
    </div>
    <span class="badge <?= $statusColors[$order['status']] ?? 'badge-gray' ?> ml-2"><?= ucfirst($order['status']) ?></span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Order Items -->
    <div class="lg:col-span-2 space-y-5">
        <div class="card overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h2 class="font-bold text-gray-800">Order Items</h2>
            </div>
            <table class="data-table">
                <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="font-medium text-gray-700"><?= e($item['product_name']) ?></td>
                        <td class="text-warm text-sm"><?= formatMoney($item['product_price']) ?></td>
                        <td class="text-warm text-sm"><?= $item['quantity'] ?></td>
                        <td class="font-bold text-brown"><?= formatMoney($item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="p-5 border-t border-gray-100 bg-gray-50">
                <div class="flex justify-between text-sm text-warm mb-1">
                    <span>Subtotal</span><span><?= formatMoney($order['subtotal']) ?></span>
                </div>
                <div class="flex justify-between text-sm text-warm mb-2">
                    <span>Tax</span><span><?= formatMoney($order['tax']) ?></span>
                </div>
                <div class="flex justify-between font-bold text-brown text-lg">
                    <span>Total</span><span><?= formatMoney($order['total']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($order['notes']): ?>
        <div class="card p-5">
            <h2 class="font-bold text-gray-800 mb-2">Order Notes</h2>
            <p class="text-warm text-sm"><?= e($order['notes']) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($order['stripe_session_id'] || $order['stripe_payment_intent_id']): ?>
        <div class="card p-5">
            <h2 class="font-bold text-gray-800 mb-3">Stripe Details</h2>
            <div class="space-y-2 text-sm">
                <?php if ($order['stripe_session_id']): ?>
                <div class="flex gap-2"><span class="text-warm w-40">Session ID:</span><span class="font-mono text-xs text-gray-600"><?= e($order['stripe_session_id']) ?></span></div>
                <?php endif; ?>
                <?php if ($order['stripe_payment_intent_id']): ?>
                <div class="flex gap-2"><span class="text-warm w-40">Payment Intent:</span><span class="font-mono text-xs text-gray-600"><?= e($order['stripe_payment_intent_id']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-5">
        <!-- Update Status -->
        <div class="card p-5">
            <h2 class="font-bold text-gray-800 mb-3">Update Status</h2>
            <form method="POST" action="<?= pageUrl('admin.orders') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <select name="status" class="form-input mb-3">
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary w-full py-2">Update Status</button>
            </form>
        </div>

        <!-- Customer Info -->
        <div class="card p-5">
            <h2 class="font-bold text-gray-800 mb-3">Customer</h2>
            <div class="space-y-2 text-sm">
                <div><span class="text-warm">Name:</span> <span class="font-medium text-gray-700"><?= e($order['delivery_name'] ?? $order['customer_name'] ?? 'Guest') ?></span></div>
                <div><span class="text-warm">Email:</span> <span class="text-gray-700"><?= e($order['delivery_email'] ?? $order['customer_email'] ?? '—') ?></span></div>
                <?php if ($order['delivery_phone']): ?>
                <div><span class="text-warm">Phone:</span> <span class="text-gray-700"><?= e($order['delivery_phone']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delivery Info -->
        <div class="card p-5">
            <h2 class="font-bold text-gray-800 mb-3">Delivery</h2>
            <div class="text-sm text-gray-700">
                <?= $order['delivery_address'] ? e($order['delivery_address']) : '<span class="text-warm">In-store pickup</span>' ?>
            </div>
        </div>

        <?php if (!empty($order['gift_loaf'])): ?>
        <!-- Loaves of Love Gift -->
        <div class="card p-5 border-l-4 border-amber-400">
            <h2 class="font-bold text-gray-800 mb-3"><i class="fa-solid fa-gift text-amber-500 mr-1"></i> Loaves of Love Gift</h2>
            <div class="space-y-1 text-sm">
                <div><span class="text-warm">Recipient:</span> <span class="font-medium text-gray-700"><?= e($order['gift_recipient_name']) ?></span></div>
                <div><span class="text-warm">Address:</span> <span class="text-gray-700"><?= e($order['gift_recipient_address']) ?></span></div>
                <?php if ($order['gift_note']): ?>
                <div><span class="text-warm">Note:</span> <span class="text-gray-700 italic">"<?= e($order['gift_note']) ?>"</span></div>
                <?php endif; ?>
                <div class="mt-2 text-xs text-amber-700 bg-amber-50 rounded px-2 py-1"><i class="fa-solid fa-heart mr-1"></i>+1 loaf donated to a food pantry</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
renderLayout('Order ' . e($order['order_number']) . ' — Admin', $content, ['admin' => true]);
