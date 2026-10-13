<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

class StripeService {

    private static function secretKey() {
        return setting('stripe_secret_key');
    }

    private static function apiRequest($method, $endpoint, $data = [], $idempotencyKey = null) {
        $key = self::secretKey();
        if (empty($key)) {
            throw new Exception('Stripe secret key not configured. Please set it in Admin > Settings.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $key . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
            'Content-Type: application/x-www-form-urlencoded',
            $idempotencyKey ? 'Idempotency-Key: ' . $idempotencyKey : null,
        ]));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $result['error']['message'] ?? 'Stripe API error';
            throw new Exception($msg);
        }
        return $result;
    }

    // Create a Stripe Checkout Session for one-time orders
    public static function createCheckoutSession($orderNumber, $items, $customerEmail, $successUrl, $cancelUrl) {
        $lineItems = [];
        foreach ($items as $i => $item) {
            $lineItems["line_items[$i][price_data][currency]"] = 'usd';
            $lineItems["line_items[$i][price_data][product_data][name]"] = $item['name'];
            $lineItems["line_items[$i][price_data][unit_amount]"] = (int)round($item['price'] * 100);
            $lineItems["line_items[$i][quantity]"] = $item['quantity'];
        }

        $data = array_merge([
            'payment_method_types[]' => 'card',
            'mode' => 'payment',
            'customer_email' => $customerEmail,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[order_number]' => $orderNumber,
        ], $lineItems);

        return self::apiRequest('POST', 'checkout/sessions', $data);
    }

    // Create a Stripe Checkout Session for subscriptions
    public static function createSubscriptionSession($planName, $pricePerWeek, $stripePriceId, $customerEmail, $successUrl, $cancelUrl, $userId, $addGiftLoaf = false) {
        if (!empty($stripePriceId)) {
            // Use existing Stripe Price ID
            $data = [
                'payment_method_types[]' => 'card',
                'mode' => 'subscription',
                'customer_email' => $customerEmail,
                'line_items[0][price]' => $stripePriceId,
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata[user_id]' => $userId,
                'metadata[plan_name]' => $planName,
            ];
        } else {
            // Create price inline
            $data = [
                'payment_method_types[]' => 'card',
                'mode' => 'subscription',
                'customer_email' => $customerEmail,
                'line_items[0][price_data][currency]' => 'usd',
                'line_items[0][price_data][product_data][name]' => $planName . ' - Weekly Bread Subscription',
                'line_items[0][price_data][unit_amount]' => (int)round($pricePerWeek * 100),
                'line_items[0][price_data][recurring][interval]' => 'week',
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata[user_id]' => $userId,
                'metadata[plan_name]' => $planName,
            ];
        }

        // Add one-time gift loaf line item if requested
        if ($addGiftLoaf) {
            $data['line_items[1][price_data][currency]'] = 'usd';
            $data['line_items[1][price_data][product_data][name]'] = 'Loaves of Love — Gift Loaf (+ Donation)';
            $data['line_items[1][price_data][unit_amount]'] = 1000;
            $data['line_items[1][quantity]'] = 1;
        }

        return self::apiRequest('POST', 'checkout/sessions', $data);
    }

    // Retrieve a checkout session
    public static function getSession($sessionId) {
        return self::apiRequest('GET', 'checkout/sessions/' . $sessionId);
    }

    // Create a Stripe Product
    public static function createProduct($name, $description = '') {
        return self::apiRequest('POST', 'products', [
            'name' => $name,
            'description' => $description,
        ]);
    }

    // Create a Stripe Price
    public static function createPrice($productId, $amount, $currency = 'usd', $recurring = null) {
        $data = [
            'product' => $productId,
            'unit_amount' => (int)round($amount * 100),
            'currency' => $currency,
        ];
        if ($recurring) {
            $data['recurring[interval]'] = $recurring;
        }
        return self::apiRequest('POST', 'prices', $data);
    }

    // Cancel a subscription
    public static function cancelSubscription($subscriptionId) {
        return self::apiRequest('DELETE', 'subscriptions/' . $subscriptionId);
    }

    // Verify webhook signature
    public static function verifyWebhook($payload, $sigHeader, $secret) {
        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            [$key, $val] = explode('=', $part, 2);
            if ($key === 't') $timestamp = $val;
            if ($key === 'v1') $signatures[] = $val;
        }
        if (!$timestamp || empty($signatures)) return false;
        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) return true;
        }
        return false;
    }

    public static function isConfigured() {
        return !empty(setting('stripe_secret_key')) && !empty(setting('stripe_publishable_key'));
    }
}
