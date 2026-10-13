<?php
$db = Database::getInstance();
$id = (int)get('id');
$product = $db->fetchOne(
    "SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.available = 1",
    [$id]
);
if (!$product) { redirect(pageUrl('shop')); }

$related = $db->fetchAll(
    "SELECT * FROM products WHERE category_id = ? AND id != ? AND available = 1 LIMIT 4",
    [$product['category_id'], $id]
);

$catIcons = ['breads'=>'🥖','pastries'=>'�','sandwiches'=>'🥪','salads'=>'🥗','soups-bowls'=>'🍲','beverages'=>'☕','desserts-sides'=>'🍰','catering'=>'🍽️'];

ob_start();
?>
<section class="py-10 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="mb-4 text-sm text-warm">
            <a href="<?= pageUrl('shop') ?>" class="hover:text-brown">Shop</a>
            <span class="mx-2">/</span>
            <a href="<?= pageUrl('shop', ['cat' => $product['category_slug']]) ?>" class="hover:text-brown"><?= e($product['category_name']) ?></a>
            <span class="mx-2">/</span>
            <span class="text-brown"><?= e($product['name']) ?></span>
        </div>
        <div class="card overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-2">
                <div class="product-img-placeholder text-8xl" style="height:320px;border-radius:0.75rem 0 0 0.75rem;">
                    <?= $catIcons[$product['category_slug']] ?? '🍞' ?>
                </div>
                <div class="p-8 flex flex-col justify-center">
                    <div class="text-xs text-gold font-semibold uppercase tracking-wider mb-2"><?= e($product['category_name']) ?></div>
                    <h1 class="text-3xl font-bold text-brown mb-3"><?= e($product['name']) ?></h1>
                    <p class="text-warm leading-relaxed mb-6"><?= e($product['description']) ?></p>
                    <div class="text-4xl font-bold text-brown mb-6"><?= formatMoney($product['price']) ?></div>
                    <form method="POST" action="<?= pageUrl('action.cart.add') ?>" class="flex gap-3 items-center">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= e(pageUrl('cart')) ?>">
                        <div class="flex items-center border border-gold rounded-lg overflow-hidden">
                            <button type="button" onclick="adjustQty(-1)" class="px-3 py-2 text-brown hover:bg-cream-dark font-bold">−</button>
                            <input type="number" name="quantity" id="qty" value="1" min="1" max="99" class="w-14 text-center border-0 form-input py-2 rounded-none">
                            <button type="button" onclick="adjustQty(1)" class="px-3 py-2 text-brown hover:bg-cream-dark font-bold">+</button>
                        </div>
                        <button type="submit" class="btn-primary flex-1 py-3">
                            <i class="fa-solid fa-basket-shopping mr-2"></i>Add to Cart
                        </button>
                    </form>
                    <div class="mt-4 flex gap-4 text-sm text-warm">
                        <span><i class="fa-solid fa-leaf text-green-500 mr-1"></i>Fresh daily</span>
                        <span><i class="fa-solid fa-truck text-gold mr-1"></i>Free delivery 20mi</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($related)): ?>
        <div class="mt-12">
            <h2 class="text-xl font-bold text-brown mb-5">More from <?= e($product['category_name']) ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($related as $r): ?>
                <a href="<?= pageUrl('product', ['id' => $r['id']]) ?>" class="card p-4 text-center hover:no-underline">
                    <div class="text-3xl mb-2"><?= $catIcons[$product['category_slug']] ?? '🍞' ?></div>
                    <div class="font-semibold text-brown text-sm"><?= e($r['name']) ?></div>
                    <div class="text-gold font-bold mt-1"><?= formatMoney($r['price']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<script>
function adjustQty(delta) {
    const input = document.getElementById('qty');
    const val = parseInt(input.value) + delta;
    if (val >= 1 && val <= 99) input.value = val;
}
</script>
<?php
$content = ob_get_clean();
renderLayout(e($product['name']), $content);
