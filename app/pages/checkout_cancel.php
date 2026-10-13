<?php
ob_start();
?>
<section class="py-16 px-4">
    <div class="max-w-2xl mx-auto text-center">
        <div class="card p-12">
            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-xmark text-yellow-500 text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-brown mb-3">Payment Cancelled</h1>
            <p class="text-warm mb-8">Your order was not completed. Your cart items are still saved — you can try again whenever you're ready.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= pageUrl('checkout') ?>" class="btn-primary px-6 py-2.5">Try Again</a>
                <a href="<?= pageUrl('cart') ?>" class="btn-outline px-6 py-2.5">Back to Cart</a>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Payment Cancelled', $content);
