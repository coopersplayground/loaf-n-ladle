<?php
$db = Database::getInstance();
$featuredProducts = $db->fetchAll("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.featured = 1 AND p.available = 1 ORDER BY p.sort_order LIMIT 6");
$plans = $db->fetchAll("SELECT * FROM subscription_plans WHERE active = 1 ORDER BY sort_order LIMIT 4");

ob_start();
?>

<!-- Hero Section -->
<section class="gradient-hero py-24 px-4">
    <div class="max-w-7xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 bg-white/60 backdrop-blur-sm text-brown px-4 py-1.5 rounded-full text-sm font-semibold mb-6 border border-gold/40">
            <i class="fa-solid fa-star text-gold text-xs"></i>
            Artisan Bakery & Café
        </div>
        <h1 class="text-5xl md:text-6xl font-bold text-brown mb-6 leading-tight">
            Baked Fresh,<br>
            <span class="text-gold-dark" style="color:var(--gold-dark)">Every Single Day</span>
        </h1>
        <p class="text-lg text-warm max-w-2xl mx-auto mb-10 leading-relaxed">
            Handcrafted breads, pastries, and sandwiches made with locally sourced ingredients. 
            Order online, subscribe for weekly delivery, or book catering for your next event.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?= pageUrl('shop') ?>" class="btn-primary text-lg px-8 py-3">
                <i class="fa-solid fa-basket-shopping mr-2"></i>Shop Now
            </a>
            <a href="<?= pageUrl('subscriptions') ?>" class="btn-outline text-lg px-8 py-3 bg-white/50">
                <i class="fa-solid fa-rotate mr-2"></i>Weekly Subscriptions
            </a>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-6 max-w-lg mx-auto mt-14">
            <div class="text-center">
                <div class="text-3xl font-bold text-brown">50+</div>
                <div class="text-sm text-warm mt-1">Menu Items</div>
            </div>
            <div class="text-center border-x border-gold/30">
                <div class="text-3xl font-bold text-brown">100%</div>
                <div class="text-sm text-warm mt-1">Local Ingredients</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-brown">Daily</div>
                <div class="text-sm text-warm mt-1">Fresh Baked</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Bar -->
<section class="bg-brown text-white py-6">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center text-sm">
            <div class="flex items-center justify-center gap-2">
                <i class="fa-solid fa-truck text-gold"></i>
                <span>Free delivery within 20 miles</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <i class="fa-solid fa-leaf text-gold"></i>
                <span>Local & organic ingredients</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <i class="fa-solid fa-rotate text-gold"></i>
                <span>Weekly bread subscriptions</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <i class="fa-solid fa-utensils text-gold"></i>
                <span>Full catering service</span>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-brown mb-3">Customer Favorites</h2>
            <div class="section-divider mb-4"></div>
            <p class="text-warm">Our most-loved items, baked fresh every morning</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="card overflow-hidden">
                <div class="product-img-placeholder">
                    <?php
                    $icons = ['breads'=>'🥖','pastries'=>'🥐','sandwiches'=>'🥪','salads'=>'🥗','soups-bowls'=>'🍲','beverages'=>'☕','desserts-sides'=>'🍰','catering'=>'🍽️'];
                    $catSlug = $db->fetchOne("SELECT slug FROM categories WHERE id = ?", [$product['category_id']])['slug'] ?? '';
                    echo $icons[$catSlug] ?? '🍞';
                    ?>
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="font-bold text-brown text-lg leading-tight"><?= e($product['name']) ?></h3>
                        <span class="text-gold font-bold text-lg ml-2 whitespace-nowrap"><?= formatMoney($product['price']) ?></span>
                    </div>
                    <p class="text-warm text-sm mb-4 line-clamp-2"><?= e($product['description']) ?></p>
                    <div class="flex gap-2">
                        <a href="<?= pageUrl('product', ['id' => $product['id']]) ?>" class="btn-outline text-sm flex-1 text-center">Details</a>
                        <form method="POST" action="<?= pageUrl('action.cart.add') ?>" class="flex-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                            <button type="submit" class="btn-primary text-sm w-full">
                                <i class="fa-solid fa-plus mr-1"></i>Add
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-8">
            <a href="<?= pageUrl('shop') ?>" class="btn-gold px-8 py-3 text-lg">
                View Full Menu <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Subscription Plans Preview -->
<section class="py-16 px-4 bg-cream-dark">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-brown mb-3">Weekly Bread Subscriptions</h2>
            <div class="section-divider mb-4"></div>
            <p class="text-warm">Fresh artisanal bread delivered to your door every week</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($plans as $i => $plan):
                $features = json_decode($plan['features'] ?? '[]', true);
                $popular = $i === 1;
            ?>
            <div class="card p-6 relative <?= $popular ? 'ring-2 ring-brown' : '' ?>">
                <?php if ($popular): ?>
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brown text-white text-xs font-bold px-3 py-1 rounded-full">Most Popular</div>
                <?php endif; ?>
                <h3 class="font-bold text-brown text-xl mb-1"><?= $plan['name'] ?></h3>
                <div class="text-3xl font-bold text-brown my-3">
                    <?= formatMoney($plan['price_per_week']) ?>
                </div>
                <p class="text-warm text-sm mb-4"><?= e($plan['loaves_per_week']) ?></p>
                <ul class="space-y-2 mb-6">
                    <?php foreach (array_slice($features, 0, 3) as $f): ?>
                    <li class="flex items-center gap-2 text-sm text-warm">
                        <i class="fa-solid fa-check text-green-500 text-xs"></i><?= e($f) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= pageUrl('subscriptions') ?>" class="<?= $popular ? 'btn-primary' : 'btn-outline' ?> w-full text-center text-sm">
                    Choose Plan
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Catering CTA -->
<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="bg-brown rounded-2xl p-10 md:p-14 text-center text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-5" style="background-image: repeating-linear-gradient(45deg, white 0, white 1px, transparent 0, transparent 50%); background-size: 20px 20px;"></div>
            <div class="relative">
                <i class="fa-solid fa-utensils text-gold text-4xl mb-4"></i>
                <h2 class="text-3xl font-bold mb-3">Planning an Event?</h2>
                <p class="text-white/80 max-w-xl mx-auto mb-8">
                    From intimate gatherings to corporate events, our catering team delivers fresh, artisanal food for any occasion. Minimum 12 servings.
                </p>
                <a href="<?= pageUrl('catering') ?>" class="btn-gold px-8 py-3 text-lg">
                    Explore Catering <i class="fa-solid fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-16 px-4 bg-cream-dark">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-brown mb-3">How It Works</h2>
            <div class="section-divider mb-4"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-brown rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-magnifying-glass text-white text-xl"></i>
                </div>
                <h3 class="font-bold text-brown text-lg mb-2">1. Browse & Choose</h3>
                <p class="text-warm text-sm">Explore our full menu of artisanal breads, sandwiches, salads, and more.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-gold rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-basket-shopping text-brown text-xl"></i>
                </div>
                <h3 class="font-bold text-brown text-lg mb-2">2. Order & Pay</h3>
                <p class="text-warm text-sm">Securely checkout with Stripe. One-time orders or weekly subscriptions.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-brown rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-truck text-white text-xl"></i>
                </div>
                <h3 class="font-bold text-brown text-lg mb-2">3. Enjoy Fresh</h3>
                <p class="text-warm text-sm">Pick up in-store or get free delivery within 20 miles of our bakery.</p>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
renderLayout("Fresh Artisan Bakery & Café", $content);
