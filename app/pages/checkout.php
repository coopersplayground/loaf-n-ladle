<?php
$items = cartItems();
if (empty($items)) { redirect(pageUrl('cart')); }

$subtotal = cartTotal();
$taxRate = (float)setting('tax_rate', 0.08);
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;
$stripeConfigured = StripeService::isConfigured();
$publishableKey = setting('stripe_publishable_key');

$user = Auth::currentUser();

ob_start();
?>
<section class="py-10 px-4">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-brown mb-8">Checkout</h1>

        <?php if (!$stripeConfigured): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded mb-6">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
            <strong>Stripe not configured.</strong> An admin needs to add Stripe API keys before payments can be processed.
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= pageUrl('action.order.create') ?>" id="checkoutForm">
            <?= csrfField() ?>
            <!-- Hidden fields for gift loaf (populated by modal) -->
            <input type="hidden" name="add_gift_loaf" id="addGiftLoaf" value="0">
            <input type="hidden" name="gift_recipient_name" id="giftRecipientName" value="">
            <input type="hidden" name="gift_recipient_address" id="giftRecipientAddress" value="">
            <input type="hidden" name="gift_note" id="giftNote" value="">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Customer Info -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="card p-6">
                        <h2 class="font-bold text-brown text-xl mb-5">Contact Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="delivery_name" class="form-input" required
                                    value="<?= e($user['name'] ?? '') ?>" placeholder="Jane Smith">
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" name="delivery_email" class="form-input" required
                                    value="<?= e($user['email'] ?? '') ?>" placeholder="jane@example.com">
                            </div>
                            <div>
                                <label class="form-label">Phone</label>
                                <input type="tel" name="delivery_phone" class="form-input"
                                    value="<?= e($user['phone'] ?? '') ?>" placeholder="(555) 123-4567">
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-bold text-brown text-xl mb-5">Delivery Address</h2>
                        <div>
                            <label class="form-label">Street Address</label>
                            <input type="text" name="delivery_address" class="form-input" 
                                value="<?= e($user['address'] ?? '') ?>" placeholder="123 Main St, City, State 12345">
                        </div>
                        <p class="text-warm text-xs mt-2"><i class="fa-solid fa-truck text-gold mr-1"></i>Free delivery within 20 miles. Leave blank for in-store pickup.</p>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-bold text-brown text-xl mb-5">Order Notes</h2>
                        <textarea name="notes" rows="3" class="form-input" placeholder="Special instructions, allergies, or requests..."></textarea>
                    </div>

                    <!-- Order Items Summary -->
                    <div class="card p-6">
                        <h2 class="font-bold text-brown text-xl mb-4">Order Items</h2>
                        <div class="space-y-3">
                            <?php foreach ($items as $item): ?>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-warm"><?= e($item['name']) ?> × <?= $item['quantity'] ?></span>
                                <span class="font-semibold text-brown"><?= formatMoney($item['line_total']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="card p-6 sticky top-20">
                        <h2 class="font-bold text-brown text-xl mb-5">Order Total</h2>
                        <div class="space-y-3 text-sm mb-6">
                            <div class="flex justify-between text-warm">
                                <span>Subtotal</span>
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
                            <div class="border-t border-gold/30 pt-3 flex justify-between font-bold text-brown text-xl">
                                <span>Total</span>
                                <span><?= formatMoney($total) ?></span>
                            </div>
                        </div>

                        <?php if ($stripeConfigured): ?>
                        <button type="submit" class="btn-primary w-full py-3 text-lg">
                            <i class="fa-solid fa-lock mr-2"></i>Pay <?= formatMoney($total) ?>
                        </button>
                        <p class="text-center text-xs text-warm mt-3">You'll be redirected to Stripe's secure checkout</p>
                        <?php else: ?>
                        <button type="button" disabled class="btn-primary w-full py-3 text-lg opacity-50 cursor-not-allowed">
                            <i class="fa-solid fa-lock mr-2"></i>Payment Unavailable
                        </button>
                        <?php endif; ?>

                        <div class="mt-4 text-center text-xs text-warm">
                            <i class="fa-brands fa-stripe text-blue-500 mr-1"></i>Secured by Stripe
                        </div>
                        <div class="mt-3 flex justify-center gap-2 opacity-60">
                            <i class="fa-brands fa-cc-visa text-xl text-gray-500"></i>
                            <i class="fa-brands fa-cc-mastercard text-xl text-gray-500"></i>
                            <i class="fa-brands fa-cc-amex text-xl text-gray-500"></i>
                            <i class="fa-brands fa-cc-apple-pay text-xl text-gray-500"></i>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gold/20">
                            <a href="<?= pageUrl('cart') ?>" class="text-brown hover:underline text-sm flex items-center gap-1">
                                <i class="fa-solid fa-arrow-left text-xs"></i>Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- Loaves of Love Gift Modal -->
<div id="loavesOfLoveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none !important;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="modalBackdrop"></div>

    <!-- Modal Card -->
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden animate-fadeIn">
        <!-- Warm header banner -->
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 px-8 py-6 text-white text-center">
            <div class="text-4xl mb-2">🍞❤️</div>
            <h2 class="text-2xl font-bold leading-tight">Send a Loaf to a Friend!</h2>
            <p class="text-amber-100 text-sm mt-1 font-medium">& We'll Donate a Loaf!</p>
        </div>

        <!-- Body -->
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

            <!-- Gift recipient form -->
            <div id="giftForm" class="space-y-3">
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

        <!-- Action buttons -->
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
    var form  = document.getElementById('checkoutForm');
    var btnAdd  = document.getElementById('btnAddGift');
    var btnSkip = document.getElementById('btnSkipGift');
    var giftError = document.getElementById('giftFormError');
    var modalShown = false;

    function showModal() {
        modal.style.removeProperty('display');
        modal.style.display = 'flex';
    }
    function hideModal() {
        modal.style.display = 'none';
    }

    // Intercept form submit
    form.addEventListener('submit', function(e) {
        if (!modalShown) {
            e.preventDefault();
            showModal();
        }
    });

    // "Add gift" button
    btnAdd.addEventListener('click', function() {
        var name    = document.getElementById('giftNameInput').value.trim();
        var address = document.getElementById('giftAddressInput').value.trim();
        var note    = document.getElementById('giftNoteInput').value.trim();

        if (!name || !address) {
            giftError.classList.remove('hidden');
            return;
        }
        giftError.classList.add('hidden');

        document.getElementById('addGiftLoaf').value        = '1';
        document.getElementById('giftRecipientName').value  = name;
        document.getElementById('giftRecipientAddress').value = address;
        document.getElementById('giftNote').value           = note;

        modalShown = true;
        hideModal();
        form.submit();
    });

    // "Skip" button
    btnSkip.addEventListener('click', function() {
        document.getElementById('addGiftLoaf').value = '0';
        modalShown = true;
        hideModal();
        form.submit();
    });

    // Close on backdrop click
    document.getElementById('modalBackdrop').addEventListener('click', function() {
        document.getElementById('addGiftLoaf').value = '0';
        modalShown = true;
        hideModal();
        form.submit();
    });
})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Checkout', $content);
