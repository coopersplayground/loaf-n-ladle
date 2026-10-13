<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/helpers.php';

Auth::startSession();

if (!isPost()) { redirect(pageUrl('cart')); }
if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('cart')); }

$productId = (int)post('product_id');
$qty = (int)post('quantity', 0);

cartUpdate($productId, $qty);
redirect(pageUrl('cart'));
