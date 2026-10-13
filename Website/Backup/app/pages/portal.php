<?php
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance();

$recentOrders = $db->fetchAll(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);
$activeSubscriptions = $db->fetchAll(
    "SELECT s.*, p.name as plan_name, p.price_per_week, p.loaves_per_week
     FROM subscriptions s JOIN subscription_plans p ON s.plan_id = p.id
     WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.created_at DESC",
    [$user['id']]
);
$totalOrders = $db->fetchOne("SELECT COUNT(*) as c FROM orders WHERE user_id = ?", [$user['id']])['c'];
$totalSpent = $db->fetchOne("SELECT SUM(total) as s FROM orders WHERE user_id = ? AND status != 'cancelled'", [$user['id']])['s'] ?? 0;

// Get loyalty data
$loyaltyData = $db->fetchOne("SELECT * FROM user_loyalty WHERE user_id = ?", [$user['id']]);
if (!$loyaltyData) {
    // Initialize loyalty record for new users
    $db->insert("INSERT INTO user_loyalty (user_id, total_loaves, free_loaves_earned) VALUES (?, ?, ?)", [$user['id'], 0, 0]);
    $loyaltyData = ['total_loaves' => 0, 'free_loaves_earned' => 0, 'last_free_loaf_at' => null];
}

// Calculate progress towards next free loaf
$loavesForNextFree = 10 - ($loyaltyData['total_loaves'] % 10);
$progressPercent = (($loyaltyData['total_loaves'] % 10) / 10) * 100;
$isCloseToFree = $loavesForNextFree <= 2;

$statusColors = ['pending'=>'badge-yellow','processing'=>'badge-blue','completed'=>'badge-green','cancelled'=>'badge-red','paid'=>'badge-green'];

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-brown">My Account</h1>
            <p class="text-warm mt-1">Welcome back, <?= e($user['name']) ?>!</p>
        </div>
        <a href="<?= pageUrl('shop') ?>" class="btn-primary">
            <i class="fa-solid fa-basket-shopping mr-2"></i>Shop Now
        </a>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <!-- Portal Nav Tabs -->
        <div class="flex gap-2 mb-8 border-b border-gold/20 pb-0">
            <a href="<?= pageUrl('portal') ?>" class="tab-btn active border-b-2 border-brown rounded-b-none pb-3">Dashboard</a>
            <a href="<?= pageUrl('portal.orders') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Orders</a>
            <a href="<?= pageUrl('portal.subscriptions') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Subscriptions</a>
            <a href="<?= pageUrl('portal.profile') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Profile</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Club Perks Loyalty Card -->
            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl transform rotate-1"></div>
                <div class="relative bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fa-solid fa-crown text-yellow-300 text-xl"></i>
                                <h3 class="font-bold text-xl">Bread Club</h3>
                            </div>
                            <p class="text-amber-100 text-sm">Your loyalty rewards</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold"><?= $loyaltyData['total_loaves'] ?></div>
                            <div class="text-xs text-amber-100">Total Loaves</div>
                        </div>
                    </div>
                    
                    <div class="bg-white/20 rounded-lg p-4 backdrop-blur-sm mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">Progress to Next Free Loaf</span>
                            <span class="text-sm font-bold"><?= $loyaltyData['total_loaves'] % 10 ?>/10</span>
                        </div>
                        <div class="w-full bg-white/30 rounded-full h-3 mb-2">
                            <div class="bg-white rounded-full h-3 transition-all duration-500" style="width: <?= $progressPercent ?>%"></div>
                        </div>
                        <div class="text-center">
                            <?php if ($loavesForNextFree === 10): ?>
                                <span class="text-xs text-amber-100">Start your journey to a free loaf!</span>
                            <?php elseif ($loavesForNextFree === 1): ?>
                                <span class="text-sm font-bold text-yellow-300 animate-pulse">🎉 One more loaf for FREE!</span>
                            <?php else: ?>
                                <span class="text-xs text-amber-100"><?= $loavesForNextFree ?> more loaf<?= $loavesForNextFree !== 1 ? 's' : '' ?> to go</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs text-amber-100 mb-1">Free Loaves Earned</div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-gift text-yellow-300"></i>
                                <span class="text-xl font-bold"><?= $loyaltyData['free_loaves_earned'] ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-amber-100 mb-1">Member Since</div>
                            <div class="text-sm font-medium"><?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></div>
                        </div>
                    </div>
                    
                    <?php if ($loyaltyData['free_loaves_earned'] > 0): ?>
                    <div class="mt-4 pt-4 border-t border-white/30">
                        <div class="flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-check-circle text-green-300"></i>
                            <span class="text-amber-100">Last free loaf: <?= $loyaltyData['last_free_loaf_at'] ? date('M j, Y', strtotime($loyaltyData['last_free_loaf_at'])) : 'Never' ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Recent Orders -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-bold text-brown text-xl">Recent Orders</h2>
                    <a href="<?= pageUrl('portal.orders') ?>" class="text-brown text-sm hover:underline">View all</a>
                </div>
                <?php if (empty($recentOrders)): ?>
                <div class="text-center py-8 text-warm">
                    <i class="fa-solid fa-receipt text-3xl mb-3 opacity-30"></i>
                    <p>No orders yet.</p>
                    <a href="<?= pageUrl('shop') ?>" class="btn-outline mt-3 text-sm">Start Shopping</a>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentOrders as $order): ?>
                    <div class="flex items-center justify-between p-3 bg-cream-dark rounded-lg">
                        <div>
                            <div class="font-semibold text-brown text-sm"><?= e($order['order_number']) ?></div>
                            <div class="text-warm text-xs"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="badge <?= $statusColors[$order['status']] ?? 'badge-gray' ?>"><?= ucfirst($order['status']) ?></span>
                            <span class="font-bold text-brown text-sm"><?= formatMoney($order['total']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Active Subscriptions -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-bold text-brown text-xl">My Subscriptions</h2>
                    <a href="<?= pageUrl('portal.subscriptions') ?>" class="text-brown text-sm hover:underline">Manage</a>
                </div>
                <?php if (empty($activeSubscriptions)): ?>
                <div class="text-center py-8 text-warm">
                    <i class="fa-solid fa-rotate text-3xl mb-3 opacity-30"></i>
                    <p>No active subscriptions.</p>
                    <a href="<?= pageUrl('subscriptions') ?>" class="btn-outline mt-3 text-sm">Browse Plans</a>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($activeSubscriptions as $sub): ?>
                    <div class="p-4 bg-cream-dark rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-brown"><?= e($sub['plan_name']) ?></div>
                            <span class="badge badge-green">Active</span>
                        </div>
                        <div class="text-warm text-sm">
                            <?= $sub['loaves_per_week'] ?> loaves/week · <?= formatMoney($sub['price_per_week']) ?>/week
                        </div>
                        <?php if ($sub['bread_selections']): ?>
                        <div class="text-warm text-xs mt-1">
                            <i class="fa-solid fa-bread-slice mr-1"></i><?= e($sub['bread_selections']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <a href="<?= pageUrl('shop') ?>" class="card p-5 text-center hover:no-underline">
                <i class="fa-solid fa-basket-shopping text-brown text-2xl mb-2"></i>
                <div class="font-semibold text-brown text-sm">Shop Now</div>
            </a>
            <a href="<?= pageUrl('subscriptions') ?>" class="card p-5 text-center hover:no-underline">
                <i class="fa-solid fa-rotate text-brown text-2xl mb-2"></i>
                <div class="font-semibold text-brown text-sm">Subscribe</div>
            </a>
            <a href="<?= pageUrl('catering') ?>" class="card p-5 text-center hover:no-underline">
                <i class="fa-solid fa-utensils text-brown text-2xl mb-2"></i>
                <div class="font-semibold text-brown text-sm">Catering</div>
            </a>
            <a href="<?= pageUrl('portal.profile') ?>" class="card p-5 text-center hover:no-underline">
                <i class="fa-solid fa-user-pen text-brown text-2xl mb-2"></i>
                <div class="font-semibold text-brown text-sm">Edit Profile</div>
            </a>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('My Account', $content);
