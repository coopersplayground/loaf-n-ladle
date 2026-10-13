<?php
if (Auth::isLoggedIn()) { redirect(pageUrl('portal')); }

$redirectTo = get('redirect', '');

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('register')); }
    $name = sanitize(post('name'));
    $email = post('email');
    $password = post('password');
    $confirm = post('password_confirm');
    $phone = sanitize(post('phone'));
    $address = sanitize(post('address'));
    $redirectTo = post('redirect', '');

    $registerUrl = pageUrl('register') . ($redirectTo ? '&redirect=' . urlencode($redirectTo) : '');
    if (strlen($name) < 2) { flash('error', 'Please enter your full name.'); redirect($registerUrl); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Please enter a valid email address.'); redirect($registerUrl); }
    if (strlen($password) < 8) { flash('error', 'Password must be at least 8 characters.'); redirect($registerUrl); }
    if ($password !== $confirm) { flash('error', 'Passwords do not match.'); redirect($registerUrl); }

    $result = Auth::register($name, $email, $password, $phone, $address);
    if (isset($result['error'])) {
        flash('error', $result['error']);
        redirect($registerUrl);
    } else {
        Auth::login($email, $password);
        flash('success', 'Welcome to Loaf\'n Ladle, ' . $name . '!');
        redirect($redirectTo ?: pageUrl('portal'));
    }
}

ob_start();
?>
<section class="min-h-screen bg-cream-dark flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="<?= pageUrl('home') ?>" class="inline-flex items-center gap-2 mb-4">
                <div class="w-12 h-12 bg-brown rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-bread-slice text-white text-lg"></i>
                </div>
            </a>
            <h1 class="text-3xl font-bold text-brown">Create Account</h1>
            <p class="text-warm mt-2">Join Loaf'n Ladle for easy ordering & subscriptions</p>
        </div>

        <div class="card p-8">
            <form method="POST" action="<?= pageUrl('register') . ($redirectTo ? '&redirect=' . urlencode($redirectTo) : '') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-input" required autofocus
                            placeholder="Jane Smith" value="<?= e(post('name')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" required
                            placeholder="you@example.com" value="<?= e(post('email')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input"
                            placeholder="(555) 123-4567" value="<?= e(post('phone')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Delivery Address</label>
                        <input type="text" name="address" class="form-input"
                            placeholder="123 Main St, City, State 12345" value="<?= e(post('address')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Password * <span class="text-xs font-normal text-warm">(min 8 characters)</span></label>
                        <div class="relative">
                            <input type="password" name="password" id="pwd1" class="form-input pr-10" required placeholder="••••••••" minlength="8">
                            <button type="button" onclick="togglePwd('pwd1','eye1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm hover:text-brown">
                                <i class="fa-solid fa-eye" id="eye1"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Confirm Password *</label>
                        <div class="relative">
                            <input type="password" name="password_confirm" id="pwd2" class="form-input pr-10" required placeholder="••••••••">
                            <button type="button" onclick="togglePwd('pwd2','eye2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm hover:text-brown">
                                <i class="fa-solid fa-eye" id="eye2"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 text-lg">
                        <i class="fa-solid fa-user-plus mr-2"></i>Create Account
                    </button>
                </div>
            </form>
            <div class="mt-6 text-center text-sm text-warm">
                Already have an account?
                <a href="<?= pageUrl('login') . ($redirectTo ? '&redirect=' . urlencode($redirectTo) : '') ?>" class="text-brown font-semibold hover:underline">Sign in</a>
            </div>
        </div>
    </div>
</section>
<script>
function togglePwd(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'fa-solid fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fa-solid fa-eye'; }
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Create Account', $content);
