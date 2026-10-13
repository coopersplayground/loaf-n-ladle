<?php
Auth::requireAdmin();
$db = Database::getInstance();

$today        = date('Y-m-d');
$todayDow     = strtolower(date('l')); // e.g. "thursday"
$hideToday    = get('hide_today', '0') === '1';

// ── 1. SUBSCRIPTIONS for today's day-of-week ─────────────────────────────────
$deliveryDay = $db->fetchOne(
    "SELECT id, day_name FROM delivery_days WHERE LOWER(day_name) = ?",
    [$todayDow]
);

$subscriptionItems = [];
$subscriptionCount = 0;

if ($deliveryDay) {
    $subs = $db->fetchAll(
        "SELECT bread_selections FROM subscriptions
         WHERE status = 'active' AND delivery_day_id = ?",
        [$deliveryDay['id']]
    );
    $subscriptionCount = count($subs);
    foreach ($subs as $sub) {
        if (empty($sub['bread_selections'])) continue;
        foreach (array_map('trim', explode(',', $sub['bread_selections'])) as $item) {
            if ($item !== '') $subscriptionItems[$item] = ($subscriptionItems[$item] ?? 0) + 1;
        }
    }
}

// ── 2. ALL UNFULFILLED SHOP ORDERS ───────────────────────────────────────────
// "Unfulfilled" = status pending, paid, or processing (not ready/completed/cancelled)
$unfulfilledStatuses = ['pending', 'paid', 'processing'];
$placeholders = implode(',', array_fill(0, count($unfulfilledStatuses), '?'));

// Build date exclusion clause
$orderParams = $unfulfilledStatuses;
$dateClause  = '';
if ($hideToday) {
    $dateClause = "AND DATE(o.created_at) != ?";
    $orderParams[] = $today;
}

$shopRows = $db->fetchAll(
    "SELECT oi.product_name, SUM(oi.quantity) as total_qty
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     WHERE o.status IN ($placeholders) $dateClause
     GROUP BY oi.product_name
     ORDER BY oi.product_name",
    $orderParams
);

$shopItems = [];
foreach ($shopRows as $row) {
    $shopItems[$row['product_name']] = (int)$row['total_qty'];
}

// Count of distinct unfulfilled orders (for the banner)
$countParams = $unfulfilledStatuses;
if ($hideToday) $countParams[] = $today;
$shopOrderTotal = $db->fetchOne(
    "SELECT COUNT(*) as c FROM orders o
     WHERE o.status IN ($placeholders) $dateClause",
    $countParams
)['c'] ?? 0;

// Today's orders count (so we can show how many are hidden)
$todayOrderTotal = $db->fetchOne(
    "SELECT COUNT(*) as c FROM orders o
     WHERE o.status IN ($placeholders) AND DATE(o.created_at) = ?",
    array_merge($unfulfilledStatuses, [$today])
)['c'] ?? 0;

// ── 3. COMBINED GRAND TOTAL ───────────────────────────────────────────────────
$allItems = [];
foreach ($subscriptionItems as $name => $qty) $allItems[$name] = ($allItems[$name] ?? 0) + $qty;
foreach ($shopItems      as $name => $qty) $allItems[$name] = ($allItems[$name] ?? 0) + $qty;
arsort($allItems);

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-fire-burner text-amber-600 mr-2"></i>Bake List
        </h1>
        <p class="text-gray-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" action="" class="flex items-center gap-2">
            <input type="hidden" name="page" value="admin.bake_list">
            <label class="flex items-center gap-2 cursor-pointer select-none text-sm font-medium text-gray-700">
                <input type="checkbox" name="hide_today" value="1"
                       <?= $hideToday ? 'checked' : '' ?>
                       onchange="this.form.submit()"
                       class="w-4 h-4 accent-amber-600">
                Hide today's orders
            </label>
        </form>
        <button onclick="window.print()" class="btn-outline text-sm px-3 py-1.5">
            <i class="fa-solid fa-print mr-1"></i> Print
        </button>
    </div>
</div>

<!-- Summary Banners -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 flex items-center gap-4">
        <i class="fa-solid fa-rotate text-blue-600 text-2xl"></i>
        <div>
            <p class="font-bold text-blue-900">Today's Subscriptions</p>
            <p class="text-blue-700 text-sm">
                <?= $subscriptionCount ?> active subscriber<?= $subscriptionCount !== 1 ? 's' : '' ?>
                for <?= $deliveryDay ? e($deliveryDay['day_name']) : ucfirst($todayDow) ?>
            </p>
        </div>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 flex items-center gap-4">
        <i class="fa-solid fa-bag-shopping text-green-600 text-2xl"></i>
        <div>
            <p class="font-bold text-green-900">Unfulfilled Shop Orders</p>
            <p class="text-green-700 text-sm">
                <?= $shopOrderTotal ?> order<?= $shopOrderTotal !== 1 ? 's' : '' ?> pending/paid/processing
                <?php if ($hideToday && $todayOrderTotal > 0): ?>
                <span class="text-amber-600">(<?= $todayOrderTotal ?> from today hidden)</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<?php if (empty($allItems)): ?>
<div class="card p-12 text-center text-gray-400">
    <i class="fa-solid fa-bread-slice text-5xl mb-4 opacity-20"></i>
    <p class="text-lg font-medium">Nothing to bake right now.</p>
    <p class="text-sm mt-1">No active subscriptions for today and no unfulfilled shop orders.</p>
</div>
<?php else: ?>

<!-- Grand Total Bake List -->
<div class="card overflow-hidden mb-6">
    <div class="bg-gray-800 px-6 py-3 flex items-center gap-2">
        <i class="fa-solid fa-list-check text-amber-400"></i>
        <h2 class="font-bold text-white text-sm uppercase tracking-wider">Total Bake List</h2>
        <span class="ml-auto text-amber-300 text-xs font-semibold"><?= array_sum($allItems) ?> total items</span>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                <th class="text-center px-6 py-3 text-xs font-semibold text-blue-600 uppercase tracking-wider">Subscriptions</th>
                <th class="text-center px-6 py-3 text-xs font-semibold text-green-600 uppercase tracking-wider">Shop Orders</th>
                <th class="text-center px-6 py-3 text-xs font-semibold text-amber-700 uppercase tracking-wider">Total to Bake</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($allItems as $itemName => $totalQty):
                $subQty  = $subscriptionItems[$itemName] ?? 0;
                $shopQty = $shopItems[$itemName] ?? 0;
            ?>
            <tr class="hover:bg-amber-50/40 transition-colors">
                <td class="px-6 py-4 font-semibold text-gray-800 text-sm"><?= e($itemName) ?></td>
                <td class="px-6 py-4 text-center">
                    <?php if ($subQty > 0): ?>
                    <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 text-sm font-bold px-3 py-1 rounded-full">
                        <i class="fa-solid fa-rotate text-xs"></i> <?= $subQty ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-300 text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <?php if ($shopQty > 0): ?>
                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 text-sm font-bold px-3 py-1 rounded-full">
                        <i class="fa-solid fa-bag-shopping text-xs"></i> <?= $shopQty ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-300 text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-block bg-amber-500 text-white text-lg font-extrabold px-5 py-1.5 rounded-full min-w-[3rem]">
                        <?= $totalQty ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
            <tr>
                <td class="px-6 py-3 font-bold text-gray-700 text-sm uppercase">Grand Total</td>
                <td class="px-6 py-3 text-center font-bold text-blue-700"><?= array_sum($subscriptionItems) ?></td>
                <td class="px-6 py-3 text-center font-bold text-green-700"><?= array_sum($shopItems) ?></td>
                <td class="px-6 py-3 text-center">
                    <span class="inline-block bg-gray-800 text-white text-xl font-extrabold px-5 py-1.5 rounded-full">
                        <?= array_sum($allItems) ?>
                    </span>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Breakdown: Subscriptions -->
<?php if (!empty($subscriptionItems)): ?>
<div class="card overflow-hidden mb-6">
    <div class="bg-blue-700 px-6 py-3 flex items-center gap-2">
        <i class="fa-solid fa-rotate text-white"></i>
        <h2 class="font-bold text-white text-sm uppercase tracking-wider">From Subscriptions</h2>
        <span class="ml-auto text-blue-200 text-xs font-semibold">
            <?= $subscriptionCount ?> subscriber<?= $subscriptionCount !== 1 ? 's' : '' ?> · <?= array_sum($subscriptionItems) ?> items
        </span>
    </div>
    <table class="w-full">
        <tbody class="divide-y divide-gray-100">
            <?php arsort($subscriptionItems); foreach ($subscriptionItems as $itemName => $qty): ?>
            <tr class="hover:bg-blue-50/30 transition-colors">
                <td class="px-6 py-3 font-medium text-gray-800 text-sm"><?= e($itemName) ?></td>
                <td class="px-6 py-3 text-right font-extrabold text-blue-700 text-xl"><?= $qty ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Breakdown: Shop Orders -->
<?php if (!empty($shopItems)): ?>
<div class="card overflow-hidden mb-6">
    <div class="bg-green-700 px-6 py-3 flex items-center gap-2">
        <i class="fa-solid fa-bag-shopping text-white"></i>
        <h2 class="font-bold text-white text-sm uppercase tracking-wider">From Unfulfilled Shop Orders</h2>
        <span class="ml-auto text-green-200 text-xs font-semibold">
            <?= $shopOrderTotal ?> order<?= $shopOrderTotal !== 1 ? 's' : '' ?> · <?= array_sum($shopItems) ?> items
            <?php if ($hideToday && $todayOrderTotal > 0): ?>(today hidden)<?php endif; ?>
        </span>
    </div>
    <table class="w-full">
        <tbody class="divide-y divide-gray-100">
            <?php arsort($shopItems); foreach ($shopItems as $itemName => $qty): ?>
            <tr class="hover:bg-green-50/30 transition-colors">
                <td class="px-6 py-3 font-medium text-gray-800 text-sm"><?= e($itemName) ?></td>
                <td class="px-6 py-3 text-right font-extrabold text-green-700 text-xl"><?= $qty ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
@media print {
    aside, nav, button, form, .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc; }
    body { background: white !important; }
}
</style>
<?php
$content = ob_get_clean();
renderLayout('Bake List — Admin', $content, ['admin' => true]);
