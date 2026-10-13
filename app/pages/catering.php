<?php
$db = Database::getInstance();
$cateringCat = $db->fetchOne("SELECT id FROM categories WHERE slug = 'catering'");
$items = $cateringCat ? $db->fetchAll("SELECT * FROM products WHERE category_id = ? AND available = 1 ORDER BY name", [$cateringCat['id']]) : [];

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto text-center">
        <h1 class="text-3xl font-bold text-brown mb-2">Catering Services</h1>
        <div class="section-divider my-4"></div>
        <p class="text-warm max-w-xl mx-auto">From corporate lunches to wedding receptions, we bring artisan quality to every event. Minimum 12 servings.</p>
    </div>
</section>

<!-- Catering Items -->
<section class="py-14 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <?php foreach ($items as $item): ?>
            <div class="card overflow-hidden">
                <div class="product-img-placeholder text-5xl">🍽️</div>
                <div class="p-5">
                    <h3 class="font-bold text-brown text-lg mb-2"><?= e($item['name']) ?></h3>
                    <p class="text-warm text-sm mb-4"><?= e($item['description']) ?></p>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xl font-bold text-brown"><?= formatMoney($item['price']) ?></span>
                            <span class="text-warm text-xs ml-1">per serving</span>
                        </div>
                        <form method="POST" action="<?= pageUrl('action.cart.add') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="quantity" value="12">
                            <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                            <button type="submit" class="btn-primary text-sm">
                                <i class="fa-solid fa-plus mr-1"></i>Add (min 12)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Catering Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="card p-6 text-center">
                <i class="fa-solid fa-users text-brown text-3xl mb-3"></i>
                <h3 class="font-bold text-brown mb-2">Group Sizes</h3>
                <p class="text-warm text-sm">We cater for groups of 12 to 500+. All items are available in bulk quantities with volume pricing.</p>
            </div>
            <div class="card p-6 text-center">
                <i class="fa-solid fa-truck text-brown text-3xl mb-3"></i>
                <h3 class="font-bold text-brown mb-2">Delivery & Setup</h3>
                <p class="text-warm text-sm">We deliver and set up at your venue. Disposable or reusable serving containers available.</p>
            </div>
            <div class="card p-6 text-center">
                <i class="fa-solid fa-calendar-check text-brown text-3xl mb-3"></i>
                <h3 class="font-bold text-brown mb-2">Advance Booking</h3>
                <p class="text-warm text-sm">Book at least 48 hours in advance. For large events (100+), please book 1 week ahead.</p>
            </div>
        </div>

        <!-- Custom Quote CTA -->
        <div class="bg-brown rounded-2xl p-10 text-center text-white">
            <i class="fa-solid fa-envelope-open-text text-gold text-4xl mb-4"></i>
            <h2 class="text-2xl font-bold mb-3">Need a Custom Quote?</h2>
            <p class="text-white/80 max-w-lg mx-auto mb-6">For large events, custom menus, or special dietary requirements, contact us directly and we'll create a tailored catering package for you.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="mailto:<?= e(setting('store_email')) ?>" class="btn-gold px-6 py-3">
                    <i class="fa-solid fa-envelope mr-2"></i>Email Us
                </a>
                <a href="tel:<?= e(setting('store_phone')) ?>" class="btn-outline border-white text-white hover:bg-white hover:text-brown px-6 py-3">
                    <i class="fa-solid fa-phone mr-2"></i><?= e(setting('store_phone')) ?>
                </a>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Catering Services', $content);
