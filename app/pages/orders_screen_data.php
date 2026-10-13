<?php
header('Content-Type: application/json');
$db = Database::getInstance();

$token = get('token', '');
$validToken = setting('orders_screen_token', '');
if (!$token || !$validToken || !hash_equals($validToken, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

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

foreach ($orders as &$o) {
    $o['items'] = $db->fetchAll(
        "SELECT product_name, quantity FROM order_items WHERE order_id = ? ORDER BY id",
        [$o['id']]
    );
}
unset($o);

echo json_encode(['orders' => $orders, 'ts' => time()]);
