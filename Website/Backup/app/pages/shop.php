<?php
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT * FROM categories WHERE active = 1 ORDER BY sort_order");
$selectedCat = get('cat', '');
$search = get('q', '');

$where = "p.available = 1";
$params = [];
if ($selectedCat) {
    $where .= " AND c.slug = ?";
    $params[] = $selectedCat;
}
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$products = $db->fetchAll(
    "SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
     FROM products p LEFT JOIN categories c ON p.category_id = c.id
     WHERE $where ORDER BY c.sort_order, p.sort_order, p.name",
    $params
);

$catIcons = ['breads'=>'🥖','pastries'=>'�','sandwiches'=>'🥪','salads'=>'🥗','soups-bowls'=>'🍲','beverages'=>'☕','desserts-sides'=>'🍰','catering'=>'🍽️'];

ob_start();
?>
<!-- Shop Header -->
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-brown mb-2">Our Menu</h1>
        <p class="text-warm">Fresh-baked daily — order online for pickup or delivery</p>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto flex flex-col lg:flex-row gap-8">

        <!-- Sidebar Filters -->
        <aside class="lg:w-56 flex-shrink-0">
            <div class="card p-4 sticky top-20">
                <h3 class="font-bold text-brown mb-3 text-sm uppercase tracking-wider">Categories</h3>
                <a href="<?= pageUrl('shop') ?>" class="sidebar-link text-sm <?= !$selectedCat ? 'active' : '' ?>">
                    <i class="fa-solid fa-th-large w-4"></i> All Items
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?= pageUrl('shop', ['cat' => $cat['slug']]) ?>" class="sidebar-link text-sm <?= $selectedCat === $cat['slug'] ? 'active' : '' ?>">
                    <span class="w-4 text-center"><?= $catIcons[$cat['slug']] ?? '🍞' ?></span>
                    <?= e($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Search + Count -->
            <div class="flex flex-col sm:flex-row gap-3 mb-6">
                <form method="GET" action="<?= pageUrl('shop') ?>" class="flex-1 flex gap-2">
                    <?php if ($selectedCat): ?>
                        <input type="hidden" name="page" value="shop">
                        <input type="hidden" name="cat" value="<?= e($selectedCat) ?>">
                    <?php else: ?>
                        <input type="hidden" name="page" value="shop">
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search menu..." class="form-input flex-1">
                    <button type="submit" class="btn-primary px-4"><i class="fa-solid fa-search"></i></button>
                    <?php if ($search): ?>
                        <a href="<?= pageUrl('shop', $selectedCat ? ['cat' => $selectedCat] : []) ?>" class="btn-outline px-4">Clear</a>
                    <?php endif; ?>
                </form>
                <div class="text-warm text-sm self-center whitespace-nowrap">
                    <?= count($products) ?> item<?= count($products) !== 1 ? 's' : '' ?>
                </div>
            </div>

            <?php if (empty($products)): ?>
            <div class="text-center py-16 text-warm">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-4 opacity-30"></i>
                <p class="text-lg">No items found.</p>
                <a href="<?= pageUrl('shop') ?>" class="btn-outline mt-4">View All</a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($products as $product): ?>
                <div class="card overflow-hidden">
                    <div class="product-img-placeholder text-4xl">
                        <?= $catIcons[$product['category_slug']] ?? '🍞' ?>
                    </div>
                    <div class="p-4">
                        <div class="text-xs text-gold font-semibold uppercase tracking-wider mb-1"><?= e($product['category_name']) ?></div>
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="font-bold text-brown text-base leading-tight"><?= e($product['name']) ?></h3>
                            <span class="text-brown font-bold ml-2 whitespace-nowrap"><?= formatMoney($product['price']) ?></span>
                        </div>
                        <p class="text-warm text-sm mb-4 line-clamp-2"><?= e($product['description']) ?></p>
                        <div class="flex gap-2">
                            <a href="<?= pageUrl('product', ['id' => $product['id']]) ?>" class="btn-outline text-sm px-3 py-1.5">Details</a>
                            <form method="POST" action="<?= pageUrl('action.cart.add') ?>" class="flex-1">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit" class="btn-primary text-sm w-full py-1.5">
                                    <i class="fa-solid fa-plus mr-1"></i>Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('Shop — Our Menu', $content);
