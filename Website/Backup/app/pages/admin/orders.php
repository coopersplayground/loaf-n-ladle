<?php
Auth::requireAdmin();
$db = Database::getInstance();

// Handle screen token generation
if (isPost() && post('action') === 'generate_screen_token') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.orders')); }
    $token = bin2hex(random_bytes(24));
    $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES ('orders_screen_token', ?, CURRENT_TIMESTAMP)", [$token]);
    flash('success', 'Screen link generated.');
    redirect(pageUrl('admin.orders'));
}

$screenToken              = setting('orders_screen_token', '');
$screenUrl                = $screenToken ? pageUrl('orders.screen', ['token' => $screenToken]) : '';
$paidOnlineMins           = (int)setting('paid_online_urgent_minutes', 2);
$paidInstoreMins          = (int)setting('paid_instore_urgent_minutes', 2);
$preparingOnlineMins      = (int)setting('preparing_online_urgent_minutes', 3);
$preparingInstoreMins     = (int)setting('preparing_instore_urgent_minutes', 3);
$alertSound              = setting('alert_sound', 'bell');

// Handle priority times update
if (isPost() && post('action') === 'save_priority_times') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.orders')); }
    foreach ([
        'paid_online_urgent_minutes'       => post('paid_online_urgent_minutes'),
        'paid_instore_urgent_minutes'      => post('paid_instore_urgent_minutes'),
        'preparing_online_urgent_minutes'  => post('preparing_online_urgent_minutes'),
        'preparing_instore_urgent_minutes' => post('preparing_instore_urgent_minutes'),
        'alert_sound'                       => post('alert_sound'),
    ] as $key => $val) {
        $v = (in_array($key, ['alert_sound'])) ? $val : max(1, (int)$val);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", [$key, $v]);
    }
    flash('success', 'Priority times updated.');
    redirect(pageUrl('admin.orders'));
}

// Handle mark as paid
if (isPost() && post('action') === 'mark_paid') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.orders')); }
    $id = (int)post('order_id');
    $db->execute("UPDATE orders SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$id]);
    flash('success', 'Order marked as paid.');
    redirect(pageUrl('admin.orders'));
}

// Handle status update
if (isPost() && post('action') === 'update_status') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.orders')); }
    $id = (int)post('order_id');
    $status = sanitize(post('status'));
    $allowed = ['ordered','paid','preparing','pickup','completed','cancelled'];
    if (in_array($status, $allowed)) {
        $db->execute("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$status, $id]);
        flash('success', 'Order status updated.');
    }
    redirect(pageUrl('admin.orders'));
}

$statusFilter = get('status', '');
$search = get('q', '');
$where = "1=1";
$params = [];
if ($statusFilter) { $where .= " AND o.status = ?"; $params[] = $statusFilter; }
if ($search) { $where .= " AND (o.order_number LIKE ? OR o.delivery_name LIKE ? OR o.delivery_email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$orders = $db->fetchAll(
    "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE $where ORDER BY o.created_at DESC",
    $params
);

$statusColors = [
    'ordered'   => 'badge-blue',
    'paid'      => 'badge-red',
    'preparing' => 'badge-yellow',
    'pickup'    => 'badge-green',
    'completed' => 'badge-gray',
    'cancelled' => 'badge-red',
];
$statusLabels = [
    'ordered'   => 'Ordered — Not Paid',
    'paid'      => 'Paid — Not Started',
    'preparing' => 'Being Prepared',
    'pickup'    => 'Waiting for Pickup',
    'completed' => 'Completed / Picked Up',
    'cancelled' => 'Cancelled',
];
$statuses = ['ordered','paid','preparing','pickup','completed','cancelled'];

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Orders</h1>
        <p class="text-gray-500 text-sm mt-1"><?= count($orders) ?> orders</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="POST" action="<?= pageUrl('admin.orders') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="generate_screen_token">
            <button type="submit" class="btn-outline text-sm px-3 py-1.5" title="Generate a shareable TV display link">
                <i class="fa-solid fa-tv mr-1"></i><?= $screenToken ? 'Regenerate Screen Link' : 'Generate Screen Link' ?>
            </button>
        </form>
        <?php if ($screenUrl): ?>
        <button onclick="copyScreenLink()" class="btn-primary text-sm px-3 py-1.5">
            <i class="fa-solid fa-link mr-1"></i>Copy Link
        </button>
        <a href="<?= e($screenUrl) ?>" target="_blank" class="btn-outline text-sm px-3 py-1.5">
            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Open
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($screenUrl): ?>
<div id="screen-link-bar" class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-5 flex items-center gap-3">
    <i class="fa-solid fa-tv text-blue-600"></i>
    <span class="text-blue-800 text-sm font-medium">TV Screen URL:</span>
    <code id="screen-url-text" class="text-blue-700 text-xs bg-blue-100 px-2 py-1 rounded flex-1 truncate"><?= e($screenUrl) ?></code>
    <button onclick="copyScreenLink()" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
        <i class="fa-solid fa-copy mr-1"></i>Copy
    </button>
</div>
<script>
function copyScreenLink() {
    const url = <?= json_encode($screenUrl) ?>;
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.querySelectorAll('[onclick="copyScreenLink()"]');
        btn.forEach(b => { const orig = b.innerHTML; b.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Copied!'; setTimeout(() => b.innerHTML = orig, 2000); });
    });
}
</script>
<?php endif; ?>

<!-- Priority Times Settings -->
<div class="card p-4 mb-5">
    <div class="flex items-center gap-2 mb-4">
        <i class="fa-solid fa-clock-rotate-left text-amber-600"></i>
        <span class="font-semibold text-gray-700 text-sm">TV Screen Alert Thresholds</span>
        <span class="text-xs text-gray-400">(blinking red border triggers after these times)</span>
    </div>
    <form method="POST" action="<?= pageUrl('admin.orders') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_priority_times">
        <div class="grid grid-cols-2 gap-x-8 gap-y-4 mb-4">
            <!-- Header row -->
            <div class="col-span-2 grid grid-cols-3 gap-4 text-xs font-semibold text-gray-500 uppercase tracking-wide border-b pb-1">
                <div></div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-globe text-blue-500"></i> Online</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-store text-amber-500"></i> In-store</div>
            </div>
            <!-- Paid row -->
            <div class="col-span-2 grid grid-cols-3 gap-4 items-center">
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>Paid — Not Started
                </div>
                <div class="flex items-center gap-1">
                    <input type="number" name="paid_online_urgent_minutes" value="<?= $paidOnlineMins ?>" min="1" max="60"
                        class="form-input w-20 text-sm py-1 text-center font-bold">
                    <span class="text-sm text-gray-500">min</span>
                </div>
                <div class="flex items-center gap-1">
                    <input type="number" name="paid_instore_urgent_minutes" value="<?= $paidInstoreMins ?>" min="1" max="60"
                        class="form-input w-20 text-sm py-1 text-center font-bold">
                    <span class="text-sm text-gray-500">min</span>
                </div>
            </div>
            <!-- Preparing row -->
            <div class="col-span-2 grid grid-cols-3 gap-4 items-center">
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <span class="inline-block w-3 h-3 rounded-full bg-yellow-500"></span>Being Prepared
                </div>
                <div class="flex items-center gap-1">
                    <input type="number" name="preparing_online_urgent_minutes" value="<?= $preparingOnlineMins ?>" min="1" max="60"
                        class="form-input w-20 text-sm py-1 text-center font-bold">
                    <span class="text-sm text-gray-500">min</span>
                </div>
                <div class="flex items-center gap-1">
                    <input type="number" name="preparing_instore_urgent_minutes" value="<?= $preparingInstoreMins ?>" min="1" max="60"
                        class="form-input w-20 text-sm py-1 text-center font-bold">
                    <span class="text-sm text-gray-500">min</span>
                </div>
            </div>
            <!-- Sound selector row -->
            <div class="col-span-2 grid grid-cols-3 gap-4 items-center">
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-bell text-purple-500"></i>Alert Sound
                </div>
                <div class="col-span-2 flex items-center gap-2">
                    <select name="alert_sound" id="alert_sound_select" class="form-input text-sm py-1.5 w-full max-w-xs">
                        <option value="short_beep" <?= $alertSound === 'short_beep' ? 'selected' : '' ?>>🔔 Short Beep</option>
                        <option value="double_beep" <?= $alertSound === 'double_beep' ? 'selected' : '' ?>>🔔 Double Beep</option>
                        <option value="bell" <?= $alertSound === 'bell' ? 'selected' : '' ?>>🔔 Bell Ring</option>
                        <option value="chime" <?= $alertSound === 'chime' ? 'selected' : '' ?>>🔔 Chime</option>
                        <option value="ding_dong" <?= $alertSound === 'ding_dong' ? 'selected' : '' ?>>🔔 Ding Dong</option>
                        <option value="triple_beep" <?= $alertSound === 'triple_beep' ? 'selected' : '' ?>>🔔 Triple Beep</option>
                        <option value="classic_ring" <?= $alertSound === 'classic_ring' ? 'selected' : '' ?>>📞 Classic Ring</option>
                        <option value="alarm" <?= $alertSound === 'alarm' ? 'selected' : '' ?>>🚨 Alarm</option>
                        <option value="siren" <?= $alertSound === 'siren' ? 'selected' : '' ?>>🚨 Siren</option>
                        <option value="church_bell" <?= $alertSound === 'church_bell' ? 'selected' : '' ?>>🔔 Church Bell</option>
                    </select>
                    <button type="button" onclick="testAlertSound()" class="btn-outline text-sm px-3 py-1.5">
                        <i class="fa-solid fa-play mr-1"></i>Test Sound
                    </button>
                </div>
            </div>
        </div>
        <button type="submit" class="btn-primary text-sm px-4 py-1.5">
            <i class="fa-solid fa-floppy-disk mr-1"></i>Save
        </button>
    </form>
</div>

<!-- Filters -->
<div class="card p-4 mb-5 flex flex-col sm:flex-row gap-3">
    <form method="GET" action="<?= pageUrl('admin.orders') ?>" class="flex gap-2 flex-1">
        <input type="hidden" name="page" value="admin.orders">
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search order #, name, email..." class="form-input flex-1">
        <button type="submit" class="btn-primary px-4"><i class="fa-solid fa-search"></i></button>
        <?php if ($search): ?><a href="<?= pageUrl('admin.orders', $statusFilter ? ['status'=>$statusFilter] : []) ?>" class="btn-outline px-4">Clear</a><?php endif; ?>
    </form>
    <div class="flex gap-2 flex-wrap">
        <a href="<?= pageUrl('admin.orders') ?>" class="<?= !$statusFilter ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5">All</a>
        <?php foreach ($statuses as $s): ?>
        <a href="<?= pageUrl('admin.orders', ['status' => $s]) ?>" class="<?= $statusFilter === $s ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5 capitalize"><?= $s ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card overflow-hidden">
    <?php if (empty($orders)): ?>
    <div class="p-12 text-center text-warm">
        <i class="fa-solid fa-receipt text-4xl mb-3 opacity-30"></i>
        <p>No orders found.</p>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Order #</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $itemCount = $db->fetchOne("SELECT COUNT(*) as c FROM order_items WHERE order_id = ?", [$o['id']])['c'];
            ?>
            <tr>
                <td>
                    <?php if ($o['daily_order_number']): ?>
                    <div class="text-2xl font-black text-brown leading-none"><?= str_pad($o['daily_order_number'], 3, '0', STR_PAD_LEFT) ?></div>
                    <?php endif; ?>
                    <div class="text-warm text-xs mt-0.5"><?= e($o['order_number']) ?></div>
                    <?php if ($o['delivery_address']): ?>
                    <div class="text-warm text-xs truncate max-w-xs"><?= e($o['delivery_address']) ?></div>
                    <?php else: ?>
                    <div class="text-warm text-xs">In-store pickup</div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="font-medium text-gray-700 text-sm"><?= e($o['customer_name'] ?? $o['delivery_name'] ?? 'Guest') ?></div>
                    <div class="text-warm text-xs"><?= e($o['delivery_email'] ?? '') ?></div>
                </td>
                <td class="text-warm text-sm"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></td>
                <td class="text-warm text-sm"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></td>
                <td class="font-bold text-brown"><?= formatMoney($o['total']) ?></td>
                <td>
                    <?php if ($o['paid_via_stripe'] ?? false): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">
                        <i class="fa-solid fa-globe text-xs"></i> Online
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                        <i class="fa-solid fa-store text-xs"></i> In-store
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" action="<?= pageUrl('admin.orders') ?>" class="flex items-center gap-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="form-input py-1 text-xs w-40">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $statusLabels[$s] ?? ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td class="flex items-center gap-1 flex-wrap">
                    <a href="<?= pageUrl('admin.order.view', ['id' => $o['id']]) ?>" class="btn-outline text-xs px-2 py-1">
                        <i class="fa-solid fa-eye mr-1"></i>View
                    </a>
                    <?php if (($o['paid_via_stripe'] ?? false) && in_array($o['status'], ['paid','preparing','pickup','completed'])): ?>
                    <span class="text-xs px-2 py-1 font-semibold rounded border border-blue-300 text-blue-700 bg-blue-50 inline-flex items-center gap-1">
                        <i class="fa-solid fa-credit-card"></i> Paid via Stripe
                    </span>
                    <?php elseif (!in_array($o['status'], ['paid','completed','cancelled'])): ?>
                    <form method="POST" action="<?= pageUrl('admin.orders') ?>" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="text-xs px-2 py-1 font-semibold rounded border border-green-600 text-green-700 bg-green-50 hover:bg-green-100 transition">
                            <i class="fa-solid fa-circle-check mr-1"></i>Mark as Paid
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<script>
function testAlertSound() {
    const sound = document.getElementById('alert_sound_select').value;
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const sampleRate = audioCtx.sampleRate;
    let buffer, duration;

    switch (sound) {
        case 'short_beep':
            duration = 0.15;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                buffer.getChannelData(0)[i] = Math.sin(2 * Math.PI * 1000 * t) * Math.exp(-t * 15) * 0.5;
            }
            break;
        case 'double_beep':
            duration = 0.6;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const beep = (t < 0.15 || (t > 0.3 && t < 0.45)) ? 1 : 0;
                buffer.getChannelData(0)[i] = beep * Math.sin(2 * Math.PI * 1000 * t) * Math.exp(-((t % 0.15) * 20)) * 0.5;
            }
            break;
        case 'bell':
            duration = 0.8;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                buffer.getChannelData(0)[i] = (Math.sin(2 * Math.PI * 800 * t) * 0.3 +
                                           Math.sin(2 * Math.PI * 1600 * t) * 0.2 +
                                           Math.sin(2 * Math.PI * 2400 * t) * 0.1) *
                                           Math.exp(-t * 5);
            }
            break;
        case 'chime':
            duration = 1.2;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                buffer.getChannelData(0)[i] = (Math.sin(2 * Math.PI * 523.25 * t) * 0.3 +  // C5
                                           Math.sin(2 * Math.PI * 659.25 * t) * 0.2 +  // E5
                                           Math.sin(2 * Math.PI * 783.99 * t) * 0.1) *  // G5
                                           Math.exp(-t * 3);
            }
            break;
        case 'ding_dong':
            duration = 1.5;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const ding = (t < 0.3) ? Math.sin(2 * Math.PI * 800 * t) * Math.exp(-t * 8) * 0.4 : 0;
                const dong = (t > 0.6 && t < 0.9) ? Math.sin(2 * Math.PI * 600 * (t - 0.6)) * Math.exp(-(t - 0.6) * 8) * 0.4 : 0;
                buffer.getChannelData(0)[i] = ding + dong;
            }
            break;
        case 'triple_beep':
            duration = 0.9;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const beep = (t < 0.15 || (t > 0.3 && t < 0.45) || (t > 0.6 && t < 0.75)) ? 1 : 0;
                buffer.getChannelData(0)[i] = beep * Math.sin(2 * Math.PI * 1000 * t) * Math.exp(-((t % 0.15) * 20)) * 0.5;
            }
            break;
        case 'classic_ring':
            duration = 2.0;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const ring = (t % 0.4) < 0.2 ? 1 : 0;
                buffer.getChannelData(0)[i] = ring * (Math.sin(2 * Math.PI * 450 * t) * 0.4 + Math.sin(2 * Math.PI * 900 * t) * 0.2) * 0.6;
            }
            break;
        case 'alarm':
            duration = 3.0;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const pulse = (t % 0.5) < 0.25 ? 1 : 0;
                buffer.getChannelData(0)[i] = pulse * (Math.sin(2 * Math.PI * 800 * t) + Math.sin(2 * Math.PI * 1200 * t)) * 0.4;
            }
            break;
        case 'siren':
            duration = 2.5;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const freq = 400 + 300 * Math.sin(2 * Math.PI * 2 * t);
                buffer.getChannelData(0)[i] = Math.sin(2 * Math.PI * freq * t) * 0.5;
            }
            break;
        case 'church_bell':
            duration = 4.0;
            buffer = audioCtx.createBuffer(1, duration * sampleRate, sampleRate);
            for (let i = 0; i < buffer.length; i++) {
                const t = i / sampleRate;
                const strike = (t < 1.5) ? 1 : ((t > 2.0 && t < 3.5) ? 0.7 : 0);
                buffer.getChannelData(0)[i] = strike * (Math.sin(2 * Math.PI * 200 * t) * 0.3 + Math.sin(2 * Math.PI * 400 * t) * 0.2 + Math.sin(2 * Math.PI * 600 * t) * 0.1) * Math.exp(-t * 0.8);
            }
            break;
        default:
            return;
    }

    const src = audioCtx.createBufferSource();
    src.buffer = buffer;
    src.connect(audioCtx.destination);
    src.start();
    src.stop(audioCtx.currentTime + duration);
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Orders — Admin', $content, ['admin' => true]);
