<?php
Auth::requireAdmin();
$db = Database::getInstance();

if (isPost() && post('action') === 'update_status') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.subscriptions')); }
    $id = (int)post('sub_id');
    $status = sanitize(post('status'));
    $allowed = ['active','paused','cancelled','past_due'];
    if (in_array($status, $allowed)) {
        $db->execute("UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$status, $id]);
        flash('success', 'Subscription updated.');
    }
    redirect(pageUrl('admin.subscriptions'));
}

$statusFilter = get('status', '');
$where = "1=1";
$params = [];
if ($statusFilter) { $where .= " AND s.status = ?"; $params[] = $statusFilter; }

$subscriptions = $db->fetchAll(
    "SELECT s.*, u.name as customer_name, u.email as customer_email, p.name as plan_name, p.price_per_week, p.loaves_per_week,
            dd.day_name as delivery_day_name
     FROM subscriptions s
     JOIN users u ON s.user_id = u.id
     JOIN subscription_plans p ON s.plan_id = p.id
     LEFT JOIN delivery_days dd ON s.delivery_day_id = dd.id
     WHERE $where ORDER BY s.created_at DESC",
    $params
);

$statusColors = ['active'=>'badge-green','paused'=>'badge-yellow','cancelled'=>'badge-red','past_due'=>'badge-red'];
$statuses = ['active','paused','cancelled','past_due'];

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Subscriptions</h1>
        <p class="text-gray-500 text-sm mt-1"><?= count($subscriptions) ?> subscriptions</p>
    </div>
</div>

<!-- Status Filters -->
<div class="flex gap-2 flex-wrap mb-5">
    <a href="<?= pageUrl('admin.subscriptions') ?>" class="<?= !$statusFilter ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5">All</a>
    <?php foreach ($statuses as $s): ?>
    <a href="<?= pageUrl('admin.subscriptions', ['status' => $s]) ?>" class="<?= $statusFilter === $s ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5 capitalize"><?= str_replace('_',' ',$s) ?></a>
    <?php endforeach; ?>
</div>

<div class="card overflow-hidden">
    <?php if (empty($subscriptions)): ?>
    <div class="p-12 text-center text-warm">
        <i class="fa-solid fa-rotate text-4xl mb-3 opacity-30"></i>
        <p>No subscriptions found.</p>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Customer</th><th>Plan</th><th>Price/Week</th><th>Breads</th><th>Delivery Day</th><th>Address</th><th>Started</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($subscriptions as $sub): ?>
            <tr>
                <td>
                    <div class="font-semibold text-brown text-sm"><?= e($sub['customer_name']) ?></div>
                    <div class="text-warm text-xs"><?= e($sub['customer_email']) ?></div>
                </td>
                <td>
                    <div class="font-medium text-gray-700 text-sm"><?= e($sub['plan_name']) ?></div>
                    <div class="text-warm text-xs"><?= $sub['loaves_per_week'] ?> loaves/week</div>
                </td>
                <td class="font-bold text-brown"><?= formatMoney($sub['price_per_week']) ?></td>
                <td class="text-warm text-xs max-w-xs truncate"><?= e($sub['bread_selections'] ?? '—') ?></td>
                <td>
                    <?php if ($sub['delivery_day_name']): ?>
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded"><?= e($sub['delivery_day_name']) ?></span>
                    <?php else: ?>
                    <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-warm text-xs max-w-xs truncate"><?= $sub['delivery_address'] ? e($sub['delivery_address']) : 'Pickup' ?></td>
                <td class="text-warm text-sm"><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                <td>
                    <form method="POST" action="<?= pageUrl('admin.subscriptions') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="form-input py-1 text-xs w-28">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $sub['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
renderLayout('Subscriptions — Admin', $content, ['admin' => true]);
