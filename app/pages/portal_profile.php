<?php
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance();

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('portal.profile')); }
    $action = post('action');

    if ($action === 'update_profile') {
        $name = sanitize(post('name'));
        $phone = sanitize(post('phone'));
        $address = sanitize(post('address'));
        if (strlen($name) < 2) { flash('error', 'Name is required.'); redirect(pageUrl('portal.profile')); }
        $db->execute(
            "UPDATE users SET name = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$name, $phone, $address, $user['id']]
        );
        flash('success', 'Profile updated successfully.');
        redirect(pageUrl('portal.profile'));
    }

    if ($action === 'change_password') {
        $current = post('current_password');
        $new = post('new_password');
        $confirm = post('confirm_password');
        if (!password_verify($current, $user['password'])) { flash('error', 'Current password is incorrect.'); redirect(pageUrl('portal.profile')); }
        if (strlen($new) < 8) { flash('error', 'New password must be at least 8 characters.'); redirect(pageUrl('portal.profile')); }
        if ($new !== $confirm) { flash('error', 'New passwords do not match.'); redirect(pageUrl('portal.profile')); }
        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->execute("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$hash, $user['id']]);
        flash('success', 'Password changed successfully.');
        redirect(pageUrl('portal.profile'));
    }
}

// Refresh user data
$user = Auth::currentUser();

ob_start();
?>
<section class="bg-cream-dark py-10 px-4 border-b border-gold/20">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-brown">My Profile</h1>
        <p class="text-warm mt-1">Manage your account information</p>
    </div>
</section>

<section class="py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <!-- Portal Nav Tabs -->
        <div class="flex gap-2 mb-8 border-b border-gold/20 pb-0">
            <a href="<?= pageUrl('portal') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Dashboard</a>
            <a href="<?= pageUrl('portal.orders') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Orders</a>
            <a href="<?= pageUrl('portal.subscriptions') ?>" class="tab-btn border-b-2 border-transparent rounded-b-none pb-3">Subscriptions</a>
            <a href="<?= pageUrl('portal.profile') ?>" class="tab-btn active border-b-2 border-brown rounded-b-none pb-3">Profile</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Update Profile -->
            <div class="card p-6">
                <h2 class="font-bold text-brown text-xl mb-5">Personal Information</h2>
                <form method="POST" action="<?= pageUrl('portal.profile') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" required value="<?= e($user['name']) ?>">
                        </div>
                        <div>
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input bg-gray-50 cursor-not-allowed" value="<?= e($user['email']) ?>" disabled>
                            <p class="text-warm text-xs mt-1">Email cannot be changed. Contact support if needed.</p>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-input" value="<?= e($user['phone'] ?? '') ?>" placeholder="(555) 123-4567">
                        </div>
                        <div>
                            <label class="form-label">Default Delivery Address</label>
                            <input type="text" name="address" class="form-input" value="<?= e($user['address'] ?? '') ?>" placeholder="123 Main St, City, State 12345">
                        </div>
                        <button type="submit" class="btn-primary w-full py-2.5">
                            <i class="fa-solid fa-floppy-disk mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card p-6">
                <h2 class="font-bold text-brown text-xl mb-5">Change Password</h2>
                <form method="POST" action="<?= pageUrl('portal.profile') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" required placeholder="••••••••">
                        </div>
                        <div>
                            <label class="form-label">New Password <span class="text-xs font-normal text-warm">(min 8 chars)</span></label>
                            <input type="password" name="new_password" class="form-input" required placeholder="••••••••" minlength="8">
                        </div>
                        <div>
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required placeholder="••••••••">
                        </div>
                        <button type="submit" class="btn-primary w-full py-2.5">
                            <i class="fa-solid fa-lock mr-2"></i>Update Password
                        </button>
                    </div>
                </form>

                <!-- Account Info -->
                <div class="mt-6 pt-6 border-t border-gold/20">
                    <h3 class="font-semibold text-brown mb-3">Account Details</h3>
                    <div class="space-y-2 text-sm text-warm">
                        <div class="flex justify-between">
                            <span>Member since</span>
                            <span class="text-brown font-medium"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Account type</span>
                            <span class="text-brown font-medium capitalize"><?= e($user['role']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="mt-6 pt-6 border-t border-red-100">
                    <h3 class="font-semibold text-red-600 mb-3">Sign Out</h3>
                    <a href="<?= pageUrl('logout') ?>" class="btn-outline text-red-500 border-red-300 hover:bg-red-500 hover:text-white hover:border-red-500 w-full text-center block py-2">
                        <i class="fa-solid fa-right-from-bracket mr-2"></i>Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
renderLayout('My Profile', $content);
