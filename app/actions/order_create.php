<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/stripe.php';

Auth::startSession();

if (!isPost()) { redirect(pageUrl('cart')); }
if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('checkout')); }

$items = cartItems();
if (empty($items)) { redirect(pageUrl('cart')); }

if (!StripeService::isConfigured()) {
    flash('error', 'Payment system not configured. Please contact us to place your order.');
    redirect(pageUrl('checkout'));
}

$db = Database::getInstance();
$taxRate = (float)setting('tax_rate', 0.08);
$subtotal = cartTotal();
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

$deliveryName  = sanitize(post('delivery_name'));
$deliveryEmail = trim(post('delivery_email'));
$deliveryPhone = sanitize(post('delivery_phone'));
$deliveryAddr  = sanitize(post('delivery_address'));
$notes         = sanitize(post('notes'));

// Gift loaf add-on
$addGiftLoaf        = (int)post('add_gift_loaf') === 1;
$giftRecipientName  = sanitize(post('gift_recipient_name'));
$giftRecipientAddr  = sanitize(post('gift_recipient_address'));
$giftNote           = sanitize(post('gift_note'));
$giftLoafPrice      = 10.00;

if (strlen($deliveryName) < 2) { flash('error', 'Please enter your name.'); redirect(pageUrl('checkout')); }
if (!filter_var($deliveryEmail, FILTER_VALIDATE_EMAIL)) { flash('error', 'Please enter a valid email.'); redirect(pageUrl('checkout')); }

// Adjust total if gift loaf added
if ($addGiftLoaf) {
    $total += $giftLoafPrice;
}

$orderNumber = generateOrderNumber();
$userId = Auth::currentUserId();

// Create order in DB (status = pending until Stripe confirms)
$orderId = $db->insert(
    "INSERT INTO orders (user_id, order_number, status, subtotal, tax, total, notes, delivery_name, delivery_email, delivery_phone, delivery_address, gift_loaf, gift_recipient_name, gift_recipient_address, gift_note)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    [$userId, $orderNumber, 'ordered', $subtotal, $tax, $total, $notes, $deliveryName, $deliveryEmail, $deliveryPhone, $deliveryAddr,
     $addGiftLoaf ? 1 : 0, $giftRecipientName, $giftRecipientAddr, $giftNote]
);

// Assign daily order number (001-999, resets each day)
try { assignDailyOrderNumber($orderId); } catch (Exception $e) { /* non-fatal */ }

// Save order items
foreach ($items as $item) {
    $db->insert(
        "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?,?,?,?,?,?)",
        [$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['line_total']]
    );
}

// Build Stripe line items
$stripeItems = [];
foreach ($items as $item) {
    $stripeItems[] = [
        'name'     => $item['name'],
        'price'    => $item['price'],
        'quantity' => $item['quantity'],
    ];
}
// Add gift loaf as a line item
if ($addGiftLoaf) {
    $stripeItems[] = [
        'name'     => 'Loaves of Love — Gift Loaf (+ Donation)',
        'price'    => $giftLoafPrice,
        'quantity' => 1,
    ];
}
// Add tax as a line item
if ($tax > 0) {
    $stripeItems[] = [
        'name'     => 'Tax (' . round($taxRate * 100) . '%)',
        'price'    => $tax,
        'quantity' => 1,
    ];
}

$successUrl = APP_URL . '/index.php?page=checkout.success&order=' . urlencode($orderNumber) . '&session_id={CHECKOUT_SESSION_ID}';
$cancelUrl  = APP_URL . '/index.php?page=checkout.cancel';

try {
    $session = StripeService::createCheckoutSession(
        $orderNumber,
        $stripeItems,
        $deliveryEmail,
        $successUrl,
        $cancelUrl
    );

    // Store Stripe session ID on order
    $db->execute(
        "UPDATE orders SET stripe_session_id = ? WHERE id = ?",
        [$session['id'], $orderId]
    );

    // Clear cart
    cartClear();

    // Redirect to Stripe
    header('Location: ' . $session['url']);
    exit;

} catch (Exception $e) {
    // Roll back order
    $db->execute("DELETE FROM order_items WHERE order_id = ?", [$orderId]);
    $db->execute("DELETE FROM orders WHERE id = ?", [$orderId]);
    flash('error', 'Payment error: ' . $e->getMessage());
    redirect(pageUrl('checkout'));
}
