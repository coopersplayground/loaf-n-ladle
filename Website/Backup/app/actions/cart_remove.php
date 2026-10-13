<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/helpers.php';

Auth::startSession();

if (!isPost()) { redirect(pageUrl('cart')); }
if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('cart')); }

if (post('clear') == '1') {
    cartClear();
} else {
    $productId = (int)post('product_id');
    cartRemove($productId);
}

redirect(pageUrl('cart'));
