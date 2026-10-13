<?php
Auth::requireAdmin();
$db = Database::getInstance();

$search = get('q', '');
$where = "role = 'client'";
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$customers = $db->fetchAll("SELECT * FROM users WHERE $where ORDER BY created_at DESC", $params);

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Customers</h1>
        <p class="text-gray-500 text-sm mt-1"><?= count($customers) ?> registered customers</p>
    </div>
</div>

<!-- Search -->
<div class="card p-4 mb-5">
    <form method="GET" action="<?= pageUrl('admin.customers') ?>" class="flex gap-2">
        <input type="hidden" name="page" value="admin.customers">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, email, or phone..." class="form-input flex-1">
        <button type="submit" class="btn-primary px-4"><i class="fa-solid fa-search"></i></button>
        <?php if ($search): ?><a href="<?= pageUrl('admin.customers') ?>" class="btn-outline px-4">Clear</a><?php endif; ?>
    </form>
</div>

<div class="card overflow-hidden">
    <?php if (empty($customers)): ?>
    <div class="p-12 text-center text-warm">
        <i class="fa-solid fa-users text-4xl mb-3 opacity-30"></i>
        <p>No customers found.</p>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Customer</th><th>Phone</th><th>Address</th><th>Orders</th><th>Subscriptions</th><th>Joined</th></tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $c):
                $orderCount = $db->fetchOne("SELECT COUNT(*) as n FROM orders WHERE user_id = ?", [$c['id']])['n'];
                $subCount = $db->fetchOne("SELECT COUNT(*) as n FROM subscriptions WHERE user_id = ? AND status = 'active'", [$c['id']])['n'];
                $totalSpent = $db->fetchOne("SELECT SUM(total) as s FROM orders WHERE user_id = ? AND status NOT IN ('cancelled','pending')", [$c['id']])['s'] ?? 0;
            ?>
            <tr>
                <td>
                    <div class="font-semibold text-brown"><?= e($c['name']) ?></div>
                    <div class="text-warm text-xs"><?= e($c['email']) ?></div>
                    <?php if ($totalSpent > 0): ?>
                    <div class="text-xs text-green-600 mt-0.5"><?= formatMoney($totalSpent) ?> total spent</div>
                    <?php endif; ?>
                </td>
                <td class="text-warm text-sm"><?= e($c['phone'] ?? '—') ?></td>
                <td class="text-warm text-xs max-w-xs truncate"><?= e($c['address'] ?? '—') ?></td>
                <td>
                    <?php if ($orderCount > 0): ?>
                    <span class="badge badge-blue"><?= $orderCount ?> order<?= $orderCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                    <span class="text-warm text-xs">None</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($subCount > 0): ?>
                    <span class="badge badge-green"><?= $subCount ?> active</span>
                    <?php else: ?>
                    <span class="text-warm text-xs">None</span>
                    <?php endif; ?>
                </td>
                <td class="text-warm text-sm"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
renderLayout('Customers — Admin', $content, ['admin' => true]);
