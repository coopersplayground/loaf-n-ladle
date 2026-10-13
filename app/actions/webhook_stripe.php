<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/stripe.php';

// Webhook must be raw POST
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret    = setting('stripe_webhook_secret');

if (!empty($secret) && !empty($sigHeader)) {
    if (!StripeService::verifyWebhook($payload, $sigHeader, $secret)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$db = Database::getInstance();
$type = $event['type'] ?? '';
$data = $event['data']['object'] ?? [];

switch ($type) {

    case 'checkout.session.completed':
        $sessionId   = $data['id'] ?? '';
        $mode        = $data['mode'] ?? '';
        $paymentIntent = $data['payment_intent'] ?? '';
        $metadata    = $data['metadata'] ?? [];

        if ($mode === 'payment') {
            // One-time order
            $order = $db->fetchOne("SELECT * FROM orders WHERE stripe_session_id = ?", [$sessionId]);
            if ($order) {
                $db->execute(
                    "UPDATE orders SET status = 'paid', paid_via_stripe = 1, stripe_payment_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$paymentIntent, $order['id']]
                );
            }
        } elseif ($mode === 'subscription') {
            // Subscription checkout completed
            $stripeSubId    = $data['subscription'] ?? '';
            $stripeCustomer = $data['customer'] ?? '';
            $userId         = $metadata['user_id'] ?? null;

            if ($stripeSubId) {
                // Load pending subscription data from DB using session ID
                $pending = $db->fetchOne("SELECT * FROM pending_subscriptions WHERE stripe_session_id = ?", [$sessionId]);

                // Fall back to metadata if no pending record found
                if (!$pending && $userId) {
                    $pending = [
                        'user_id'          => $userId,
                        'plan_id'          => null,
                        'bread_selections' => '',
                        'delivery_address' => '',
                        'notes'            => '',
                        'frequency'        => 'weekly',
                        'delivery_day_id'  => null,
                        'gift_loaf'        => 0,
                        'gift_recipient_name'    => '',
                        'gift_recipient_address' => '',
                        'gift_note'        => '',
                    ];
                    // Try to find plan by name from metadata
                    $planName = $metadata['plan_name'] ?? '';
                    $planRow  = $db->fetchOne("SELECT id FROM subscription_plans WHERE name = ?", [$planName]);
                    if ($planRow) $pending['plan_id'] = $planRow['id'];
                }

                if ($pending && $pending['plan_id']) {
                    $resolvedUserId = $pending['user_id'] ?? $userId;

                    // Create subscription record if not already exists
                    $existing = $db->fetchOne("SELECT id FROM subscriptions WHERE stripe_subscription_id = ?", [$stripeSubId]);
                    if (!$existing) {
                        $db->insert(
                            "INSERT INTO subscriptions (user_id, plan_id, status, stripe_subscription_id, stripe_customer_id, stripe_session_id, delivery_day_id, bread_selections, delivery_address, notes, created_at, updated_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",
                            [
                                $resolvedUserId,
                                $pending['plan_id'],
                                'active',
                                $stripeSubId,
                                $stripeCustomer,
                                $sessionId,
                                $pending['delivery_day_id'] ?? null,
                                $pending['bread_selections'] ?? '',
                                $pending['delivery_address'] ?? '',
                                $pending['notes'] ?? '',
                            ]
                        );
                    }

                    // Update user's stripe customer ID
                    $db->execute("UPDATE users SET stripe_customer_id = ? WHERE id = ?", [$stripeCustomer, $resolvedUserId]);

                    // Clean up pending record
                    $db->execute("DELETE FROM pending_subscriptions WHERE stripe_session_id = ?", [$sessionId]);
                }
            }
        }
        break;

    case 'customer.subscription.updated':
        $stripeSubId = $data['id'] ?? '';
        $status      = $data['status'] ?? '';
        $statusMap   = [
            'active'   => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'paused'   => 'paused',
        ];
        $newStatus = $statusMap[$status] ?? null;
        if ($newStatus && $stripeSubId) {
            $db->execute(
                "UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE stripe_subscription_id = ?",
                [$newStatus, $stripeSubId]
            );
        }
        break;

    case 'customer.subscription.deleted':
        $stripeSubId = $data['id'] ?? '';
        if ($stripeSubId) {
            $db->execute(
                "UPDATE subscriptions SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE stripe_subscription_id = ?",
                [$stripeSubId]
            );
        }
        break;

    case 'invoice.payment_failed':
        $stripeCustomer = $data['customer'] ?? '';
        $stripeSubId    = $data['subscription'] ?? '';
        if ($stripeSubId) {
            $db->execute(
                "UPDATE subscriptions SET status = 'past_due', updated_at = CURRENT_TIMESTAMP WHERE stripe_subscription_id = ?",
                [$stripeSubId]
            );
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
