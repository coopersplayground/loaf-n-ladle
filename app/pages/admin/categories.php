<?php
Auth::requireAdmin();
$db = Database::getInstance();

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.categories')); }
    $action = post('action');

    if ($action === 'save') {
        $id = (int)post('id');
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $icon = sanitize(post('icon'));
        $sortOrder = (int)post('sort_order');
        $active = post('active') ? 1 : 0;
        if (strlen($name) < 2) { flash('error', 'Name required.'); redirect(pageUrl('admin.categories')); }
        $slug = slugify($name);
        if ($id) {
            $db->execute("UPDATE categories SET name=?, description=?, icon=?, sort_order=?, active=? WHERE id=?", [$name, $description, $icon, $sortOrder, $active, $id]);
            flash('success', 'Category updated.');
        } else {
            $existing = $db->fetchOne("SELECT id FROM categories WHERE slug = ?", [$slug]);
            if ($existing) $slug .= '-' . time();
            $db->insert("INSERT INTO categories (name, slug, description, icon, sort_order, active) VALUES (?,?,?,?,?,?)", [$name, $slug, $description, $icon, $sortOrder, $active]);
            flash('success', 'Category created.');
        }
        redirect(pageUrl('admin.categories'));
    }
    if ($action === 'delete') {
        $id = (int)post('id');
        $db->execute("UPDATE categories SET active = 0 WHERE id = ?", [$id]);
        flash('success', 'Category deactivated.');
        redirect(pageUrl('admin.categories'));
    }
}

$categories = $db->fetchAll("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count FROM categories c WHERE c.active = 1 ORDER BY c.sort_order");
$editId = (int)get('edit');
$editCat = $editId ? $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$editId]) : null;

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Categories</h1>
    <button onclick="document.getElementById('addForm').classList.toggle('hidden')" class="btn-primary">
        <i class="fa-solid fa-plus mr-2"></i>Add Category
    </button>
</div>

<!-- Add Form -->
<div id="addForm" class="<?= $editCat ? '' : 'hidden' ?> card p-6 mb-6">
    <h2 class="font-bold text-gray-700 mb-4"><?= $editCat ? 'Edit Category' : 'New Category' ?></h2>
    <form method="POST" action="<?= pageUrl('admin.categories') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editCat['id'] ?? 0 ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-input" required value="<?= e($editCat['name'] ?? '') ?>" placeholder="e.g. Breads & Pastries">
            </div>
            <div>
                <label class="form-label">Icon (FA class)</label>
                <input type="text" name="icon" class="form-input" value="<?= e($editCat['icon'] ?? 'fa-bread-slice') ?>" placeholder="fa-bread-slice">
            </div>
            <div>
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-input" value="<?= $editCat['sort_order'] ?? 0 ?>" min="0">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" value="1" class="w-4 h-4 accent-brown" <?= ($editCat['active'] ?? 1) ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">Active</span>
                </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-input" value="<?= e($editCat['description'] ?? '') ?>" placeholder="Brief description...">
            </div>
        </div>
        <div class="flex gap-3 mt-4">
            <button type="submit" class="btn-primary px-6 py-2"><?= $editCat ? 'Update' : 'Create' ?> Category</button>
            <a href="<?= pageUrl('admin.categories') ?>" class="btn-outline px-6 py-2">Cancel</a>
        </div>
    </form>
</div>

<div class="card overflow-hidden">
    <table class="data-table">
        <thead>
            <tr><th>Category</th><th>Slug</th><th>Products</th><th>Sort</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid <?= e($cat['icon']) ?> text-brown w-5 text-center"></i>
                        <div>
                            <div class="font-semibold text-brown"><?= e($cat['name']) ?></div>
                            <div class="text-warm text-xs"><?= e($cat['description']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="text-warm text-sm font-mono"><?= e($cat['slug']) ?></td>
                <td class="text-warm text-sm"><?= $cat['product_count'] ?></td>
                <td class="text-warm text-sm"><?= $cat['sort_order'] ?></td>
                <td><span class="badge <?= $cat['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $cat['active'] ? 'Active' : 'Hidden' ?></span></td>
                <td>
                    <div class="flex gap-2">
                        <a href="<?= pageUrl('admin.categories', ['edit' => $cat['id']]) ?>" class="btn-outline text-xs px-2 py-1"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" action="<?= pageUrl('admin.categories') ?>" onsubmit="return confirm('Deactivate this category?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn-outline text-xs px-2 py-1 text-red-500 border-red-300 hover:bg-red-500 hover:text-white"><i class="fa-solid fa-trash"></i></button>
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
renderLayout('Categories — Admin', $content, ['admin' => true]);
