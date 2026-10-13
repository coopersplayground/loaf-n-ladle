<?php
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance();

// Handle cancel subscription
if (isPost() && post('action') === 'cancel') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('portal.subscriptions')); }
    $subId = (int)post('subscription_id');
    $sub = $db->fetchOne("SELECT * FROM subscriptions WHERE id = ? AND user_id = ?", [$subId, $user['id']]);
    if ($sub) {
        if (!empty($sub['stripe_subscription_id'])) {
            try { StripeService::cancelSubscription($sub['stripe_subscription_id']); } catch (Exception $e) {}
        }
        $db->execute("UPDATE subscriptions SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$subId]);
        flash('success', 'Subscription cancelled successfully.');
    }
    redirect(pageUrl('portal.subscriptions'));
}

$subscriptions = $db->fetchAll(
    "SELECT s.*, p.name as plan_name, p.price_per_week, p.loaves_per_week, p.description as plan_description
     FROM subscriptions s JOIN subscription_plans p ON s.plan_id = p.id
     WHERE s.user_id = ? ORDER BY s.created_at DESC",
    [$user['id']]
);

$statusColors = ['active'=>'badge-green','paused'=>'badge-yellow','cancelled'=>'badge-red','past_due'=>'badge-red'];

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-brown">My Subscriptions</h1>
            <p class="text-warm mt-1">Manage your weekly bread deliveries</p>
        </div>
        <a href="<?= pageUrl('subscriptions') ?>" class="btn-primary">
            <i class="fa-solid fa-plus mr-2"></i>New Subscription
        </a>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <!-- Portal Nav Tabs -->
        <div class="flex gap-2 mb-8 border-b border-gold/20 pb-0">
            <a href="<?= pageUrl('portal') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Dashboard</a>
            <a href="<?= pageUrl('portal.orders') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Orders</a>
            <a href="<?= pageUrl('portal.subscriptions') ?>" class="tab-btn active border-b-2 border-brown rounded-b-none pb-3">Subscriptions</a>
            <a href="<?= pageUrl('portal.profile') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Profile</a>
        </div>

        <?php if (empty($subscriptions)): ?>
        <div class="card p-16 text-center">
            <i class="fa-solid fa-rotate text-5xl text-brown opacity-20 mb-4"></i>
            <h2 class="text-xl font-bold text-brown mb-2">No subscriptions yet</h2>
            <p class="text-warm mb-6">Subscribe for weekly fresh bread delivery!</p>
            <a href="<?= pageUrl('subscriptions') ?>" class="btn-primary px-8 py-3">Browse Plans</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($subscriptions as $sub): ?>
            <div class="card p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-brown text-xl"><?= e($sub['plan_name']) ?></h3>
                        <p class="text-warm text-sm mt-1"><?= e($sub['plan_description']) ?></p>
                    </div>
                    <span class="badge <?= $statusColors[$sub['status']] ?? 'badge-gray' ?>"><?= ucfirst($sub['status']) ?></span>
                </div>

                <div class="bg-cream-dark rounded-lg p-4 mb-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-warm text-xs uppercase tracking-wider mb-1">Weekly Price</div>
                        <div class="font-bold text-brown text-lg"><?= formatMoney($sub['price_per_week']) ?></div>
                    </div>
                    <div>
                        <div class="text-warm text-xs uppercase tracking-wider mb-1">Loaves/Week</div>
                        <div class="font-bold text-brown text-lg"><?= $sub['loaves_per_week'] ?></div>
                    </div>
                    <?php if ($sub['bread_selections']): ?>
                    <div class="col-span-2">
                        <div class="text-warm text-xs uppercase tracking-wider mb-1">Bread Selections</div>
                        <div class="text-brown text-sm"><?= e($sub['bread_selections']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($sub['delivery_address']): ?>
                    <div class="col-span-2">
                        <div class="text-warm text-xs uppercase tracking-wider mb-1">Delivery Address</div>
                        <div class="text-brown text-sm"><?= e($sub['delivery_address']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-warm text-xs uppercase tracking-wider mb-1">Started</div>
                        <div class="text-brown text-sm"><?= date('M j, Y', strtotime($sub['created_at'])) ?></div>
                    </div>
                </div>

                <?php if ($sub['status'] === 'active'): ?>
                <div class="flex gap-3">
                    <a href="<?= pageUrl('subscriptions') ?>" class="btn-outline text-sm flex-1 text-center">Change Plan</a>
                    <form method="POST" action="<?= pageUrl('portal.subscriptions') ?>" onsubmit="return confirm('Are you sure you want to cancel this subscription?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                        <button type="submit" class="btn-outline text-sm text-red-500 border-red-300 hover:bg-red-500 hover:text-white hover:border-red-500 px-4">
                            Cancel
                        </button>
                    </form>
                </div>
                <?php elseif ($sub['status'] === 'cancelled'): ?>
                <a href="<?= pageUrl('subscriptions') ?>" class="btn-primary text-sm w-full text-center block">Resubscribe</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('My Subscriptions', $content);
