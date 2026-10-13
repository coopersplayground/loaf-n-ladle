<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

Auth::startSession();

function renderLayout($title, $content, $options = []) {
    $isAdmin = $options['admin'] ?? false;
    $bodyClass = $options['bodyClass'] ?? '';
    $cartCount = cartCount();
    $isLoggedIn = Auth::isLoggedIn();
    $isAdminUser = Auth::isAdmin();
    $currentUser = $isLoggedIn ? Auth::currentUser() : null;
    $flash = renderFlash();
    $page = get('page', 'home');
    
    // Check if page is enabled (only for non-admin pages)
    $pageEnabled = true;
    $showComingSoon = false;
    if (!$isAdmin && $page) {
        $pageEnabled = isPageEnabled($page);
        $showComingSoon = !$pageEnabled;
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        html {
            scroll-behavior: smooth;
        }
        .text-brown {
            color: #8b4513;
        }
        .bg-cream {
            background-color: #faf8f3;
        }
        .text-gold {
            color: #d4a574;
        }
        .bg-gold {
            background-color: #d4a574;
        }
        .section-padding {
            padding: 80px 0;
        }
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .nav-link {
            position: relative;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #8b4513;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .btn-primary {
            background-color: #8b4513;
            color: white;
            padding: 0.6rem 1.4rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: inline-block;
            cursor: pointer;
            border: none;
        }
        .btn-primary:hover { background-color: #6b3410; transform: translateY(-1px); }
        .btn-outline {
            border: 2px solid #8b4513;
            color: #8b4513;
            padding: 0.6rem 1.4rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
            cursor: pointer;
            background: transparent;
        }
        .btn-outline:hover { background-color: #8b4513; color: white; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
        }
        .badge-green { background-color: #10b981; color: white; }
        .badge-blue { background-color: #3b82f6; color: white; }
        .badge-yellow { background-color: #f59e0b; color: white; }
        .badge-red { background-color: #ef4444; color: white; }
        .badge-gray { background-color: #6b7280; color: white; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-input {
            display: block;
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-input:focus {
            outline: none;
            border-color: #8b4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }
        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .section-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #d4a574, transparent);
            margin: 2rem 0;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
    </style>
</head>
<body class="bg-cream">
    <?php if ($isAdmin): ?>
<!-- ADMIN LAYOUT -->
<div class="flex h-screen bg-gray-50">
    <!-- Admin Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex-shrink-0">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-gold rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-bread-slice text-brown"></i>
                </div>
                <span class="text-xl font-bold">Loaf'n Ladle</span>
            </div>
        </div>
        <nav class="px-3">
            <div class="pb-2 px-2 text-xs font-semibold text-white/60 uppercase tracking-wider">Dashboard</div>
            <a href="<?= pageUrl('admin') ?>" class="sidebar-link <?= $page === 'admin' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line w-4"></i> Overview
            </a>
            <div class="pt-2 pb-1 px-2 text-xs font-semibold text-white/60 uppercase tracking-wider">Management</div>
            <a href="<?= pageUrl('admin.products') ?>" class="sidebar-link <?= $page === 'admin.products' ? 'active' : '' ?>">
                <i class="fa-solid fa-box w-4"></i> Products
            </a>
            <a href="<?= pageUrl('admin.categories') ?>" class="sidebar-link <?= $page === 'admin.categories' ? 'active' : '' ?>">
                <i class="fa-solid fa-tags w-4"></i> Categories
            </a>
            <a href="<?= pageUrl('admin.orders') ?>" class="sidebar-link <?= $page === 'admin.orders' ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt w-4"></i> Orders
            </a>
            <a href="<?= pageUrl('admin.subscriptions') ?>" class="sidebar-link <?= $page === 'admin.subscriptions' ? 'active' : '' ?>">
                <i class="fa-solid fa-rotate w-4"></i> Subscriptions
            </a>
            <a href="<?= pageUrl('admin.customers') ?>" class="sidebar-link <?= $page === 'admin.customers' ? 'active' : '' ?>">
                <i class="fa-solid fa-users w-4"></i> Customers
            </a>
            <a href="<?= pageUrl('admin.bake_list') ?>" class="sidebar-link <?= $page === 'admin.bake_list' ? 'active' : '' ?>">
                <i class="fa-solid fa-fire-burner w-4"></i> Bake List
            </a>
            <div class="pt-2 pb-1 px-2 text-xs font-semibold text-white/60 uppercase tracking-wider">Settings</div>
            <a href="<?= pageUrl('admin.settings') ?>" class="sidebar-link <?= $page === 'admin.settings' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear w-4"></i> General
            </a>
            <a href="<?= pageUrl('admin.stripe') ?>" class="sidebar-link <?= $page === 'admin.stripe' ? 'active' : '' ?>">
                <i class="fa-brands fa-stripe w-4"></i> Stripe API
            </a>
            <a href="<?= pageUrl('admin.plans') ?>" class="sidebar-link <?= $page === 'admin.plans' ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group w-4"></i> Sub Plans
            </a>
            <a href="<?= pageUrl('admin.page_management') ?>" class="sidebar-link <?= $page === 'admin.page_management' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-lines w-4"></i> Page Management
            </a>
        </nav>
        <div class="p-3 border-t border-white/10">
            <a href="<?= homeUrl() ?>" class="sidebar-link text-sm">
                <i class="fa-solid fa-store w-4"></i> View Store
            </a>
            <a href="<?= pageUrl('logout') ?>" class="sidebar-link text-sm text-red-400 hover:text-red-300">
                <i class="fa-solid fa-right-from-bracket w-4"></i> Logout
            </a>
        </div>
    </aside>
    <!-- Admin Main -->
    <main class="flex-1 bg-gray-50 overflow-auto">
        <div class="p-6 md:p-8 fade-in">
            <?= $flash ?>
            <?= $content ?>
        </div>
    </main>
</div>

<?php else: ?>
<!-- PUBLIC / CLIENT LAYOUT -->
    <header class="fixed top-0 w-full bg-white shadow-md z-50">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <?php 
                    $siteLogo = setting('site_logo');
                    if ($siteLogo && file_exists(APP_ROOT . '/uploads/' . $siteLogo)): 
                    ?>
                    <img src="<?= pageUrl('uploads', ['file' => $siteLogo]) ?>" alt="Loaf'n Ladle" class="h-8 object-contain">
                    <?php else: ?>
                    <i class="fas fa-bread-slice text-2xl text-brown"></i>
                    <?php endif; ?>
                    <span class="text-xl font-bold text-brown">Loaf'n Ladle</span>
                </div>
                <div class="hidden md:flex space-x-6">
                    <a href="<?= homeUrl() ?>" class="nav-link text-gray-700 hover:text-brown transition">Home</a>
                    <a href="<?= pageUrl('shop') ?>" class="nav-link text-gray-700 hover:text-brown transition <?= $page === 'shop' ? 'text-brown font-semibold' : '' ?>">Shop</a>
                    <a href="<?= pageUrl('subscriptions') ?>" class="nav-link text-gray-700 hover:text-brown transition <?= $page === 'subscriptions' ? 'text-brown font-semibold' : '' ?>">Loaf Club</a>
                    <a href="<?= pageUrl('catering') ?>" class="nav-link text-gray-700 hover:text-brown transition <?= $page === 'catering' ? 'text-brown font-semibold' : '' ?>">Catering</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= pageUrl('portal') ?>" class="nav-link text-gray-700 hover:text-brown transition <?= str_starts_with($page, 'portal') ? 'text-brown font-semibold' : '' ?>">My Account</a>
                        <?php if ($isAdminUser): ?>
                            <a href="<?= pageUrl('admin') ?>" class="nav-link text-purple-600 hover:text-purple-800 transition">Admin</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= pageUrl('login') ?>" class="nav-link text-gray-700 hover:text-brown transition">Login</a>
                        <a href="<?= pageUrl('register') ?>" class="nav-link text-gray-700 hover:text-brown transition">Register</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Cart -->
                    <a href="<?= pageUrl('cart') ?>" class="relative text-gray-700 hover:text-brown transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-yellow-400 text-brown text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($isLoggedIn): ?>
                    <a href="<?= pageUrl('portal') ?>" class="text-gray-700 hover:text-brown transition">
                        <i class="fas fa-user text-xl"></i>
                    </a>
                    <?php else: ?>
                    <a href="<?= pageUrl('login') ?>" class="text-gray-700 hover:text-brown transition">
                        <i class="fas fa-sign-in-alt text-xl"></i>
                    </a>
                    <?php endif; ?>
                    <button id="mobile-menu-btn" class="md:hidden">
                        <i class="fas fa-bars text-2xl text-gray-700"></i>
                    </button>
                </div>
            </div>
        </nav>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-6 py-4 space-y-3">
                <a href="<?= homeUrl() ?>" class="block text-gray-700 hover:text-brown">Home</a>
                <a href="<?= appUrl('index.php') ?>" class="block text-gray-700 hover:text-brown <?= $page === 'shop' ? 'text-brown font-semibold' : '' ?>">Shop</a>
                <a href="<?= pageUrl('subscriptions') ?>" class="block text-gray-700 hover:text-brown <?= $page === 'subscriptions' ? 'text-brown font-semibold' : '' ?>">Loaf Club</a>
                <a href="<?= pageUrl('catering') ?>" class="block text-gray-700 hover:text-brown <?= $page === 'catering' ? 'text-brown font-semibold' : '' ?>">Catering</a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= pageUrl('portal') ?>" class="block text-gray-700 hover:text-brown <?= str_starts_with($page, 'portal') ? 'text-brown font-semibold' : '' ?>">My Account</a>
                    <?php if ($isAdminUser): ?>
                        <a href="<?= pageUrl('admin') ?>" class="block text-purple-600 hover:text-purple-800">Admin</a>
                    <?php endif; ?>
                    <a href="<?= pageUrl('logout') ?>" class="block text-gray-700 hover:text-brown">Logout</a>
                <?php else: ?>
                    <a href="<?= pageUrl('login') ?>" class="block text-gray-700 hover:text-brown">Login</a>
                    <a href="<?= pageUrl('register') ?>" class="block text-gray-700 hover:text-brown">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-20">
        <?= $flash ?>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="fade-in <?= $showComingSoon ? 'blur-sm' : '' ?> bg-white min-h-screen">
        <div class="pt-20">
            <?= $content ?>
        </div>
    </main>

    <!-- Coming Soon Modal -->
    <?php if ($showComingSoon): ?>
    <div id="comingSoonModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md mx-4 text-center shadow-2xl">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid <?= setting('coming_soon_icon', 'fa-clock') ?> <?= setting('coming_soon_icon_color', 'text-yellow-500') ?> text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= e(setting('coming_soon_title', 'Coming Soon!')) ?></h2>
            <p class="text-gray-600 mb-6"><?= e(setting('coming_soon_message', 'This page is currently under construction. We\'re working hard to bring you something amazing!')) ?></p>
            <div class="space-y-3">
                <?php if (setting('coming_soon_show_loaf_club_button', '1')): ?>
                <a href="<?= pageUrl('subscriptions') ?>" class="btn-primary w-full block">
                    <i class="fa-solid fa-crown mr-2"></i><?= e(setting('coming_soon_button_loaf_club', 'Join the Loaf Club')) ?>
                </a>
                <?php endif; ?>
                <a href="<?= homeUrl() ?>" class="btn-outline w-full block">
                    <i class="fa-solid fa-home mr-2"></i><?= e(setting('coming_soon_button_home', 'Back to Home')) ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer (EXACTLY like homepage) -->
    <footer class="bg-cream border-t border-gray-200 py-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="md:col-span-2">
                    <div class="flex items-center space-x-2 mb-4">
                        <i class="fas fa-bread-slice text-2xl text-brown"></i>
                        <span class="text-xl font-bold text-brown">Loaf'n Ladle</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Your local artisan bakery serving fresh breads, pastries, and gourmet meals with love and tradition.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="bg-gray-200 w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-300 transition">
                            <i class="fab fa-facebook-f text-gray-700"></i>
                        </a>
                        <a href="#" class="bg-gray-200 w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-300 transition">
                            <i class="fab fa-instagram text-gray-700"></i>
                        </a>
                        <a href="#" class="bg-gray-200 w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-300 transition">
                            <i class="fab fa-twitter text-gray-700"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4 text-gray-800">Quick Links</h4>
                    <ul class="space-y-2 text-gray-600">
                        <li><a href="<?= pageUrl('shop') ?>" class="hover:text-brown transition">Shop</a></li>
                        <li><a href="<?= pageUrl('subscriptions') ?>" class="hover:text-brown transition">Loaf Club</a></li>
                        <li><a href="<?= pageUrl('catering') ?>" class="hover:text-brown transition">Catering</a></li>
                        <?php if ($isLoggedIn): ?>
                        <li><a href="<?= pageUrl('portal') ?>" class="hover:text-brown transition">Client Portal</a></li>
                        <?php else: ?>
                        <li><a href="<?= pageUrl('login') ?>" class="hover:text-brown transition">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4 text-gray-800">Business Hours</h4>
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li>Monday - Friday: 7am - 7pm</li>
                        <li>Saturday: 8am - 5pm</li>
                        <li>Sunday: 8am - 5pm</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-300 mt-8 pt-8 text-center text-gray-500">
                <p>&copy; <?= date('Y') ?> Website and Application designed & developed by <a href="https://www.hyndsyte.com" class="hover:text-brown">HyndSyte.com</a> - Loaf'n Ladle LLC. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle (exactly like homepage)
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Smooth scrolling for navigation links (exactly like homepage)
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php endif; ?>
<?php
}
?>
