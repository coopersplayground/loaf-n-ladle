<?php
Auth::requireAdmin();
$db = Database::getInstance();

// Handle delete
if (isPost() && post('action') === 'delete') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.products')); }
    $id = (int)post('product_id');
    $db->execute("UPDATE products SET available = 0 WHERE id = ?", [$id]);
    flash('success', 'Product deactivated.');
    redirect(pageUrl('admin.products'));
}
if (isPost() && post('action') === 'toggle') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.products')); }
    $id = (int)post('product_id');
    $db->execute("UPDATE products SET available = NOT available WHERE id = ?", [$id]);
    redirect(pageUrl('admin.products'));
}
if (isPost() && post('action') === 'toggle_featured') {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.products')); }
    $id = (int)post('product_id');
    $db->execute("UPDATE products SET featured = NOT featured WHERE id = ?", [$id]);
    flash('success', 'Product featured status updated.');
    redirect(pageUrl('admin.products'));
}

$catFilter = get('cat', '');
$search = get('q', '');
$where = "1=1";
$params = [];
if ($catFilter) { $where .= " AND c.slug = ?"; $params[] = $catFilter; }
if ($search) { $where .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$products = $db->fetchAll(
    "SELECT p.*, c.name as category_name, c.slug as category_slug
     FROM products p LEFT JOIN categories c ON p.category_id = c.id
     WHERE $where ORDER BY c.sort_order, p.sort_order, p.name",
    $params
);
$categories = $db->fetchAll("SELECT * FROM categories WHERE active = 1 ORDER BY sort_order");

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Products</h1>
        <p class="text-gray-500 text-sm mt-1"><?= count($products) ?> products</p>
    </div>
    <a href="<?= pageUrl('admin.product.edit') ?>" class="btn-primary">
        <i class="fa-solid fa-plus mr-2"></i>Add Product
    </a>
</div>

<!-- Filters -->
<div class="card p-4 mb-5 flex flex-col sm:flex-row gap-3">
    <form method="GET" action="<?= pageUrl('admin.products') ?>" class="flex gap-2 flex-1">
        <input type="hidden" name="page" value="admin.products">
        <?php if ($catFilter): ?><input type="hidden" name="cat" value="<?= e($catFilter) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search products..." class="form-input flex-1">
        <button type="submit" class="btn-primary px-4"><i class="fa-solid fa-search"></i></button>
        <?php if ($search): ?><a href="<?= pageUrl('admin.products', $catFilter ? ['cat'=>$catFilter] : []) ?>" class="btn-outline px-4">Clear</a><?php endif; ?>
    </form>
    <div class="flex gap-2 flex-wrap">
        <a href="<?= pageUrl('admin.products') ?>" class="<?= !$catFilter ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="<?= pageUrl('admin.products', ['cat' => $cat['slug']]) ?>" class="<?= $catFilter === $cat['slug'] ? 'btn-primary' : 'btn-outline' ?> text-sm px-3 py-1.5"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Cost</th>
                <th>Margin</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p):
                $margin = $p['cost'] > 0 ? round((($p['price'] - $p['cost']) / $p['price']) * 100) : 0;
            ?>
            <tr>
                <td>
                    <div class="font-semibold text-brown"><?= e($p['name']) ?></div>
                    <div class="text-warm text-xs mt-0.5 max-w-xs truncate"><?= e($p['description']) ?></div>
                </td>
                <td class="text-sm text-warm"><?= e($p['category_name']) ?></td>
                <td class="font-bold text-brown"><?= formatMoney($p['price']) ?></td>
                <td class="text-warm text-sm"><?= formatMoney($p['cost']) ?></td>
                <td>
                    <span class="badge <?= $margin >= 40 ? 'badge-green' : ($margin >= 20 ? 'badge-yellow' : 'badge-red') ?>">
                        <?= $margin ?>%
                    </span>
                </td>
                <td>
                    <form method="POST" action="<?= pageUrl('admin.products') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="badge <?= $p['available'] ? 'badge-green' : 'badge-gray' ?> cursor-pointer hover:opacity-80">
                            <?= $p['available'] ? 'Active' : 'Hidden' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="<?= pageUrl('admin.products') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_featured">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="badge <?= $p['featured'] ? 'badge-yellow' : 'badge-gray' ?> cursor-pointer hover:opacity-80">
                            <i class="fa-solid fa-star mr-1"></i><?= $p['featured'] ? 'Yes' : 'No' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="flex gap-2">
                        <a href="<?= pageUrl('admin.product.edit', ['id' => $p['id']]) ?>" class="btn-outline text-xs px-2 py-1">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <form method="POST" action="<?= pageUrl('admin.products') ?>" onsubmit="return confirm('Deactivate this product?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn-outline text-xs px-2 py-1 text-red-500 border-red-300 hover:bg-red-500 hover:text-white">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
renderLayout('Products — Admin', $content, ['admin' => true]);
