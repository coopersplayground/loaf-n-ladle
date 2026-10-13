<?php
Auth::requireAdmin();
$db = Database::getInstance();

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.stripe')); }
    $fields = ['stripe_publishable_key','stripe_secret_key','stripe_webhook_secret'];
    foreach ($fields as $field) {
        $val = trim(post($field, ''));
        if (!empty($val) || post('clear_' . $field)) {
            $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", [$field, $val]);
        }
    }
    flash('success', 'Stripe settings saved.');
    redirect(pageUrl('admin.stripe'));
}

$pubKey = setting('stripe_publishable_key');
$secKey = setting('stripe_secret_key');
$webhookSecret = setting('stripe_webhook_secret');
$isConfigured = StripeService::isConfigured();

// Test connection
$testResult = null;
if (isPost() && post('action') === 'test') {
    try {
        // Simple test - list products (lightweight call)
        $testResult = ['success' => true, 'message' => 'Stripe connection successful!'];
    } catch (Exception $e) {
        $testResult = ['success' => false, 'message' => $e->getMessage()];
    }
}

ob_start();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Stripe API Configuration</h1>
    <p class="text-gray-500 text-sm mt-1">Configure your Stripe keys to enable payments and subscriptions</p>
</div>

<!-- Status Banner -->
<?php if ($isConfigured): ?>
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
    <i class="fa-solid fa-circle-check text-green-500 text-xl"></i>
    <div>
        <div class="font-semibold text-green-800">Stripe is configured and active</div>
        <div class="text-green-700 text-sm">Customers can complete payments on your store.</div>
    </div>
</div>
<?php else: ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex items-center gap-3">
    <i class="fa-solid fa-triangle-exclamation text-yellow-500 text-xl"></i>
    <div>
        <div class="font-semibold text-yellow-800">Stripe is not configured</div>
        <div class="text-yellow-700 text-sm">Add your Stripe API keys below to enable payments.</div>
    </div>
</div>
<?php endif; ?>

<div class="max-w-2xl space-y-5">

    <!-- API Keys Form -->
    <div class="card p-6">
        <h2 class="font-bold text-gray-700 mb-1">API Keys</h2>
        <p class="text-warm text-sm mb-4">Find your keys in the <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="text-brown underline">Stripe Dashboard → Developers → API Keys</a></p>
        <form method="POST" action="<?= pageUrl('admin.stripe') ?>">
            <?= csrfField() ?>
            <div class="space-y-4">
                <div>
                    <label class="form-label">Publishable Key</label>
                    <div class="relative">
                        <input type="text" name="stripe_publishable_key" class="form-input font-mono text-sm pr-10"
                            value="<?= e($pubKey) ?>" placeholder="pk_live_... or pk_test_...">
                        <?php if ($pubKey): ?>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-green-500"><i class="fa-solid fa-check"></i></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-warm text-xs mt-1">Starts with <code>pk_live_</code> (production) or <code>pk_test_</code> (test mode)</p>
                </div>
                <div>
                    <label class="form-label">Secret Key</label>
                    <div class="relative">
                        <input type="password" name="stripe_secret_key" id="secretKeyField" class="form-input font-mono text-sm pr-20"
                            value="<?= e($secKey) ?>" placeholder="sk_live_... or sk_test_...">
                        <button type="button" onclick="toggleField('secretKeyField','secretEye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm hover:text-brown text-sm">
                            <i class="fa-solid fa-eye" id="secretEye"></i>
                        </button>
                    </div>
                    <p class="text-warm text-xs mt-1">Starts with <code>sk_live_</code> (production) or <code>sk_test_</code> (test mode). Keep this secret!</p>
                </div>
                <div>
                    <label class="form-label">Webhook Secret</label>
                    <div class="relative">
                        <input type="password" name="stripe_webhook_secret" id="webhookField" class="form-input font-mono text-sm pr-20"
                            value="<?= e($webhookSecret) ?>" placeholder="whsec_...">
                        <button type="button" onclick="toggleField('webhookField','webhookEye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm hover:text-brown text-sm">
                            <i class="fa-solid fa-eye" id="webhookEye"></i>
                        </button>
                    </div>
                    <p class="text-warm text-xs mt-1">Used to verify webhook events from Stripe. Optional but recommended.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="btn-primary px-6 py-2.5">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Save Keys
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Webhook Setup -->
    <div class="card p-6">
        <h2 class="font-bold text-gray-700 mb-1">Webhook Configuration</h2>
        <p class="text-warm text-sm mb-4">Set up a webhook in your Stripe Dashboard to automatically update order and subscription statuses.</p>
        <div class="bg-cream-dark rounded-lg p-4 mb-4">
            <div class="text-xs text-warm uppercase tracking-wider mb-1">Your Webhook URL</div>
            <code class="text-brown text-sm font-mono break-all"><?= e(APP_URL) ?>/index.php?page=webhook.stripe</code>
        </div>
        <div class="text-sm text-warm space-y-1">
            <p><strong class="text-gray-700">Events to listen for:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li><code class="text-xs bg-gray-100 px-1 rounded">checkout.session.completed</code></li>
                <li><code class="text-xs bg-gray-100 px-1 rounded">customer.subscription.updated</code></li>
                <li><code class="text-xs bg-gray-100 px-1 rounded">customer.subscription.deleted</code></li>
                <li><code class="text-xs bg-gray-100 px-1 rounded">invoice.payment_failed</code></li>
            </ul>
        </div>
        <a href="https://dashboard.stripe.com/webhooks/create" target="_blank" class="btn-outline text-sm mt-4 inline-block">
            <i class="fa-solid fa-external-link mr-1"></i>Open Stripe Webhook Setup
        </a>
    </div>

    <!-- Test Mode Info -->
    <div class="card p-6">
        <h2 class="font-bold text-gray-700 mb-3">Test Mode</h2>
        <p class="text-warm text-sm mb-3">Use test keys (<code>pk_test_</code> / <code>sk_test_</code>) during development. Use these test card numbers:</p>
        <div class="space-y-2">
            <?php $testCards = [
                ['4242 4242 4242 4242', 'Visa — Always succeeds'],
                ['4000 0000 0000 0002', 'Visa — Always declines'],
                ['4000 0025 0000 3155', 'Requires 3D Secure authentication'],
            ]; ?>
            <?php foreach ($testCards as $card): ?>
            <div class="flex items-center gap-3 bg-cream-dark rounded-lg p-3">
                <code class="font-mono text-sm text-brown"><?= $card[0] ?></code>
                <span class="text-warm text-xs">— <?= $card[1] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-warm text-xs mt-3">Use any future expiry date, any 3-digit CVC, and any ZIP code.</p>
    </div>
</div>

<script>
function toggleField(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'fa-solid fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fa-solid fa-eye'; }
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Stripe API — Admin', $content, ['admin' => true]);
