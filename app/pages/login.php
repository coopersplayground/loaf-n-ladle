<?php
if (Auth::isLoggedIn()) { redirect(pageUrl('portal')); }

$redirectTo = get('redirect', pageUrl('portal'));

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('login')); }
    $email = post('email');
    $password = post('password');
    $user = Auth::login($email, $password);
    if ($user) {
        flash('success', 'Welcome back, ' . $user['name'] . '!');
        $dest = post('redirect', pageUrl('portal'));
        redirect($dest);
    } else {
        flash('error', 'Invalid email or password. Please try again.');
        redirect(pageUrl('login'));
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
            <h1 class="text-3xl font-bold text-brown">Welcome Back</h1>
            <p class="text-warm mt-2">Sign in to your Loaf'n Ladle account</p>
        </div>

        <div class="card p-8">
            <form method="POST" action="<?= pageUrl('login') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <div class="space-y-5">
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" required autofocus
                            placeholder="you@example.com" value="<?= e(post('email')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="passwordField" class="form-input pr-10" required placeholder="••••••••">
                            <button type="button" onclick="togglePwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm hover:text-brown">
                                <i class="fa-solid fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 text-lg">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign In
                    </button>
                </div>
            </form>
            <div class="mt-6 text-center text-sm text-warm">
                Don't have an account?
                <a href="<?= pageUrl('register') . ($redirectTo !== pageUrl('portal') ? '&redirect=' . urlencode($redirectTo) : '') ?>" class="text-brown font-semibold hover:underline">Create one</a>
            </div>
        </div>

        <div class="mt-4 text-center text-xs text-warm">
            <i class="fa-solid fa-lock mr-1"></i>Your information is secure and encrypted
        </div>
    </div>
</section>
<script>
function togglePwd() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'fa-solid fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fa-solid fa-eye'; }
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Sign In', $content);
