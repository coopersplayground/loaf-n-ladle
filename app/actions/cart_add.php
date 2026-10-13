<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/helpers.php';

Auth::startSession();

if (!isPost()) { redirect(pageUrl('shop')); }
if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('shop')); }

$productId = (int)post('product_id');
$qty = max(1, (int)post('quantity', 1));

$product = Database::getInstance()->fetchOne("SELECT id, name, available FROM products WHERE id = ? AND available = 1", [$productId]);
if (!$product) {
    flash('error', 'Product not available.');
    redirect(post('redirect', pageUrl('shop')));
}

cartAdd($productId, $qty);
flash('success', e($product['name']) . ' added to cart!');
redirect(post('redirect', pageUrl('shop')));
