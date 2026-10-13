<?php
$db = Database::getInstance();
$isSubscription = (get('subscription') === '1');
$stripeSessionId = get('session_id'); // Stripe appends this if you add {CHECKOUT_SESSION_ID} to success_url
$orderNumber = get('order');
$order = null;
$subscription = null;

if ($isSubscription) {
    // Try to fulfill subscription from pending_subscriptions table
    // (webhook may not have fired yet, especially in local dev)
    $pending = null;
    if ($stripeSessionId) {
        $pending = $db->fetchOne("SELECT * FROM pending_subscriptions WHERE stripe_session_id = ?", [$stripeSessionId]);
    }

    if ($pending) {
        // Check if subscription already created (webhook may have beaten us)
        $existing = $db->fetchOne("SELECT * FROM subscriptions WHERE stripe_session_id = ?", [$stripeSessionId]);
        if (!$existing) {
            // Fetch the Stripe session to get subscription ID and customer
            try {
                $stripeSession = StripeService::getSession($stripeSessionId);
                $stripeSubId    = $stripeSession['subscription'] ?? '';
                $stripeCustomer = $stripeSession['customer'] ?? '';

                if ($stripeSubId) {
                    $alreadyExists = $db->fetchOne("SELECT id FROM subscriptions WHERE stripe_subscription_id = ?", [$stripeSubId]);
                    if (!$alreadyExists) {
                        $db->insert(
                            "INSERT INTO subscriptions (user_id, plan_id, status, stripe_subscription_id, stripe_customer_id, stripe_session_id, delivery_day_id, bread_selections, delivery_address, notes, created_at, updated_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",
                            [
                                $pending['user_id'],
                                $pending['plan_id'],
                                'active',
                                $stripeSubId,
                                $stripeCustomer,
                                $stripeSessionId,
                                $pending['delivery_day_id'] ?? null,
                                $pending['bread_selections'] ?? '',
                                $pending['delivery_address'] ?? '',
                                $pending['notes'] ?? '',
                            ]
                        );
                        if ($stripeCustomer) {
                            $db->execute("UPDATE users SET stripe_customer_id = ? WHERE id = ?", [$stripeCustomer, $pending['user_id']]);
                        }
                    }
                    $db->execute("DELETE FROM pending_subscriptions WHERE stripe_session_id = ?", [$stripeSessionId]);
                }
            } catch (Exception $e) {
                // Stripe not reachable or key not set — subscription will be created by webhook
            }
        }
        $subscription = $db->fetchOne(
            "SELECT s.*, sp.name as plan_name FROM subscriptions s JOIN subscription_plans sp ON s.plan_id = sp.id WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 1",
            [$pending['user_id']]
        );
    } elseif (Auth::isLoggedIn()) {
        $subscription = $db->fetchOne(
            "SELECT s.*, sp.name as plan_name FROM subscriptions s JOIN subscription_plans sp ON s.plan_id = sp.id WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 1",
            [Auth::currentUserId()]
        );
    }
} elseif ($orderNumber) {
    $order = $db->fetchOne("SELECT * FROM orders WHERE order_number = ?", [$orderNumber]);
    // If order is still 'ordered' (webhook hasn't fired yet), confirm payment via Stripe session
    if ($order && $order['status'] === 'ordered' && $stripeSessionId) {
        try {
            $stripeSession = StripeService::getSession($stripeSessionId);
            $paymentStatus = $stripeSession['payment_status'] ?? '';
            $paymentIntent = $stripeSession['payment_intent'] ?? '';
            if ($paymentStatus === 'paid') {
                $db->execute(
                    "UPDATE orders SET status = 'paid', paid_via_stripe = 1, stripe_payment_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$paymentIntent, $order['id']]
                );
                $order['status'] = 'paid';
                $order['paid_via_stripe'] = 1;
            }
        } catch (Exception $e) {
            // Stripe unreachable — webhook will handle it
        }
    }
}

ob_start();
?>
<section class="py-16 px-4">
    <div class="max-w-2xl mx-auto text-center">
        <div class="card p-12">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-check text-green-500 text-4xl"></i>
            </div>
            <?php if ($isSubscription): ?>
            <h1 class="text-3xl font-bold text-brown mb-3">Subscription Confirmed!</h1>
            <?php if ($subscription): ?>
            <p class="text-warm mb-2">You're now subscribed to the <strong class="text-brown"><?= e($subscription['plan_name']) ?></strong>.</p>
            <p class="text-warm text-sm mb-8">Your first delivery will be scheduled shortly. You can manage your subscription from your portal.</p>
            <?php else: ?>
            <p class="text-warm mb-8">Your subscription has been activated! Thank you for joining Loaf'n Ladle.</p>
            <?php endif; ?>
            <div class="bg-cream-dark rounded-xl p-5 mb-8 text-left">
                <h3 class="font-bold text-brown mb-3">What happens next?</h3>
                <ul class="space-y-2 text-sm text-warm">
                    <li class="flex items-start gap-2"><i class="fa-solid fa-envelope text-gold mt-0.5"></i>You'll receive a subscription confirmation email shortly.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-bread-slice text-gold mt-0.5"></i>Your fresh bread will be baked and prepared for your selected delivery day.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-truck text-gold mt-0.5"></i>You'll be notified before each weekly delivery.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-rotate text-gold mt-0.5"></i>Cancel or pause anytime from your client portal.</li>
                </ul>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <?php if (Auth::isLoggedIn()): ?>
                <a href="<?= pageUrl('portal') ?>" class="btn-primary px-6 py-2.5">Go to My Portal</a>
                <?php endif; ?>
                <a href="<?= pageUrl('shop') ?>" class="btn-outline px-6 py-2.5">Continue Shopping</a>
            </div>
            <?php else: ?>
            <h1 class="text-3xl font-bold text-brown mb-3">Order Confirmed!</h1>
            <?php if ($order): ?>
            <p class="text-warm mb-2">Your order <strong class="text-brown"><?= e($order['order_number']) ?></strong> has been placed successfully.</p>
            <p class="text-warm text-sm mb-8">A confirmation email will be sent to <strong><?= e($order['delivery_email']) ?></strong>.</p>
            <?php else: ?>
            <p class="text-warm mb-8">Your order has been placed successfully. Thank you for choosing Loaf'n Ladle!</p>
            <?php endif; ?>
            <div class="bg-cream-dark rounded-xl p-5 mb-8 text-left">
                <h3 class="font-bold text-brown mb-3">What happens next?</h3>
                <ul class="space-y-2 text-sm text-warm">
                    <li class="flex items-start gap-2"><i class="fa-solid fa-envelope text-gold mt-0.5"></i>You'll receive an order confirmation email shortly.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-bread-slice text-gold mt-0.5"></i>Our team will begin preparing your fresh order.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-truck text-gold mt-0.5"></i>You'll be notified when your order is ready for pickup or out for delivery.</li>
                </ul>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <?php if (Auth::isLoggedIn()): ?>
                <a href="<?= pageUrl('portal.orders') ?>" class="btn-primary px-6 py-2.5">View My Orders</a>
                <?php endif; ?>
                <a href="<?= pageUrl('shop') ?>" class="btn-outline px-6 py-2.5">Continue Shopping</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Order Confirmed', $content);
