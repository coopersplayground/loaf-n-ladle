<?php
Auth::requireAdmin();
$db = Database::getInstance();

// Create uploads directory if it doesn't exist
$uploadsDir = APP_ROOT . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.settings')); }
    
    // Handle logo deletion
    if (post('delete_logo')) {
        $oldLogo = setting('site_logo');
        if ($oldLogo && file_exists($uploadsDir . '/' . $oldLogo)) {
            unlink($uploadsDir . '/' . $oldLogo);
        }
        $db->execute("DELETE FROM settings WHERE key = 'site_logo'");
        flash('success', 'Logo deleted successfully.');
        redirect(pageUrl('admin.settings'));
    }
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            flash('error', 'Logo must be a JPEG, PNG, GIF, or WebP image.');
            redirect(pageUrl('admin.settings'));
        }
        
        if ($file['size'] > $maxSize) {
            flash('error', 'Logo must be smaller than 2MB.');
            redirect(pageUrl('admin.settings'));
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $extension;
        $uploadPath = $uploadsDir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Delete old logo if exists
            $oldLogo = setting('site_logo');
            if ($oldLogo && file_exists($uploadsDir . '/' . $oldLogo)) {
                unlink($uploadsDir . '/' . $oldLogo);
            }
            
            // Save new logo filename
            $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['site_logo', $filename]);
            flash('success', 'Logo uploaded successfully.');
        } else {
            flash('error', 'Failed to upload logo.');
            redirect(pageUrl('admin.settings'));
        }
    }
    
    // Handle other settings
    $fields = ['store_email','store_phone','store_address','store_hours','tax_rate','delivery_radius_miles'];
    foreach ($fields as $field) {
        $val = sanitize(post($field, ''));
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", [$field, $val]);
    }
    
    flash('success', 'Settings saved successfully.');
    redirect(pageUrl('admin.settings'));
}

$currentLogo = setting('site_logo');
$logoUrl = $currentLogo ? pageUrl('uploads', ['file' => $currentLogo]) : '';

ob_start();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">General Settings</h1>
    <p class="text-gray-500 text-sm mt-1">Configure your store information and preferences</p>
</div>

<div class="max-w-4xl">
    <form method="POST" action="<?= pageUrl('admin.settings') ?>" enctype="multipart/form-data">
        <?= csrfField() ?>

        <!-- Logo Upload -->
        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Site Logo</h2>
            <div class="flex items-start gap-6">
                <div class="flex-shrink-0">
                    <?php if ($currentLogo): ?>
                    <img src="<?= $logoUrl ?>" alt="Site Logo" class="w-24 h-24 object-contain border border-gray-200 rounded-lg p-2 bg-white">
                    <?php else: ?>
                    <div class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-50">
                        <i class="fa-solid fa-image text-gray-400 text-2xl"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <div>
                        <label class="form-label">Upload Logo</label>
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" class="form-input">
                        <p class="text-warm text-xs mt-1">JPEG, PNG, GIF, or WebP. Max 2MB. Recommended size: 200x60px.</p>
                    </div>
                    <?php if ($currentLogo): ?>
                    <div class="mt-3">
                        <button type="submit" name="delete_logo" value="1" class="btn-outline text-red-500 border-red-300 hover:bg-red-500 hover:text-white px-4 py-2 text-sm">
                            <i class="fa-solid fa-trash mr-2"></i>Delete Current Logo
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Store Information -->
        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Store Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Store Email</label>
                    <input type="email" name="store_email" class="form-input" value="<?= e(setting('store_email')) ?>" placeholder="hello@loafnladle.com">
                </div>
                <div>
                    <label class="form-label">Store Phone</label>
                    <input type="text" name="store_phone" class="form-input" value="<?= e(setting('store_phone')) ?>" placeholder="(555) 123-4567">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Store Address</label>
                    <input type="text" name="store_address" class="form-input" value="<?= e(setting('store_address')) ?>" placeholder="123 Artisan Way, Local Town, ST 12345">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Store Hours</label>
                    <input type="text" name="store_hours" class="form-input" value="<?= e(setting('store_hours')) ?>" placeholder="Mon-Fri: 7am-7pm | Sat-Sun: 8am-5pm">
                </div>
            </div>
        </div>

        <!-- Order Settings -->
        <div class="card p-6 mb-5">
            <h2 class="font-bold text-gray-700 mb-4">Order Settings</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Tax Rate</label>
                    <div class="relative">
                        <input type="number" name="tax_rate" class="form-input pr-8" step="0.001" min="0" max="1"
                            value="<?= setting('tax_rate', '0.08') ?>" placeholder="0.08">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-warm text-sm">%</span>
                    </div>
                    <p class="text-warm text-xs mt-1">Enter as decimal (e.g. 0.08 = 8%)</p>
                </div>
                <div>
                    <label class="form-label">Free Delivery Radius (miles)</label>
                    <input type="number" name="delivery_radius_miles" class="form-input" min="0"
                        value="<?= setting('delivery_radius_miles', '20') ?>" placeholder="20">
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary px-8 py-2.5">
            <i class="fa-solid fa-floppy-disk mr-2"></i>Save Settings
        </button>
    </form>
</div>

<!-- Logo Preview in Header -->
<?php if ($currentLogo): ?>
<div class="card p-4 mt-6 max-w-4xl">
    <h3 class="font-semibold text-gray-700 mb-3">Logo Preview</h3>
    <div class="bg-cream-dark rounded-lg p-4">
        <div class="flex items-center gap-4">
            <img src="<?= $logoUrl ?>" alt="Site Logo" class="h-12 object-contain">
            <div>
                <div class="text-sm font-semibold text-brown">Header Logo</div>
                <div class="text-xs text-warm">This is how your logo appears in the site header</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
renderLayout('Settings — Admin', $content, ['admin' => true]);
