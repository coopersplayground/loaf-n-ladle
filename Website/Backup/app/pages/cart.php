<?php
$items = cartItems();
$subtotal = cartTotal();
$taxRate = (float)setting('tax_rate', 0.08);
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

ob_start();
?>
<section class="py-10 px-4">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-brown mb-8">Your Cart</h1>

        <?php if (empty($items)): ?>
        <div class="card p-16 text-center">
            <div class="text-6xl mb-4">🧺</div>
            <h2 class="text-xl font-bold text-brown mb-2">Your cart is empty</h2>
            <p class="text-warm mb-6">Add some delicious items from our menu!</p>
            <a href="<?= pageUrl('shop') ?>" class="btn-primary px-8 py-3">Browse Menu</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-3">
                <?php foreach ($items as $item): ?>
                <div class="card p-4 flex items-center gap-4">
                    <div class="w-16 h-16 bg-cream-dark rounded-lg flex items-center justify-center text-2xl flex-shrink-0">
                        🍞
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-brown truncate"><?= e($item['name']) ?></h3>
                        <p class="text-warm text-sm"><?= formatMoney($item['price']) ?> each</p>
                    </div>
                    <!-- Quantity -->
                    <form method="POST" action="<?= pageUrl('action.cart.update') ?>" class="flex items-center gap-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <button type="button" onclick="this.form.querySelector('[name=quantity]').value=Math.max(1,parseInt(this.form.querySelector('[name=quantity]').value)-1);this.form.submit()" class="w-7 h-7 rounded border border-gold text-brown hover:bg-cream-dark font-bold text-sm">−</button>
                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="99" class="w-12 text-center form-input py-1 text-sm" onchange="this.form.submit()">
                        <button type="button" onclick="this.form.querySelector('[name=quantity]').value=Math.min(99,parseInt(this.form.querySelector('[name=quantity]').value)+1);this.form.submit()" class="w-7 h-7 rounded border border-gold text-brown hover:bg-cream-dark font-bold text-sm">+</button>
                    </form>
                    <!-- Line Total -->
                    <div class="text-brown font-bold w-20 text-right"><?= formatMoney($item['line_total']) ?></div>
                    <!-- Remove -->
                    <form method="POST" action="<?= pageUrl('action.cart.remove') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 ml-2 transition-colors">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>

                <div class="flex justify-between items-center pt-2">
                    <a href="<?= pageUrl('shop') ?>" class="text-brown hover:underline text-sm">
                        <i class="fa-solid fa-arrow-left mr-1"></i>Continue Shopping
                    </a>
                    <form method="POST" action="<?= pageUrl('action.cart.remove') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="clear" value="1">
                        <button type="submit" class="text-red-400 hover:text-red-600 text-sm transition-colors">
                            <i class="fa-solid fa-trash mr-1"></i>Clear Cart
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="card p-6 sticky top-20">
                    <h2 class="font-bold text-brown text-xl mb-5">Order Summary</h2>
                    <div class="space-y-3 text-sm mb-5">
                        <div class="flex justify-between text-warm">
                            <span>Subtotal (<?= cartCount() ?> items)</span>
                            <span><?= formatMoney($subtotal) ?></span>
                        </div>
                        <div class="flex justify-between text-warm">
                            <span>Tax (<?= round($taxRate * 100) ?>%)</span>
                            <span><?= formatMoney($tax) ?></span>
                        </div>
                        <div class="flex justify-between text-warm">
                            <span>Delivery</span>
                            <span class="text-green-600 font-semibold">Free</span>
                        </div>
                        <div class="border-t border-gold/30 pt-3 flex justify-between font-bold text-brown text-lg">
                            <span>Total</span>
                            <span><?= formatMoney($total) ?></span>
                        </div>
                    </div>
                    <a href="<?= pageUrl('checkout') ?>" class="btn-primary w-full text-center py-3 text-lg block">
                        <i class="fa-solid fa-lock mr-2"></i>Proceed to Checkout
                    </a>
                    <div class="mt-4 text-center text-xs text-warm">
                        <i class="fa-brands fa-stripe text-blue-500 mr-1"></i>Secured by Stripe
                    </div>
                    <div class="mt-3 flex justify-center gap-2 opacity-60">
                        <i class="fa-brands fa-cc-visa text-xl text-gray-500"></i>
                        <i class="fa-brands fa-cc-mastercard text-xl text-gray-500"></i>
                        <i class="fa-brands fa-cc-amex text-xl text-gray-500"></i>
                        <i class="fa-brands fa-cc-apple-pay text-xl text-gray-500"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Your Cart', $content);
