<?php
$db = Database::getInstance();

// Validate token
$token = get('token', '');
$validToken = setting('orders_screen_token', '');
if (!$token || !$validToken || !hash_equals($validToken, $token)) {
    http_response_code(403);
    die('Access denied.');
}

// Load urgency thresholds (in seconds)
$paidOnlineSecs       = (int)setting('paid_online_urgent_minutes', 2) * 60;
$paidInstoreSecs      = (int)setting('paid_instore_urgent_minutes', 2) * 60;
$preparingOnlineSecs  = (int)setting('preparing_online_urgent_minutes', 3) * 60;
$preparingInstoreSecs = (int)setting('preparing_instore_urgent_minutes', 3) * 60;
$alertSound          = setting('alert_sound', 'bell');

// Fetch active orders (not completed/cancelled)
$orders = $db->fetchAll(
    "SELECT o.*, u.name as customer_name
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     WHERE o.status IN ('ordered','paid','preparing','pickup')
     ORDER BY CASE o.status
         WHEN 'paid'      THEN 1
         WHEN 'ordered'   THEN 2
         WHEN 'preparing' THEN 3
         WHEN 'pickup'    THEN 4
     END, o.created_at ASC",
    []
);

// Fetch items for each order
foreach ($orders as &$o) {
    $o['items'] = $db->fetchAll(
        "SELECT product_name, quantity FROM order_items WHERE order_id = ? ORDER BY id",
        [$o['id']]
    );
}
unset($o);

$statusConfig = [
    'ordered'   => ['label' => 'Ordered',          'bg' => '#3b82f6', 'text' => '#fff', 'dot' => '#60a5fa'],
    'paid'      => ['label' => 'Paid — Not Started','bg' => '#ef4444', 'text' => '#fff', 'dot' => '#f87171'],
    'preparing' => ['label' => 'Being Prepared',   'bg' => '#f59e0b', 'text' => '#fff', 'dot' => '#fbbf24'],
    'pickup'    => ['label' => 'Waiting for Pickup','bg' => '#10b981', 'text' => '#fff', 'dot' => '#34d399'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loaf'n Ladle — Order Screen</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background: #0f0f0f;
        color: #f5f5f5;
        font-family: 'Segoe UI', system-ui, sans-serif;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* Top bar */
    .topbar {
        background: #1a1a1a;
        border-bottom: 2px solid #8b4513;
        padding: 14px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .topbar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.4rem;
        font-weight: 700;
        color: #d4a574;
        letter-spacing: 0.02em;
    }
    .topbar-brand i { color: #8b4513; }
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
        font-size: 0.85rem;
        color: #888;
    }
    .live-dot {
        display: inline-block;
        width: 10px; height: 10px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 1.5s infinite;
        margin-right: 6px;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.5; transform: scale(1.3); }
    }
    @keyframes blink-border {
        0%, 100% { border-color: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        50%       { border-color: #ef4444; box-shadow: 0 0 16px 4px rgba(239,68,68,0.55); }
    }
    .orders-grid .order-card.urgent {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 0 rgba(239,68,68,0) !important;
        animation: blink-border 1s ease-in-out infinite !important;
    }
    #clock {
        font-size: 1.1rem;
        font-weight: 600;
        color: #d4a574;
        min-width: 80px;
        text-align: right;
    }

    /* Grid */
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        padding: 24px 32px;
    }

    /* Card */
    .order-card {
        background: #1c1c1c;
        border-radius: 14px;
        border: 1px solid #2a2a2a;
        overflow: hidden;
        transition: border-color 0.3s;
    }
    .order-card.status-ready {
        border-color: #10b981;
        box-shadow: 0 0 20px rgba(16,185,129,0.15);
    }
    .order-card.status-processing {
        border-color: #8b5cf6;
    }

    .card-header {
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .order-number {
        font-size: 1.05rem;
        font-weight: 700;
        color: #f5f5f5;
        letter-spacing: 0.03em;
    }
    .order-time {
        font-size: 0.75rem;
        color: #666;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .status-dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .card-divider { border: none; border-top: 1px solid #2a2a2a; }

    .card-items {
        padding: 12px 18px;
    }
    .item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #242424;
        gap: 10px;
    }
    .item-row:last-child { border-bottom: none; }
    .item-name {
        font-size: 0.9rem;
        color: #e0e0e0;
        flex: 1;
    }
    .item-qty {
        font-size: 1rem;
        font-weight: 800;
        color: #d4a574;
        min-width: 28px;
        text-align: right;
    }

    .card-footer {
        padding: 10px 18px;
        background: #181818;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.78rem;
        color: #555;
    }
    .card-footer .total {
        font-weight: 700;
        color: #d4a574;
        font-size: 0.9rem;
    }

    /* Empty state */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 80px 20px;
        color: #444;
    }
    .empty-state i { font-size: 4rem; margin-bottom: 16px; display: block; }
    .empty-state p { font-size: 1.2rem; }

    /* Status legend */
    .legend {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        color: #888;
    }
    .legend-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
    }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <i class="fas fa-bread-slice"></i>
        Loaf'n Ladle — Orders
    </div>
    <div class="topbar-right">
        <div class="legend">
            <?php foreach ($statusConfig as $key => $cfg): ?>
            <div class="legend-item">
                <span class="legend-dot" style="background:<?= $cfg['dot'] ?>"></span>
                <?= htmlspecialchars($cfg['label']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <button id="enable-sound-btn" onclick="initAudio()" style="background:#1e3a5f;color:#93c5fd;border:1px solid #3b82f6;padding:4px 10px;border-radius:999px;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#1e3a5f'">
            <i class="fa-solid fa-bell"></i> Enable Sound
        </button>
        <div>
            <span class="live-dot"></span>Live
        </div>
        <div id="clock"></div>
    </div>
</div>

<div class="orders-grid">
<?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle" style="color:#10b981"></i>
        <p>All caught up — no active orders right now.</p>
    </div>
<?php else: ?>
    <?php foreach ($orders as $o):
        $cfg = $statusConfig[$o['status']] ?? $statusConfig['ordered'];
        $name = $o['customer_name'] ?? $o['delivery_name'] ?? 'Guest';
        $timeAgo = date('g:ia', strtotime($o['created_at']));
    ?>
    <?php
        $age      = time() - strtotime($o['created_at']);
        $isOnline = !empty($o['paid_via_stripe']);
        $isUrgent = ($o['status'] === 'paid'      && $age > ($isOnline ? $paidOnlineSecs      : $paidInstoreSecs))
                 || ($o['status'] === 'preparing' && $age > ($isOnline ? $preparingOnlineSecs : $preparingInstoreSecs));
    ?>
    <div class="order-card status-<?= $o['status'] ?><?= $isUrgent ? ' urgent' : '' ?>">
        <div class="card-header">
            <div>
                <?php if ($o['daily_order_number']): ?>
                <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#fff;letter-spacing:-1px"><?= str_pad($o['daily_order_number'], 3, '0', STR_PAD_LEFT) ?></div>
                <?php endif; ?>
                <div class="order-number" style="font-size:0.7rem;font-weight:400;opacity:0.6;margin-top:2px"><?= e($o['order_number']) ?></div>
                <div class="order-time"><?= e($name) ?> &middot; <?= $timeAgo ?></div>
            </div>
            <div class="status-badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['text'] ?>">
                <span class="status-dot" style="background:<?= $cfg['dot'] ?>"></span>
                <?= $cfg['label'] ?>
            </div>
        </div>
        <hr class="card-divider">
        <div class="card-items">
            <?php foreach ($o['items'] as $item): ?>
            <div class="item-row">
                <span class="item-name"><?= e($item['product_name']) ?></span>
                <span class="item-qty">×<?= (int)$item['quantity'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($o['items'])): ?>
            <div class="item-row"><span class="item-name" style="color:#555">No items</span></div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <span><?= $o['delivery_address'] ? '<i class="fas fa-truck" style="margin-right:4px"></i>Delivery' : '<i class="fas fa-store" style="margin-right:4px"></i>Pickup' ?></span>
            <div style="display:flex;align-items:center;gap:8px">
                <?php if ($o['paid_via_stripe'] ?? false): ?>
                <span style="background:#1e3a5f;color:#93c5fd;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase">ONLINE ORDER</span>
                <?php endif; ?>
                <?php if (!($o['paid_via_stripe'] ?? false) || $o['status'] === 'ordered'): ?>
                <span style="background:#7f1d1d;color:#fca5a5;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase">NOT PAID</span>
                <?php endif; ?>
                <span class="total"><?= formatMoney($o['total']) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
// ── Clock ────────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        now.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit', second:'2-digit'});
}
updateClock();
setInterval(updateClock, 1000);

// ── Config ───────────────────────────────────────────────────────────────────
const TOKEN    = <?= json_encode($token) ?>;
const DATA_URL = <?= json_encode(pageUrl('orders.screen.data', ['token' => $token])) ?>;
const POLL_MS  = 5000; // poll every 5 seconds
const PAID_ONLINE_SECS       = <?= $paidOnlineSecs ?>;
const PAID_INSTORE_SECS      = <?= $paidInstoreSecs ?>;
const PREPARING_ONLINE_SECS  = <?= $preparingOnlineSecs ?>;
const PREPARING_INSTORE_SECS = <?= $preparingInstoreSecs ?>;

const STATUS_CONFIG = {
    ordered:   { label: 'Ordered',           bg: '#3b82f6', text: '#fff', dot: '#60a5fa' },
    paid:      { label: 'Paid \u2014 Not Started', bg: '#ef4444', text: '#fff', dot: '#f87171' },
    preparing: { label: 'Being Prepared',    bg: '#f59e0b', text: '#fff', dot: '#fbbf24' },
    pickup:    { label: 'Waiting for Pickup',bg: '#10b981', text: '#fff', dot: '#34d399' },
};

// ── Render helpers ────────────────────────────────────────────────────────────
function formatMoney(v) {
    return '$' + parseFloat(v).toFixed(2);
}
function timeStr(dateStr) {
    return new Date(dateStr.replace(' ', 'T') + 'Z').toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
}
function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildCard(o) {
    const cfg = STATUS_CONFIG[o.status] ?? STATUS_CONFIG.ordered;
    const name = esc(o.customer_name ?? o.delivery_name ?? 'Guest');
    const delivery = o.delivery_address
        ? '<i class="fas fa-truck" style="margin-right:4px"></i>Delivery'
        : '<i class="fas fa-store" style="margin-right:4px"></i>Pickup';

    const onlineBadge = parseInt(o.paid_via_stripe)
        ? '<span style="background:#1e3a5f;color:#93c5fd;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase">ONLINE ORDER</span>'
        : '';
    const notPaidBadge = (!parseInt(o.paid_via_stripe) || o.status === 'ordered')
        ? '<span style="background:#7f1d1d;color:#fca5a5;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase">NOT PAID</span>'
        : '';

    const itemsHtml = o.items && o.items.length
        ? o.items.map(i => `
            <div class="item-row">
                <span class="item-name">${esc(i.product_name)}</span>
                <span class="item-qty">×${parseInt(i.quantity)}</span>
            </div>`).join('')
        : '<div class="item-row"><span class="item-name" style="color:#555">No items</span></div>';

    const ageSeconds = o.created_at ? (Date.now() / 1000 - new Date(o.created_at.replace(' ', 'T') + 'Z').getTime() / 1000) : 0;
    const isOnline   = !!(o.paid_via_stripe && o.paid_via_stripe != '0');
    const threshold  = (o.status === 'paid') ? (isOnline ? PAID_ONLINE_SECS : PAID_INSTORE_SECS) 
                    : (o.status === 'preparing') ? (isOnline ? PREPARING_ONLINE_SECS : PREPARING_INSTORE_SECS) 
                    : 999999;
    const isUrgent   = ageSeconds > threshold;
    console.log('Card check:', o.order_number, o.status, ageSeconds + 's', 'threshold=' + threshold + 's', 'isOnline=' + isOnline, 'isUrgent=' + isUrgent);
    const dailyNum = o.daily_order_number ? String(o.daily_order_number).padStart(3,'0') : '';
    return `
    <div class="order-card status-${esc(o.status)}${isUrgent ? ' urgent' : ''}" data-order-id="${o.id}">
        <div class="card-header">
            <div>
                ${dailyNum ? `<div style="font-size:2.5rem;font-weight:900;line-height:1;color:#fff;letter-spacing:-1px">${dailyNum}</div>` : ''}
                <div class="order-number" style="font-size:0.7rem;font-weight:400;opacity:0.6;margin-top:2px">${esc(o.order_number)}</div>
                <div class="order-time">${name} &middot; ${timeStr(o.created_at)}</div>
            </div>
            <div class="status-badge" style="background:${cfg.bg};color:${cfg.text}">
                <span class="status-dot" style="background:${cfg.dot}"></span>
                ${cfg.label}
            </div>
        </div>
        <hr class="card-divider">
        <div class="card-items">${itemsHtml}</div>
        <div class="card-footer">
            <span>${delivery}</span>
            <div style="display:flex;align-items:center;gap:8px">
                ${onlineBadge}${notPaidBadge}
                <span class="total">${formatMoney(o.total)}</span>
            </div>
        </div>
    </div>`;
}

function buildEmpty() {
    return `<div class="empty-state" data-order-id="__empty__">
        <i class="fas fa-check-circle" style="color:#10b981"></i>
        <p>All caught up — no active orders right now.</p>
    </div>`;
}

// ── Fingerprint for change detection ─────────────────────────────────────────
function fingerprint(orders) {
    return orders.map(o => `${o.id}:${o.status}:${o.updated_at}`).join('|');
}

let lastFingerprint = null;
let lastOrderCount = 0;

// ── Bell sound (Web Audio API) ───────────────────────────────────────────────────
let audioCtx = null;
let audioEnabled = false;

function initAudio() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    audioEnabled = true;
    const btn = document.getElementById('enable-sound-btn');
    if (btn) btn.style.display = 'none';
}

function playBell() {
    if (!audioEnabled) return;
    if (!audioCtx) initAudio();
    const sound = <?= json_encode($alertSound) ?>;
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

// ── Poll ─────────────────────────────────────────────────────────────────────
async function poll() {
    try {
        console.log('Fetching:', DATA_URL);
        const res = await fetch(DATA_URL, { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();
        console.log('API response:', data);
        const orders = data.orders ?? [];

        const fp = fingerprint(orders);
        console.log('Fingerprint check:', fp === lastFingerprint ? 'no change' : 'changed');
        // Temporarily disable fingerprint check for debugging
        // if (fp === lastFingerprint) return; // nothing changed
        lastFingerprint = fp;

        // Play bell if order count increased (new order arrived)
        if (orders.length > lastOrderCount) {
            playBell();
        }
        lastOrderCount = orders.length;

        const grid = document.querySelector('.orders-grid');
        if (orders.length === 0) {
            grid.innerHTML = buildEmpty();
        } else {
            grid.innerHTML = orders.map(buildCard).join('');
        }
    } catch (e) {
        // silently ignore network errors — will retry next poll
    }
}

// Set initial fingerprint and order count from server-rendered content so first poll
// only triggers a DOM update if something actually changed.
lastFingerprint = <?= json_encode(implode('|', array_map(
    fn($o) => $o['id'] . ':' . $o['status'] . ':' . $o['updated_at'],
    $orders
))) ?>;
lastOrderCount = <?= count($orders) ?>;

setInterval(poll, POLL_MS);
</script>
</body>
</html>
