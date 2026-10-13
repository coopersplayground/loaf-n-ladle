<?php
Auth::requireAdmin();
$db = Database::getInstance();

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.plans')); }
    $action = post('action');

    if ($action === 'update_delivery_days') {
        $days = post('delivery_days', []);
        
        foreach ($days as $dayId => $dayData) {
            $maxDeliveries = (int)($dayData['max_deliveries'] ?? 10);
            $isAvailable = isset($dayData['is_available']) ? 1 : 0;
            
            $db->execute("UPDATE delivery_days SET max_deliveries = ?, is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", 
                [$maxDeliveries, $isAvailable, (int)$dayId]);
        }
        
        flash('success', 'Delivery days updated.');
        redirect(pageUrl('admin.plans'));
    }
    
    if ($action === 'save') {
        $id = (int)post('id');
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $loaves = sanitize(post('loaves_per_week'));
        $price = (float)post('price_per_week');
        $priceBiWeekly = (float)post('price_bi_weekly');
        $priceMonthly = (float)post('price_monthly');
        $priceOneTime = post('price_one_time') ? (float)post('price_one_time') : null;
        $stripePriceId = sanitize(post('stripe_price_id'));
        $stripeProductId = sanitize(post('stripe_product_id'));
        $featuresRaw = post('features', '');
        $featuresArr = array_filter(array_map('trim', explode("\n", $featuresRaw)));
        $features = json_encode(array_values($featuresArr));
        $active = post('active') ? 1 : 0;
        $sortOrder = (int)post('sort_order');
        $totalMaxSelections = post('total_max_selections') ? (int)post('total_max_selections') : null;

        if (strlen($name) < 2 || $price <= 0 || empty($loaves) || $priceBiWeekly <= 0 || $priceMonthly <= 0) {
            flash('error', 'Name, all prices, and highlight are required.'); redirect(pageUrl('admin.plans'));
        }

        $slug = slugify($name);
        if ($id) {
            $db->execute("UPDATE subscription_plans SET name=?, slug=?, description=?, loaves_per_week=?, price_per_week=?, price_bi_weekly=?, price_monthly=?, price_one_time=?, stripe_price_id=?, stripe_product_id=?, features=?, active=?, sort_order=?, total_max_selections=? WHERE id=?",
                [$name, $slug, $description, $loaves, $price, $priceBiWeekly, $priceMonthly, $priceOneTime, $stripePriceId, $stripeProductId, $features, $active, $sortOrder, $totalMaxSelections, $id]);
            flash('success', 'Plan updated.');
        } else {
            $existing = $db->fetchOne("SELECT id FROM subscription_plans WHERE slug = ?", [$slug]);
            if ($existing) $slug .= '-' . time();
            $db->insert("INSERT INTO subscription_plans (name, slug, description, loaves_per_week, price_per_week, price_bi_weekly, price_monthly, price_one_time, stripe_price_id, stripe_product_id, features, active, sort_order, total_max_selections) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$name, $slug, $description, $loaves, $price, $priceBiWeekly, $priceMonthly, $priceOneTime, $stripePriceId, $stripeProductId, $features, $active, $sortOrder, $totalMaxSelections]);
            flash('success', 'Plan created.');
        }
        redirect(pageUrl('admin.plans'));
    }

    if ($action === 'save_items') {
        $planId = (int)post('plan_id');
        $categories = post('categories', []);
        $maxSelections = post('max_selections', []);
        $products = post('products', []);
        
        // Clear existing items
        $db->execute("DELETE FROM subscription_plan_item_products WHERE plan_item_id IN (SELECT id FROM subscription_plan_items WHERE plan_id = ?)", [$planId]);
        $db->execute("DELETE FROM subscription_plan_items WHERE plan_id = ?", [$planId]);
        
        // Add new items (prevent duplicates by tracking used categories)
        $usedCategories = [];
        $sortOrder = 0;
        
        // Process categories in order of their array keys to maintain sort order
        ksort($categories);
        
        foreach ($categories as $index => $catId) {
            if (empty($catId)) continue;
            
            // Skip if this category is already used
            if (in_array($catId, $usedCategories)) {
                continue;
            }
            $usedCategories[] = $catId;
            
            $maxSel = (int)($maxSelections[$index] ?? 1);
            $planItemId = $db->insert("INSERT INTO subscription_plan_items (plan_id, category_id, max_selections, sort_order) VALUES (?,?,?,?)",
                [$planId, $catId, $maxSel, $sortOrder]);
            
            // Add products for this category
            if (isset($products[$index]) && is_array($products[$index])) {
                $productSortOrder = 0;
                foreach ($products[$index] as $prodIndex => $prodId) {
                    if (empty($prodId)) continue;
                    $db->insert("INSERT OR REPLACE INTO subscription_plan_item_products (plan_item_id, product_id, sort_order) VALUES (?,?,?)",
                        [$planItemId, $prodId, $productSortOrder]);
                    $productSortOrder++;
                }
            }
            $sortOrder++;
        }
        flash('success', 'Plan items updated.');
        redirect(pageUrl('admin.plans', ['edit_items' => $planId]));
    }

    if ($action === 'toggle') {
        $id = (int)post('id');
        $db->execute("UPDATE subscription_plans SET active = NOT active WHERE id = ?", [$id]);
        redirect(pageUrl('admin.plans'));
    }
}

$plans = $db->fetchAll("SELECT * FROM subscription_plans ORDER BY sort_order");
$categories = $db->fetchAll("SELECT * FROM categories WHERE active = 1 ORDER BY sort_order");
$deliveryDays = $db->fetchAll("SELECT * FROM delivery_days ORDER BY sort_order");
$editId = (int)get('edit');
$editPlan = $editId ? $db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$editId]) : null;
$editItemsId = (int)get('edit_items');
$editItemsPlan = $editItemsId ? $db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$editItemsId]) : null;

// Get plan items for editing
$planItems = [];
if ($editItemsPlan) {
    $planItems = $db->fetchAll(
        "SELECT spi.*, c.name as category_name, c.slug as category_slug
         FROM subscription_plan_items spi
         JOIN categories c ON spi.category_id = c.id
         WHERE spi.plan_id = ? AND c.active = 1 ORDER BY spi.sort_order",
        [$editItemsId]
    );
    foreach ($planItems as &$item) {
        $item['products'] = $db->fetchAll(
            "SELECT p.* FROM subscription_plan_item_products spip
             JOIN products p ON spip.product_id = p.id
             WHERE spip.plan_item_id = ? ORDER BY spip.sort_order",
            [$item['id']]
        );
    }
    unset($item);
}

if ($editPlan) {
    $editFeatures = implode("\n", json_decode($editPlan['features'] ?? '[]', true));
}

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Subscription Plans</h1>
        <p class="text-gray-500 text-sm mt-1">Manage weekly bread subscription plans and available items</p>
    </div>
    <button onclick="document.getElementById('planForm').classList.toggle('hidden')" class="btn-primary">
        <i class="fa-solid fa-plus mr-2"></i>Add Plan
    </button>
</div>

<!-- Add/Edit Plan Form -->
<div id="planForm" class="<?= $editPlan ? '' : 'hidden' ?> card p-6 mb-6">
    <h2 class="font-bold text-gray-700 mb-4"><?= $editPlan ? 'Edit Plan' : 'New Subscription Plan' ?></h2>
    <form method="POST" action="<?= pageUrl('admin.plans') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editPlan['id'] ?? 0 ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="form-label">Plan Name *</label>
                <input type="text" name="name" class="form-input" required value="<?= e($editPlan['name'] ?? '') ?>" placeholder="e.g. Basic Plan">
            </div>
            <div>
                <label class="form-label">highlight *</label>
                <input type="text" name="loaves_per_week" class="form-input" required value="<?= $editPlan['loaves_per_week'] ?? 2 ?>">
            </div>
            <div>
                <label class="form-label">Price per Week ($) *</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                    <input type="number" name="price_per_week" class="form-input pl-7" required step="0.01" min="0.01"
                        value="<?= number_format($editPlan['price_per_week'] ?? 0, 2) ?>">
                </div>
            </div>
            <div>
                <label class="form-label">Price Bi-Weekly ($) *</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                    <input type="number" name="price_bi_weekly" class="form-input pl-7" required step="0.01" min="0.01"
                        value="<?= number_format($editPlan['price_bi_weekly'] ?? 0, 2) ?>">
                </div>
            </div>
            <div>
                <label class="form-label">Price Monthly ($) *</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                    <input type="number" name="price_monthly" class="form-input pl-7" required step="0.01" min="0.01"
                        value="<?= number_format($editPlan['price_monthly'] ?? 0, 2) ?>">
                </div>
            </div>
            <div>
                <label class="form-label">Price One Time ($) <span class="text-xs font-normal text-warm">(optional)</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-warm">$</span>
                    <input type="number" name="price_one_time" class="form-input pl-7" step="0.01" min="0.01"
                        value="<?= number_format($editPlan['price_one_time'] ?? 0, 2) ?>" placeholder="Leave empty to disable">
                </div>
                <p class="text-warm text-xs mt-1">Optional. Enables one-time purchase option.</p>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-input" value="<?= e($editPlan['description'] ?? '') ?>" placeholder="Brief plan description...">
            </div>
            <div>
                <label class="form-label">Stripe Price ID</label>
                <input type="text" name="stripe_price_id" class="form-input font-mono text-sm" value="<?= e($editPlan['stripe_price_id'] ?? '') ?>" placeholder="price_xxxxx">
                <p class="text-warm text-xs mt-1">Optional. If blank, price is created dynamically.</p>
            </div>
            <div>
                <label class="form-label">Stripe Product ID</label>
                <input type="text" name="stripe_product_id" class="form-input font-mono text-sm" value="<?= e($editPlan['stripe_product_id'] ?? '') ?>" placeholder="prod_xxxxx">
            </div>
            <div>
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-input" value="<?= $editPlan['sort_order'] ?? 0 ?>" min="0">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label">Features <span class="text-xs font-normal text-warm">(one per line)</span></label>
                <textarea name="features" rows="5" class="form-input font-mono text-sm" placeholder="2 loaves/week&#10;Free delivery within 20 miles&#10;Cancel anytime"><?= e($editFeatures ?? '') ?></textarea>
            </div>
            <div>
                <label class="form-label">Total Max Selections <span class="text-xs font-normal text-warm">(overrides category limits)</span></label>
                <input type="number" name="total_max_selections" class="form-input" min="1" value="<?= $editPlan['total_max_selections'] ?? '' ?>" placeholder="Leave empty to use category limits">
                <p class="text-warm text-xs mt-1">Optional. Sets overall limit regardless of individual category limits.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="active" value="1" id="planActive" class="w-4 h-4 accent-brown" <?= ($editPlan['active'] ?? 1) ? 'checked' : '' ?>>
                <label for="planActive" class="form-label mb-0 cursor-pointer">Active (visible to customers)</label>
            </div>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-6 py-2"><?= $editPlan ? 'Update' : 'Create' ?> Plan</button>
            <a href="<?= pageUrl('admin.plans') ?>" class="btn-outline px-6 py-2">Cancel</a>
        </div>
    </form>
</div>

<!-- Edit Plan Items Form -->
<?php if ($editItemsPlan): ?>
<div class="card p-6 mb-6">
    <h2 class="font-bold text-gray-700 mb-4">Manage Items for "<?= e($editItemsPlan['name']) ?>"</h2>
    <form method="POST" action="<?= pageUrl('admin.plans') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_items">
        <input type="hidden" name="plan_id" value="<?= $editItemsPlan['id'] ?>">
        
        <div id="categoriesContainer" class="space-y-4 mb-4">
            <?php foreach ($planItems as $index => $item): ?>
            <div class="border-2 border-gray-200 rounded-lg p-4" data-category-index="<?= $index ?>">
                <div class="flex items-start gap-4 mb-3">
                    <div class="flex-1">
                        <label class="form-label">Category</label>
                        <select name="categories[<?= $index ?>]" class="form-input" required onchange="updateProducts(this); updateAllCategorySelects()">
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $item['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-32">
                        <label class="form-label">Max Selections</label>
                        <input type="number" name="max_selections[<?= $index ?>]" class="form-input" required min="1" value="<?= $item['max_selections'] ?>">
                    </div>
                    <div class="pt-6">
                        <button type="button" onclick="removeCategory(this)" class="btn-outline text-red-500 border-red-300 hover:bg-red-500 hover:text-white px-3 py-2">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <label class="form-label mb-0">Available Products</label>
                        <span class="text-xs text-warm bg-gray-100 px-2 py-1 rounded"><?= e($item['category_name']) ?></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <?php
                        $allProducts = $db->fetchAll("SELECT * FROM products WHERE category_id = ? AND available = 1 ORDER BY name", [$item['category_id']]);
                        $selectedProducts = array_column($item['products'], 'id');
                        foreach ($allProducts as $product):
                        ?>
                        <label class="flex items-center gap-2 p-2 border rounded cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" name="products[<?= $index ?>][]" value="<?= $product['id'] ?>"
                                class="w-4 h-4 accent-brown" <?= in_array($product['id'], $selectedProducts) ? 'checked' : '' ?>>
                            <span class="text-sm"><?= e($product['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="addCategory()" class="btn-outline px-4 py-2">
                <i class="fa-solid fa-plus mr-2"></i>Add Category
            </button>
            <button type="submit" class="btn-primary px-6 py-2">Save Items</button>
            <a href="<?= pageUrl('admin.plans') ?>" class="btn-outline px-6 py-2">Done</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Delivery Days Management -->
<div class="card p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-700 text-lg">Delivery Days Management</h3>
        <span class="text-sm text-gray-500">Set delivery capacity and availability</span>
    </div>
    <form method="POST" action="<?= pageUrl('admin.plans') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_delivery_days">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($deliveryDays as $day): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-800"><?= e($day['day_name']) ?></h4>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="delivery_days[<?= $day['id'] ?>][is_available]" value="1" 
                            id="day_<?= $day['id'] ?>" class="w-4 h-4 accent-green-500" 
                            <?= $day['is_available'] ? 'checked' : '' ?>>
                        <label for="day_<?= $day['id'] ?>" class="text-sm cursor-pointer">
                            <?= $day['is_available'] ? '<span class="text-green-600 font-medium">Available</span>' : '<span class="text-red-600 font-medium">Blocked</span>' ?>
                        </label>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm text-gray-600">Max Deliveries per Day</label>
                    <input type="number" name="delivery_days[<?= $day['id'] ?>][max_deliveries]" 
                        class="form-input" min="1" max="50" value="<?= $day['max_deliveries'] ?>"
                        <?= !$day['is_available'] ? 'disabled' : '' ?>>
                    <p class="text-xs text-gray-500">
                        <?php if ($day['is_available']): ?>
                            Up to <?= $day['max_deliveries'] ?> deliveries can be scheduled
                        <?php else: ?>
                            Day is blocked - shows as "FULL" to customers
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-6">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-calendar-days mr-2"></i>Update Delivery Days
            </button>
        </div>
    </form>
</div>

<!-- Plans Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
    <?php foreach ($plans as $plan):
        $features = json_decode($plan['features'] ?? '[]', true);
        $subCount = $db->fetchOne("SELECT COUNT(*) as c FROM subscriptions WHERE plan_id = ? AND status = 'active'", [$plan['id']])['c'];
        $itemCount = $db->fetchOne("SELECT COUNT(*) as c FROM subscription_plan_items WHERE plan_id = ?", [$plan['id']])['c'];
    ?>
    <div class="card p-5 <?= !$plan['active'] ? 'opacity-60' : '' ?>">
        <div class="flex items-start justify-between mb-3">
            <h3 class="font-bold text-brown text-lg"><?= $plan['name'] ?></h3>
            <span class="badge <?= $plan['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $plan['active'] ? 'Active' : 'Hidden' ?></span>
        </div>
        <div class="text-3xl font-bold text-brown mb-1"><?= formatMoney($plan['price_per_week']) ?></div>
        <div class="text-sm text-gold font-semibold mb-3"><?= e($plan['loaves_per_week']) ?></div>
        <?php if ($subCount > 0): ?>
        <div class="badge badge-blue mb-3"><?= $subCount ?> active subscriber<?= $subCount !== 1 ? 's' : '' ?></div>
        <?php endif; ?>
        <?php if ($itemCount > 0): ?>
        <div class="badge badge-yellow mb-3"><?= $itemCount ?> categor<?= $itemCount !== 1 ? 'ies' : 'y' ?></div>
        <?php endif; ?>
        <ul class="space-y-1.5 mb-4">
            <?php foreach (array_slice($features, 0, 4) as $f): ?>
            <li class="text-xs text-warm flex items-start gap-1.5">
                <i class="fa-solid fa-check text-green-500 mt-0.5 flex-shrink-0"></i><?= e($f) ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($plan['stripe_price_id']): ?>
        <div class="text-xs text-warm font-mono mb-3 truncate" title="<?= e($plan['stripe_price_id']) ?>">
            <i class="fa-brands fa-stripe mr-1"></i><?= e(substr($plan['stripe_price_id'], 0, 20)) ?>...
        </div>
        <?php endif; ?>
        <div class="flex gap-2">
            <a href="<?= pageUrl('admin.plans', ['edit' => $plan['id']]) ?>" class="btn-outline text-xs px-3 py-1.5 flex-1 text-center">
                <i class="fa-solid fa-pen mr-1"></i>Edit
            </a>
            <a href="<?= pageUrl('admin.plans', ['edit_items' => $plan['id']]) ?>" class="btn-outline text-xs px-3 py-1.5 flex-1 text-center">
                <i class="fa-solid fa-list mr-1"></i>Items
            </a>
            <form method="POST" action="<?= pageUrl('admin.plans') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                <button type="submit" class="btn-outline text-xs px-3 py-1.5">
                    <?= $plan['active'] ? 'Hide' : 'Show' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
const categories = <?= json_encode($categories) ?>;
const allProducts = <?= json_encode($db->fetchAll("SELECT * FROM products WHERE available = 1 ORDER BY category_id, name")) ?>;

// Counter for unique category indices — must be plain integers so PHP doesn't truncate float keys
let categoryCounter = <?= count($planItems) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Stamp data-base-name on every existing option so updateAllCategorySelects works correctly
    document.querySelectorAll('#categoriesContainer select[name^="categories["] option').forEach(opt => {
        if (opt.value) opt.dataset.baseName = opt.textContent;
    });
    updateAllCategorySelects();
});

function addCategory() {
    const container = document.getElementById('categoriesContainer');
    const usedValues = Array.from(container.querySelectorAll('select[name^="categories["]'))
        .map(s => s.value).filter(v => v);

    const idx = categoryCounter++;

    const categoryOptions = categories.map(cat => {
        const used = usedValues.includes(String(cat.id));
        return `<option value="${cat.id}" data-base-name="${cat.name}" ${used ? 'disabled' : ''}>${cat.name}${used ? ' (already used)' : ''}</option>`;
    }).join('');

    const div = document.createElement('div');
    div.className = 'border-2 border-gray-200 rounded-lg p-4';
    div.dataset.categoryIndex = idx;
    div.innerHTML = `
        <div class="flex items-start gap-4 mb-3">
            <div class="flex-1">
                <label class="form-label">Category</label>
                <select name="categories[${idx}]" class="form-input" required onchange="updateProducts(this); updateAllCategorySelects()">
                    <option value="">Select category...</option>
                    ${categoryOptions}
                </select>
            </div>
            <div class="w-32">
                <label class="form-label">Max Selections</label>
                <input type="number" name="max_selections[${idx}]" class="form-input" required min="1" value="1">
            </div>
            <div class="pt-6">
                <button type="button" onclick="removeCategory(this)" class="btn-outline text-red-500 border-red-300 hover:bg-red-500 hover:text-white px-3 py-2">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <div>
            <div class="flex items-center gap-2 mb-2">
                <label class="form-label mb-0">Available Products</label>
                <span class="text-xs text-warm bg-gray-100 px-2 py-1 rounded category-badge">Select a category</span>
            </div>
            <div class="products-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <div class="text-warm text-sm col-span-full">Select a category to see available products</div>
            </div>
        </div>
    `;

    container.appendChild(div);
    updateAllCategorySelects();
}

function removeCategory(button) {
    button.closest('[data-category-index]').remove();
    updateAllCategorySelects();
}

function updateAllCategorySelects() {
    const container = document.getElementById('categoriesContainer');
    const allSelects = Array.from(container.querySelectorAll('select[name^="categories["]'));
    const selectedValues = allSelects.map(s => s.value).filter(v => v);

    allSelects.forEach(select => {
        const current = select.value;
        select.querySelectorAll('option[value]').forEach(opt => {
            if (!opt.value) return;
            const base = opt.dataset.baseName || opt.textContent.replace(' (already used)', '');
            opt.dataset.baseName = base;
            const usedElsewhere = opt.value !== current && selectedValues.includes(opt.value);
            opt.disabled = usedElsewhere;
            opt.textContent = base + (usedElsewhere ? ' (already used)' : '');
        });
    });
}

function updateProducts(select) {
    const categoryId = parseInt(select.value);
    const row = select.closest('[data-category-index]');
    const productsDiv = row.querySelector('.products-grid');
    const idx = row.dataset.categoryIndex;

    if (!categoryId) {
        productsDiv.innerHTML = '<div class="text-warm text-sm col-span-full">Select a category to see available products</div>';
        const badge = row.querySelector('.category-badge');
        if (badge) badge.textContent = 'Select a category';
        return;
    }

    const cat = categories.find(c => c.id === categoryId);
    const catName = cat ? cat.name : '';
    const catProducts = allProducts.filter(p => p.category_id === categoryId);

    const badge = row.querySelector('.category-badge');
    if (badge) badge.textContent = catName;

    if (catProducts.length === 0) {
        productsDiv.innerHTML = '<div class="text-warm text-sm col-span-full">No products available in this category</div>';
        return;
    }

    productsDiv.innerHTML = catProducts.map(p => `
        <label class="flex items-center gap-2 p-2 border rounded cursor-pointer hover:bg-gray-50">
            <input type="checkbox" name="products[${idx}][]" value="${p.id}" class="w-4 h-4 accent-brown">
            <span class="text-sm">${p.name}</span>
        </label>
    `).join('');

    updateAllCategorySelects();
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Subscription Plans — Admin', $content, ['admin' => true]);
