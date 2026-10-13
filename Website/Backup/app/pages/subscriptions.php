<?php
$db = Database::getInstance();
$plans = $db->fetchAll("SELECT * FROM subscription_plans WHERE active = 1 ORDER BY sort_order");
$breads = $db->fetchAll("SELECT * FROM products WHERE category_id IN (SELECT id FROM categories WHERE slug IN ('breads', 'pastries')) AND available = 1 ORDER BY name");

// Get separate breads and pastries with their categories
$breadsProducts = $db->fetchAll("SELECT p.*, c.slug as category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE c.slug = 'breads' AND p.available = 1 ORDER BY p.name");
$pastriesProducts = $db->fetchAll("SELECT p.*, c.slug as category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE c.slug = 'pastries' AND p.available = 1 ORDER BY p.name");

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto text-center">
        <h1 class="text-3xl font-bold text-brown mb-2">Loaf Club</h1>
        <div class="section-divider my-4"></div>
                       <p class="text-warm max-w-xl mx-auto">Limited menu available now, items will be added and removed periodically, decided through your feedback and popular demand. </p>
          <br>
                       <p class="text-warm max-w-xl mx-auto">Fresh artisanal bread delivered to your door every week. Choose your plan, select your breads, and we'll handle the rest.</p>
                                 <br>
                       <p class="text-warm max-w-xl mx-auto">The more you buy the less it costs! You do not need to commit, you can cancel at any time. Subscriptions are not required to place an order.</p>
  
            </div>
    </div>
</section>

<!-- Plans -->
<section class="py-14 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($plans as $i => $plan):
                $features = json_decode($plan['features'] ?? '[]', true);
                $popular = $i === 1;
            ?>
            <div class="card p-6 relative flex flex-col <?= $popular ? 'ring-2 ring-brown shadow-xl' : '' ?>">
                <?php if ($popular): ?>
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brown text-white text-xs font-bold px-4 py-1 rounded-full whitespace-nowrap">Most Popular</div>
                <?php endif; ?>
                <div class="mb-4">
                    <h3 class="font-bold text-brown text-xl mb-1"><?= $plan['name'] ?></h3>
                    <p class="text-warm text-sm"><?= e($plan['description']) ?></p>
                </div>
                <div class="text-4xl font-bold text-brown my-4">
                    <?= formatMoney($plan['price_per_week']) ?>
                </div>
                <div class="text-sm text-gold font-semibold mb-4">
                    <i class="fa-solid fa-bread-slice mr-1"></i><?= e($plan['loaves_per_week']) ?>
                </div>
                <ul class="space-y-2.5 mb-6 flex-1">
                    <?php foreach ($features as $f): ?>
                    <li class="flex items-start gap-2 text-sm text-warm">
                        <i class="fa-solid fa-check text-green-500 mt-0.5 flex-shrink-0"></i>
                        <span><?= e($f) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= pageUrl('subscribe.checkout', ['plan_id' => $plan['id']]) ?>" class="<?= $popular ? 'btn-primary' : 'btn-outline' ?> w-full text-center py-2.5">
                    Subscribe — <?= formatMoney($plan['price_per_week']) ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Available Items -->
<section class="py-14 px-4">
    <div class="max-w-6xl mx-auto">
        <!-- Breads Section -->
        <?php if (!empty($breadsProducts)): ?>
        <div class="mb-12">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-brown mb-2">Available Breads</h2>
                <div class="section-divider mb-4"></div>
                <p class="text-warm text-sm">Choose from our rotating selection of artisanal breads</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                <?php foreach ($breadsProducts as $bread): ?>
                <div class="card p-4 text-center">
                    <div class="text-3xl mb-2">🥖</div>
                    <div class="font-semibold text-brown text-sm"><?= e($bread['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pastries Section -->
        <?php if (!empty($pastriesProducts)): ?>
        <div class="mb-12">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-brown mb-2">Available Pastries</h2>
                <div class="section-divider mb-4"></div>
                <p class="text-warm text-sm">Fresh baked pastries and sweet treats</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                <?php foreach ($pastriesProducts as $pastry): ?>
                <div class="card p-4 text-center">
                    <div class="text-3xl mb-2">🥐</div>
                    <div class="font-semibold text-brown text-sm"><?= e($pastry['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- If no items available -->
        <?php if (empty($breadsProducts) && empty($pastriesProducts)): ?>
        <div class="text-center">
            <div class="text-5xl mb-4 opacity-30">🥖</div>
            <h3 class="text-xl font-bold text-brown mb-2">No items available</h3>
            <p class="text-warm">Check back soon for fresh breads and pastries!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Add-ons -->
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-brown mb-2">Add-On Options</h2>
            <div class="section-divider mb-4"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="card p-5 text-center">
                <div class="text-4xl mb-3">🥐</div>
                <h3 class="font-bold text-brown mb-1">Monthly Pastry Box</h3>
                <p class="text-warm text-sm mb-3">4 assorted croissants or muffins delivered monthly</p>
                <div class="text-brown font-bold">+$10/month</div>
            </div>
            <div class="card p-5 text-center">
                <div class="text-4xl mb-3">🎃</div>
                <h3 class="font-bold text-brown mb-1">Seasonal Specials</h3>
                <p class="text-warm text-sm mb-3">Pumpkin spice, holiday flavors, and more</p>
                <div class="text-brown font-bold">Varies by season</div>
            </div>
            <div class="card p-5 text-center">
                <div class="text-4xl mb-3">✨</div>
                <h3 class="font-bold text-brown mb-1">Custom Bread Requests</h3>
                <p class="text-warm text-sm mb-3">Request a specific bread not on our standard list</p>
                <div class="text-brown font-bold">+$5/loaf</div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="py-12 px-4 bg-cream-dark">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-brown mb-2">Frequently Asked Questions</h2>
            <div class="section-divider mb-4"></div>
        </div>
        <div class="space-y-4">
            <?php $faqs = [
                ['When is bread delivered?', 'We deliver every Friday morning between 7am and 12pm. You\'ll receive a notification when your order is on its way.'],
                ['Can I pause my subscription?', 'Yes! You can pause, skip a week, or cancel anytime from your client portal with no fees.'],
                ['What if I\'m not home?', 'We leave your bread in a designated spot you specify. All deliveries are in insulated bags to keep bread fresh.'],
                ['How far do you deliver?', 'We deliver free within 20 miles of our bakery. Contact us for deliveries beyond that range.'],
                ['Can I change my bread selections?', 'Yes, you can update your bread choices anytime before Wednesday for that week\'s delivery.'],
            ]; ?>
            <?php foreach ($faqs as $faq): ?>
            <div class="card p-5">
                <h4 class="font-bold text-brown mb-2"><?= e($faq[0]) ?></h4>
                <p class="text-warm text-sm"><?= e($faq[1]) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Weekly Bread Subscriptions', $content);
