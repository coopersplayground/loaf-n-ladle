<?php
Auth::requireLogin();
$db = Database::getInstance();
$planId = (int)get('plan_id');
$plan = $db->fetchOne("SELECT * FROM subscription_plans WHERE id = ? AND active = 1", [$planId]);
if (!$plan) { redirect(pageUrl('subscriptions')); }

$features = json_decode($plan['features'] ?? '[]', true);
$stripeConfigured = StripeService::isConfigured();
$user = Auth::currentUser();

// Get all delivery days (including blocked ones)
$deliveryDays = $db->fetchAll("SELECT * FROM delivery_days ORDER BY sort_order");

// Get plan items for this subscription plan
$planItems = $db->fetchAll(
    "SELECT spi.*, c.name as category_name, c.slug as category_slug
     FROM subscription_plan_items spi
     JOIN categories c ON spi.category_id = c.id
     WHERE spi.plan_id = ? ORDER BY spi.sort_order",
    [$planId]
);

foreach ($planItems as &$item) {
    $item['products'] = $db->fetchAll(
        "SELECT p.* FROM subscription_plan_item_products spip
         JOIN products p ON spip.product_id = p.id
         WHERE spip.plan_item_id = ? AND p.available = 1 ORDER BY spip.sort_order",
        [$item['id']]
    );
}
unset($item);

// Handle POST - create subscription checkout
if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId])); }

    if (!$stripeConfigured) {
        flash('error', 'Payment system not configured. Please contact us.');
        redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
    }

    // Validate selections
    $selections = post('selections', []);
    $selectedProducts = [];
    $totalLoaves = 0;
    
    // Check if plan has total max selections override
    $maxSelectionsLimit = $plan['total_max_selections'] ?? null;
    
    foreach ($planItems as $item) {
        $categorySelections = $selections[$item['id']] ?? [];
        
        // Always enforce individual category limits
        if (count($categorySelections) > $item['max_selections']) {
            flash('error', 'Please select no more than ' . $item['max_selections'] . ' items from ' . e($item['category_name']));
            redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
        }
        
        $selectedProducts = array_merge($selectedProducts, $categorySelections);
        $totalLoaves += count($categorySelections);
    }
    
    // Validate total selections (if total max is set, it overrides the loaves_per_week requirement)
    $requiredLoaves = $maxSelectionsLimit ?? $plan['loaves_per_week'];
    if ($totalLoaves != $requiredLoaves) {
        $limitType = $maxSelectionsLimit ? 'total selections' : 'items';
        flash('error', 'Please select exactly ' . $requiredLoaves . ' ' . $limitType . '.');
        redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
    }
    
    // Validate delivery day
    $deliveryDayId = (int)post('delivery_day');
    $deliveryDay = $db->fetchOne("SELECT * FROM delivery_days WHERE id = ? AND is_available = 1", [$deliveryDayId]);
    if (!$deliveryDay) {
        flash('error', 'Please select a valid delivery day.');
        redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
    }
    
    // Check if delivery day is at capacity
    $currentCount = $db->fetchOne("SELECT COUNT(*) as c FROM subscription_deliveries WHERE delivery_day_id = ? AND status != 'cancelled'", [$deliveryDayId])['c'] ?? 0;
    if ($currentCount >= $deliveryDay['max_deliveries']) {
        flash('error', 'Selected delivery day is full. Please choose another day.');
        redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
    }

    $deliveryAddress = sanitize(post('delivery_address', $user['address'] ?? ''));
    $notes = sanitize(post('notes', ''));
    $frequency = sanitize(post('frequency', 'weekly'));

    // Gift loaf add-on
    $addGiftLoaf       = (int)post('add_gift_loaf') === 1;
    $giftRecipientName = sanitize(post('gift_recipient_name', ''));
    $giftRecipientAddr = sanitize(post('gift_recipient_address', ''));
    $giftNote          = sanitize(post('gift_note', ''));

    // Get product names for storage
    $productNames = [];
    foreach ($selectedProducts as $productId) {
        $product = $db->fetchOne("SELECT name FROM products WHERE id = ?", [$productId]);
        if ($product) $productNames[] = $product['name'];
    }

    try {
        $successUrl = pageUrl('checkout.success') . '&subscription=1&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = pageUrl('subscribe.checkout', ['plan_id' => $planId]);
        $session = StripeService::createSubscriptionSession(
            $plan['name'],
            $plan['price_per_week'],
            $plan['stripe_price_id'] ?? '',
            $user['email'],
            $successUrl,
            $cancelUrl,
            $user['id'],
            $addGiftLoaf
        );

        // Store pending subscription data in DB keyed by Stripe session ID
        $db->insert(
            "INSERT OR REPLACE INTO pending_subscriptions
             (stripe_session_id, user_id, plan_id, bread_selections, delivery_address, notes, frequency, delivery_day_id, gift_loaf, gift_recipient_name, gift_recipient_address, gift_note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $session['id'],
                $user['id'],
                $planId,
                implode(', ', $productNames),
                $deliveryAddress,
                $notes,
                $frequency,
                $deliveryDayId,
                $addGiftLoaf ? 1 : 0,
                $giftRecipientName,
                $giftRecipientAddr,
                $giftNote,
            ]
        );

        header('Location: ' . $session['url']);
        exit;
    } catch (Exception $e) {
        flash('error', 'Payment error: ' . $e->getMessage());
        redirect(pageUrl('subscribe.checkout', ['plan_id' => $planId]));
    }
}

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-brown">Subscribe to <?= e($plan['name']) ?></h1>
        <p class="text-warm mt-1">Customize your weekly bread selection</p>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Selection Form -->
            <div class="lg:col-span-2">
                <form method="POST" action="<?= pageUrl('subscribe.checkout', ['plan_id' => $planId]) ?>" id="subscribeForm">
                    <!-- Hidden fields for gift loaf (populated by modal) -->
                    <input type="hidden" name="add_gift_loaf" id="addGiftLoaf" value="0">
                    <input type="hidden" name="gift_recipient_name" id="giftRecipientName" value="">
                    <input type="hidden" name="gift_recipient_address" id="giftRecipientAddress" value="">
                    <input type="hidden" name="gift_note" id="giftNote" value="">
                    <?= csrfField() ?>
                    
                    <?php if (empty($planItems)): ?>
                    <div class="card p-8 text-center">
                        <i class="fa-solid fa-exclamation-triangle text-5xl text-warm opacity-20 mb-4"></i>
                        <h2 class="text-xl font-bold text-brown mb-2">No Items Available</h2>
                        <p class="text-warm mb-4">This subscription plan doesn't have any items configured yet.</p>
                        <a href="<?= pageUrl('subscriptions') ?>" class="btn-primary">View Other Plans</a>
                    </div>
                    <?php else: ?>
                    
                    <!-- Plan Summary -->
                    <div class="card p-6 mb-6">
                        <h2 class="font-bold text-brown text-xl mb-3">Plan Details</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-warm text-xs uppercase tracking-wider mb-1">Weekly Price</div>
                                <div class="text-2xl font-bold text-brown"><?= formatMoney($plan['price_per_week']) ?></div>
                            </div>
                            <div>
                                <div class="text-warm text-xs uppercase tracking-wider mb-1">Items Required</div>
                                <div class="text-2xl font-bold text-brown">
                                    <?php if ($plan['total_max_selections']): ?>
                                        <?= $plan['total_max_selections'] ?> total selections
                                    <?php else: ?>
                                        <?= $plan['loaves_per_week'] ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($plan['total_max_selections']): ?>
                                <div class="text-xs text-warm mt-1">Total max across all categories</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($features)): ?>
                        <div class="mt-4 pt-4 border-t border-gold/20">
                            <div class="text-warm text-xs uppercase tracking-wider mb-2">Features</div>
                            <ul class="space-y-1">
                                <?php foreach ($features as $feature): ?>
                                <li class="text-sm text-warm flex items-center gap-2">
                                    <i class="fa-solid fa-check text-green-500 text-xs"></i><?= e($feature) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Item Selections -->
                    <div class="space-y-6">
                        <?php foreach ($planItems as $item): ?>
                        <div class="card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-brown text-lg"><?= e($item['category_name']) ?></h3>
                                <span class="badge badge-yellow">Select up to <?= $item['max_selections'] ?></span>
                            </div>
                            
                            <?php if (empty($item['products'])): ?>
                            <p class="text-warm text-sm">No products available in this category.</p>
                            <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($item['products'] as $product): ?>
                                <label class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 hover:shadow-md transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:shadow-lg transform has-[:checked]:scale-105">
                                    <input type="checkbox" 
                                        name="selections[<?= $item['id'] ?>][]" 
                                        value="<?= $product['id'] ?>"
                                        class="sr-only product-checkbox"
                                        data-category="<?= $item['id'] ?>"
                                        data-max="<?= $item['max_selections'] ?>"
                                        onchange="updateSelectionCount()">
                                    <div class="text-center">
                                        <div class="font-semibold text-brown flex items-center justify-center gap-2">
                                            <span><?= e($product['name']) ?></span>
                                            <i class="fa-solid fa-check-circle text-green-500 opacity-0 has-[:checked]:opacity-100 transition-opacity"></i>
                                        </div>
                                        <div class="text-warm text-sm mt-1"><?= e($product['description']) ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Delivery Frequency -->
                    <div class="card p-6 mt-6">
                        <h3 class="font-bold text-brown text-xl mb-4">Delivery Frequency</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <label class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                <input type="radio" name="frequency" value="weekly" class="sr-only" <?= ($plan['frequency'] ?? 'weekly') === 'weekly' ? 'checked' : '' ?> onchange="updateFrequencyDisplay()">
                                <div class="text-center">
                                    <div class="font-semibold text-brown mb-2">Weekly</div>
                                    <div class="text-warm text-sm">Every week</div>
                                    <div class="text-green-600 font-semibold mt-2"><?= formatMoney($plan['price_per_week']) ?>/week</div>
                                </div>
                            </label>
                            
                            <label class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                <input type="radio" name="frequency" value="bi-weekly" class="sr-only" onchange="updateFrequencyDisplay()">
                                <div class="text-center">
                                    <div class="font-semibold text-brown mb-2">Bi-Weekly</div>
                                    <div class="text-warm text-sm">Every 2 weeks</div>
                                    <div class="text-green-600 font-semibold mt-2"><?= formatMoney($plan['price_bi_weekly']) ?>/delivery</div>
                                </div>
                            </label>
                            
                            <label class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                <input type="radio" name="frequency" value="monthly" class="sr-only" onchange="updateFrequencyDisplay()">
                                <div class="text-center">
                                    <div class="font-semibold text-brown mb-2">Monthly</div>
                                    <div class="text-warm text-sm">Once per month</div>
                                    <div class="text-green-600 font-semibold mt-2"><?= formatMoney($plan['price_monthly']) ?>/month</div>
                                </div>
                            </label>
                            
                            <?php if ($plan['price_one_time']): ?>
                            <label class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                <input type="radio" name="frequency" value="one-time" class="sr-only" onchange="updateFrequencyDisplay()">
                                <div class="text-center">
                                    <div class="font-semibold text-brown mb-2">One Time</div>
                                    <div class="text-warm text-sm">Single delivery</div>
                                    <div class="text-green-600 font-semibold mt-2"><?= formatMoney($plan['price_one_time']) ?></div>
                                </div>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Delivery Day Selection -->
                    <div class="card p-6 mt-6">
                        <h3 class="font-bold text-brown text-xl mb-4">Delivery Day</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                            <?php foreach ($deliveryDays as $day): ?>
                            <?php 
                            // Check if day is blocked
                            $isBlocked = !$day['is_available'];
                            // Check current delivery count for this day
                            $currentCount = $db->fetchOne("SELECT COUNT(*) as c FROM subscription_deliveries WHERE delivery_day_id = ? AND status != 'cancelled'", [$day['id']])['c'] ?? 0;
                            $remaining = $day['max_deliveries'] - $currentCount;
                            $isFull = $remaining <= 0;
                            ?>
                            <label class="border-2 border-gray-200 rounded-lg p-3 cursor-pointer hover:border-green-500 transition-all duration-200 has-[:checked]:border-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 text-center <?= ($isBlocked || $isFull) ? 'opacity-50 cursor-not-allowed bg-red-50 border-red-200' : '' ?>">
                                <?php if (!$isBlocked && !$isFull): ?>
                                <input type="radio" name="delivery_day" value="<?= $day['id'] ?>" class="sr-only" required>
                                <?php endif; ?>
                                <div class="font-semibold text-brown text-sm mb-1"><?= e($day['day_name']) ?></div>
                                <div class="text-xs text-warm">
                                    <?php if ($isBlocked): ?>
                                        <span class="text-red-600 font-bold">FULL</span>
                                    <?php elseif ($isFull): ?>
                                        <span class="text-red-600 font-bold">FULL</span>
                                    <?php elseif ($remaining <= 2): ?>
                                        <span class="text-orange-600"><?= $remaining ?> left</span>
                                    <?php else: ?>
                                        <span class="text-green-600">Available</span>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                            <?php if (empty($deliveryDays)): ?>
                            <div class="col-span-full text-center text-warm">
                                <i class="fa-solid fa-calendar-xmark text-3xl mb-2 opacity-30"></i>
                                <p>No delivery days available</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-warm mt-3">
                            <i class="fa-solid fa-info-circle mr-1"></i>
                            Select your preferred delivery day. Days marked as "FULL" have reached maximum capacity.
                        </p>
                    </div>

                    <!-- Delivery Info -->
                    <div class="card p-6 mt-6">
                        <h3 class="font-bold text-brown text-xl mb-4">Delivery Information</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Delivery Address</label>
                                <textarea name="delivery_address" rows="3" class="form-input" placeholder="Enter your delivery address..." required><?= e($user['address'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="form-label">Special Instructions (Optional)</label>
                                <textarea name="notes" rows="3" class="form-input" placeholder="Any special delivery instructions..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="mt-6">
                        <div class="bg-cream-dark rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="text-warm">Total Selections:</span>
                                <span class="font-bold text-brown text-lg" id="selectionCount">0 / <?= $plan['total_max_selections'] ?? $plan['loaves_per_week'] ?></span>
                            </div>
                            <?php if ($plan['total_max_selections']): ?>
                            <div class="text-xs text-warm mt-2">Category limits still apply</div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$stripeConfigured): ?>
                        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-4">
                            <i class="fa-solid fa-triangle-exclamation text-yellow-500 mr-2"></i>
                            <span class="text-yellow-800">Payment system is currently being configured. Please check back later.</span>
                        </div>
                        <?php else: ?>
                        <button type="submit" class="btn-primary w-full py-3 text-lg" id="subscribeBtn" disabled>
                            <i class="fa-brands fa-stripe mr-2"></i>Subscribe with Stripe
                        </button>
                        <p class="text-xs text-warm text-center mt-2">Payment system provided by HyndSyte.com through Stripe.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="card p-6 sticky top-6">
                    <h3 class="font-bold text-brown text-xl mb-4">Subscription Summary</h3>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-warm">Plan:</span>
                            <span class="font-semibold text-brown"><?= e($plan['name']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-warm">Frequency:</span>
                            <span class="font-semibold text-brown" id="selectedFrequency">Weekly</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-warm">Weekly Price:</span>
                            <span class="font-bold text-brown"><?= formatMoney($plan['price_per_week']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-warm">Items:</span>
                            <span class="font-semibold text-brown">
                                <?php if ($plan['total_max_selections']): ?>
                                    <?= $plan['total_max_selections'] ?> total selections
                                <?php else: ?>
                                    <?= e($plan['loaves_per_week']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-gold/20 pt-4">
                        <h4 class="font-semibold text-brown mb-2">What happens next:</h4>
                        <ul class="space-y-2 text-sm text-warm">
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-green-500 mt-0.5"></i>
                                <span>Select your bread items for weekly delivery</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-green-500 mt-0.5"></i>
                                <span>Secure payment via Stripe</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-green-500 mt-0.5"></i>
                                <span>Cancel anytime from your portal</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-green-500 mt-0.5"></i>
                                <span>First delivery within 3-5 days</span>
                            </li>
                        </ul>
                    </div>

                    <a href="<?= pageUrl('subscriptions') ?>" class="btn-outline w-full mt-6 text-center block">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Back to Plans
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Loaves of Love Gift Modal -->
<div id="loavesOfLoveModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="modalBackdrop"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden">
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 px-8 py-6 text-white text-center">
            <div class="text-4xl mb-2">🍞❤️</div>
            <h2 class="text-2xl font-bold leading-tight">Send a Loaf to a Friend!</h2>
            <p class="text-amber-100 text-sm mt-1 font-medium">& We'll Donate a Loaf!</p>
        </div>
        <div class="px-8 py-6">
            <p class="text-gray-700 text-center mb-1 font-medium">Send a gift with <span class="text-amber-600 font-bold">Loaves of Love</span></p>
            <p class="text-gray-500 text-sm text-center mb-5">Send a friend a delicious loaf and we'll donate a loaf to a local food pantry!</p>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 flex items-center gap-3">
                <div class="text-2xl">🎁</div>
                <div>
                    <p class="font-bold text-amber-800 text-sm">Add a one-time loaf for just <span class="text-lg">$10</span></p>
                    <p class="text-amber-700 text-xs">One-time charge — your regular subscription won't include this extra $10.</p>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Friend's Name <span class="text-red-500">*</span></label>
                    <input type="text" id="giftNameInput" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" placeholder="Jane Smith">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Delivery Address <span class="text-red-500">*</span></label>
                    <input type="text" id="giftAddressInput" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" placeholder="123 Main St, City, State 12345">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Personal Note <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="giftNoteInput" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" placeholder="Thinking of you! Enjoy this fresh loaf..."></textarea>
                </div>
                <p id="giftFormError" class="text-red-500 text-xs hidden">Please enter your friend's name and address.</p>
            </div>
        </div>
        <div class="px-8 pb-7 flex flex-col sm:flex-row gap-3">
            <button type="button" id="btnAddGift"
                class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-4 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-gift"></i> Add to My Order — $10
            </button>
            <button type="button" id="btnSkipGift"
                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold py-3 px-4 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right"></i> Not This Time, Take Me to Payment
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('loavesOfLoveModal');
    var form  = document.getElementById('subscribeForm');
    var btnAdd  = document.getElementById('btnAddGift');
    var btnSkip = document.getElementById('btnSkipGift');
    var giftError = document.getElementById('giftFormError');
    var modalShown = false;

    function showModal() {
        modal.style.display = 'flex';
    }
    function hideModal() {
        modal.style.display = 'none';
    }

    form.addEventListener('submit', function(e) {
        if (!modalShown) {
            e.preventDefault();
            showModal();
        }
    });

    btnAdd.addEventListener('click', function() {
        var name    = document.getElementById('giftNameInput').value.trim();
        var address = document.getElementById('giftAddressInput').value.trim();
        var note    = document.getElementById('giftNoteInput').value.trim();
        if (!name || !address) {
            giftError.classList.remove('hidden');
            return;
        }
        giftError.classList.add('hidden');
        document.getElementById('addGiftLoaf').value          = '1';
        document.getElementById('giftRecipientName').value    = name;
        document.getElementById('giftRecipientAddress').value = address;
        document.getElementById('giftNote').value             = note;
        modalShown = true;
        hideModal();
        form.submit();
    });

    btnSkip.addEventListener('click', function() {
        document.getElementById('addGiftLoaf').value = '0';
        modalShown = true;
        hideModal();
        form.submit();
    });

    document.getElementById('modalBackdrop').addEventListener('click', function() {
        document.getElementById('addGiftLoaf').value = '0';
        modalShown = true;
        hideModal();
        form.submit();
    });
})();
</script>

<script>
function updateSelectionCount() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const count = checkboxes.length;
    const required = <?= $plan['total_max_selections'] ?? $plan['loaves_per_week'] ?>;
    const hasTotalMax = <?= $plan['total_max_selections'] ? 'true' : 'false' ?>;
    const countElement = document.getElementById('selectionCount');
    const submitBtn = document.getElementById('subscribeBtn');
    
    countElement.textContent = `${count} / ${required}`;
    
    if (count === required) {
        countElement.className = 'font-bold text-green-600 text-lg';
        submitBtn.disabled = false;
    } else {
        countElement.className = 'font-bold text-brown text-lg';
        submitBtn.disabled = true;
    }
    
    // Always enforce max selections per category
    const categoryData = {};
    checkboxes.forEach(cb => {
        const category = cb.dataset.category;
        const max = parseInt(cb.dataset.max);
        if (!categoryData[category]) categoryData[category] = [];
        categoryData[category].push(cb);
    });
    
    // First, reset all checkboxes
    const allCheckboxes = document.querySelectorAll('.product-checkbox');
    allCheckboxes.forEach(cb => {
        cb.disabled = false;
        cb.parentElement.classList.remove('opacity-50');
    });
    
    // Then apply category limits
    Object.keys(categoryData).forEach(category => {
        const categoryCheckboxes = document.querySelectorAll(`.product-checkbox[data-category="${category}"]`);
        const max = parseInt(categoryCheckboxes[0].dataset.max);
        const selected = categoryData[category].length;
        
        categoryCheckboxes.forEach(cb => {
            if (!cb.checked && selected >= max) {
                cb.disabled = true;
                cb.parentElement.classList.add('opacity-50');
            }
        });
    });
    
    // Finally, apply total limit - disable ALL unchecked checkboxes if total reached
    if (count >= required) {
        allCheckboxes.forEach(cb => {
            if (!cb.checked) {
                cb.disabled = true;
                cb.parentElement.classList.add('opacity-50');
            }
        });
    }
}

function updateFrequencyDisplay() {
    const selectedFrequency = document.querySelector('input[name="frequency"]:checked')?.value || 'weekly';
    const frequencyElement = document.getElementById('selectedFrequency');
    if (frequencyElement) {
        const frequencyText = {
            'weekly': 'Weekly',
            'bi-weekly': 'Bi-Weekly', 
            'monthly': 'Monthly',
            'one-time': 'One Time'
        };
        frequencyElement.textContent = frequencyText[selectedFrequency];
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectionCount();
    updateFrequencyDisplay();
});
</script>
<?php
$content = ob_get_clean();
renderLayout('Subscribe to ' . e($plan['name']), $content);
