<?php
Auth::requireAdmin();
$db = Database::getInstance();
$id = (int)get('id');
$product = $id ? $db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]) : null;
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY sort_order");

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.product.edit', $id ? ['id'=>$id] : [])); }

    $name = sanitize(post('name'));
    $catId = (int)post('category_id');
    $description = sanitize(post('description'));
    $price = (float)post('price');
    $cost = (float)post('cost');
    $stripePriceId = sanitize(post('stripe_price_id'));
    $available = post('available') ? 1 : 0;
    $featured = post('featured') ? 1 : 0;
    $sortOrder = (int)post('sort_order');

    if (strlen($name) < 2) { flash('error', 'Product name is required.'); redirect(pageUrl('admin.product.edit', $id ? ['id'=>$id] : [])); }
    if ($price <= 0) { flash('error', 'Price must be greater than 0.'); redirect(pageUrl('admin.product.edit', $id ? ['id'=>$id] : [])); }

    $slug = slugify($name);
    // Ensure unique slug
    $existing = $db->fetchOne("SELECT id FROM products WHERE slug = ? AND id != ?", [$slug, $id ?: 0]);
    if ($existing) $slug .= '-' . time();

    if ($id && $product) {
        $db->execute(
            "UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, cost=?, stripe_price_id=?, available=?, featured=?, sort_order=? WHERE id=?",
            [$catId, $name, $slug, $description, $price, $cost, $stripePriceId, $available, $featured, $sortOrder, $id]
        );
        flash('success', 'Product updated successfully.');
    } else {
        $newId = $db->insert(
            "INSERT INTO products (category_id, name, slug, description, price, cost, stripe_price_id, available, featured, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$catId, $name, $slug, $description, $price, $cost, $stripePriceId, $available, $featured, $sortOrder]
        );
        flash('success', 'Product created successfully.');
        redirect(pageUrl('admin.product.edit', ['id' => $newId]));
    }
    redirect(pageUrl('admin.products'));
}

ob_start();
?>
<div class="flex items-center gap-3 mb-6">
    <a href="<?= pageUrl('admin.products') ?>" class="text-warm hover:text-brown">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $product ? 'Edit Product' : 'Add Product' ?></h1>
        <?php if ($product): ?><p class="text-gray-500 text-sm mt-0.5"><?= e($product['name']) ?></p><?php endif; ?>
    </div>
</div>

<div class="max-w-3xl">
    <form method="POST" action="<?= pageUrl('admin.product.edit', $id ? ['id'=>$id] : []) ?>">
        <?= csrfField() ?>

        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-input" required value="<?= e($product['name'] ?? '') ?>" placeholder="e.g. Sourdough Boule">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input" placeholder="Brief description of the product..."><?= e($product['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-input" required>
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-input" value="<?= $product['sort_order'] ?? 0 ?>" min="0">
                </div>
            </div>
        </div>

        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Pricing</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Selling Price ($) *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                        <input type="number" name="price" class="form-input pl-7" required step="0.01" min="0.01"
                            value="<?= number_format($product['price'] ?? 0, 2) ?>" placeholder="0.00">
                    </div>
                </div>
                <div>
                    <label class="form-label">Cost Price ($)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                        <input type="number" name="cost" class="form-input pl-7" step="0.01" min="0"
                            value="<?= number_format($product['cost'] ?? 0, 2) ?>" placeholder="0.00">
                    </div>
                    <p class="text-warm text-xs mt-1">Used to calculate profit margin</p>
                </div>
            </div>
            <?php if ($product && $product['cost'] > 0): ?>
            <?php $margin = round((($product['price'] - $product['cost']) / $product['price']) * 100); ?>
            <div class="mt-3 p-3 bg-cream-dark rounded-lg text-sm">
                <span class="text-warm">Current margin: </span>
                <span class="font-bold text-brown"><?= $margin ?>%</span>
                <span class="text-warm ml-2">(<?= formatMoney($product['price'] - $product['cost']) ?> profit per unit)</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Stripe Integration</h2>
            <div>
                <label class="form-label">Stripe Price ID</label>
                <input type="text" name="stripe_price_id" class="form-input" value="<?= e($product['stripe_price_id'] ?? '') ?>" placeholder="price_xxxxxxxxxxxxx">
                <p class="text-warm text-xs mt-1">Optional. If set, this Stripe Price ID will be used for checkout instead of creating one dynamically.</p>
            </div>
        </div>

        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Visibility</h2>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="available" value="1" class="w-4 h-4 accent-brown" <?= ($product['available'] ?? 1) ? 'checked' : '' ?>>
                    <div>
                        <div class="font-medium text-gray-700">Available for purchase</div>
                        <div class="text-warm text-xs">Show this product in the shop</div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="featured" value="1" class="w-4 h-4 accent-brown" <?= ($product['featured'] ?? 0) ? 'checked' : '' ?>>
                    <div>
                        <div class="font-medium text-gray-700">Featured product</div>
                        <div class="text-warm text-xs">Show on homepage in "Featured Products"</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-8 py-2.5">
                <i class="fa-solid fa-floppy-disk mr-2"></i><?= $product ? 'Update Product' : 'Create Product' ?>
            </button>
            <a href="<?= pageUrl('admin.products') ?>" class="btn-outline px-6 py-2.5">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
renderLayout(($product ? 'Edit' : 'Add') . ' Product — Admin', $content, ['admin' => true]);
