<?php
// Include PHP app functionality for cart count, auth, etc.
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

Auth::startSession();

// Get cart count for header
$cartCount = cartCount();
$isLoggedIn = Auth::isLoggedIn();
$isAdminUser = Auth::isAdmin();
$currentUser = $isLoggedIn ? Auth::currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loaf'n Ladle - Bakery & Cafe | Mountain Top, PA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html {
            scroll-behavior: smooth;
        }
        .hero-bg {
            background: linear-gradient(135deg, rgba(245, 230, 211, 0.7) 0%, rgba(212, 165, 116, 0.7) 100%),
                        url('https://www.loafnladle.com/images/3.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .hero-bg-club {
            background: linear-gradient(135deg, rgba(245, 230, 211, 0.7) 0%, rgba(212, 165, 116, 0.7) 100%),
                        url('https://www.loafnladle.com/images/2.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .text-brown {
            color: #8b4513;
        }
        .bg-brown {
            background-color: #8b4513;
        }
        .bg-cream {
            background-color: #faf8f3;
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
    </style>
</head>
<body class="bg-cream">
    <!-- Navigation Header -->
    <header class="fixed top-0 w-full bg-white shadow-md z-50">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <?php 
                    $siteLogo = setting('site_logo');
                    if ($siteLogo && file_exists(__DIR__ . '/app/uploads/' . $siteLogo)): 
                    ?>
                    <img src="<?= appUrl('uploads/' . $siteLogo) ?>" alt="Loaf'n Ladle" class="h-8 object-contain">
                    <?php else: ?>
                    <i class="fas fa-bread-slice text-2xl text-brown"></i>
                    <?php endif; ?>
                    <span class="text-xl font-bold text-brown">Loaf'n Ladle</span>
                </div>
                <div class="hidden md:flex space-x-6">
                    <a href="#home" class="nav-link text-gray-700 hover:text-brown transition">Home</a>
                    <a href="#about" class="nav-link text-gray-700 hover:text-brown transition">About</a>
                    <a href="#bread-subscription" class="nav-link text-gray-700 hover:text-brown transition">Loaf Club</a>
                    <a href="#products" class="nav-link text-gray-700 hover:text-brown transition">Products</a>
                    <a href="#menu" class="nav-link text-gray-700 hover:text-brown transition">Menu</a>
                    <a href="<?= appUrl('app/index.php?page=catering') ?>" class="nav-link text-gray-700 hover:text-brown transition">Catering</a>
                    <a href="#contact" class="nav-link text-gray-700 hover:text-brown transition">Contact</a>
                    <?php if ($isAdminUser): ?>
                    <a href="<?= appUrl('app/index.php?page=admin') ?>" class="nav-link text-purple-600 hover:text-purple-800 transition">Admin</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Cart -->
                    <a href="<?= appUrl('app/index.php?page=cart') ?>" class="relative text-gray-700 hover:text-brown transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-yellow-400 text-brown text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($isLoggedIn): ?>
                    <a href="<?= appUrl('app/index.php?page=portal') ?>" class="text-gray-700 hover:text-brown transition">
                        <i class="fas fa-user text-xl"></i>
                    </a>
                    <?php else: ?>
                    <a href="<?= appUrl('app/index.php?page=login') ?>" class="text-gray-700 hover:text-brown transition">
                        <i class="fas fa-sign-in-alt text-xl"></i>
                    </a>
                    <?php endif; ?>
                    <button id="mobile-menu-btn" class="md:hidden">
                        <i class="fas fa-bars text-2xl text-brown"></i>
                    </button>
                </div>
            </div>
        </nav>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-6 py-4 space-y-3">
                <a href="#home" class="block text-gray-700 hover:text-brown">Home</a>
                <a href="#about" class="block text-gray-700 hover:text-brown">About</a>
                <a href="#bread-subscription" class="block text-gray-700 hover:text-brown">Loaf Club</a>
                <a href="#products" class="block text-gray-700 hover:text-brown">Products</a>
                <a href="#menu" class="block text-gray-700 hover:text-brown">Menu</a>
                <a href="<?= appUrl('app/index.php?page=catering') ?>" class="block text-gray-700 hover:text-brown">Catering</a>
                <a href="#contact" class="block text-gray-700 hover:text-brown">Contact</a>
                <?php if ($isLoggedIn): ?>
                <a href="<?= appUrl('app/index.php?page=portal') ?>" class="block text-gray-700">My Account</a>
                <?php else: ?>
                <a href="<?= appUrl('app/index.php?page=login') ?>" class="block text-gray-700">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero-bg pt-32">
        <div class="container mx-auto px-6">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold text-white mb-6">
                    Loaf'n Ladle - Bakery & Cafe
                </h1>
                <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto font-bold">
                    COMING SOON TO MOUNTAIN TOP, PA
                </p>
                <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
                    Artisan Breads, Fresh Pastries & Gourmet Lunch
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#products" class="bg-white text-brown px-8 py-3 rounded-full font-semibold hover:bg-gray-100 transition">
                        Explore Our Products
                    </a>
                    <a href="#bread-subscription" class="border-2 border-white text-white px-8 py-3 rounded-full font-semibold hover:bg-white hover:text-brown transition">
                        Order Now
                    </a>
                    <a href="#contact" class="border-2 border-white text-white px-8 py-3 rounded-full font-semibold hover:bg-white hover:text-brown transition">
                        Get Updates
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section-padding bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-brown mb-4">About Loaf'n Ladle</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Bringing artisanal baking and gourmet dining to Mountain Top, PA
                </p>
            </div>
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="text-center">
                    <h3 class="text-2xl font-semibold text-brown mb-6">Our Mission</h3>
                    <p class="text-gray-600 mb-6">
                        To provide fresh, high-quality baked goods and convenient lunch options that serve our community by creating exceptional artisan breads, baked goods, soups, salads, sandwiches, and lattes, using traditional techniques and premium ingredients while building a sustainable community-focused business from the ground up, emphasizing local ingredients and artisanal quality in a warm inviting atmosphere.
                    </p>
                    <p class="text-gray-600 mb-6">
 
                    </p>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-brown mb-2">48-Hour</div>
                            <div class="text-gray-600">Sourdough Fermentation</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-brown mb-2">100%</div>
                            <div class="text-gray-600">Local Ingredients</div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div class="bg-cream p-6 rounded-lg text-center card-hover">
                        <i class="fas fa-bread-slice text-3xl text-brown mb-4"></i>
                        <h4 class="font-semibold text-brown mb-2">Artisan Breads</h4>
                        <p class="text-gray-600 text-sm">Traditional techniques, modern flavors</p>
                    </div>
                    <div class="bg-cream p-6 rounded-lg text-center card-hover">
                        <i class="fas fa-coffee text-3xl text-brown mb-4"></i>
                        <h4 class="font-semibold text-brown mb-2">Gourmet Coffee</h4>
                        <p class="text-gray-600 text-sm">Premium espresso drinks & more</p>
                    </div>
                    <div class="bg-cream p-6 rounded-lg text-center card-hover">
                        <i class="fas fa-utensils text-3xl text-brown mb-4"></i>
                        <h4 class="font-semibold text-brown mb-2">Fresh Lunch</h4>
                        <p class="text-gray-600 text-sm">Sandwiches, salads & soups</p>
                    </div>
                    <div class="bg-cream p-6 rounded-lg text-center card-hover">
                        <i class="fas fa-birthday-cake text-3xl text-brown mb-4"></i>
                        <h4 class="font-semibold text-brown mb-2">Custom Cakes</h4>
                        <p class="text-gray-600 text-sm">Celebrations made special</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="section-padding bg-cream">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-brown mb-4">Our Products</h2>
                                <p class="text-gray-600 max-w-2xl mx-auto">
                    (LIMITED MENU AVAILABLE NOW)
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Handcrafted with love using the finest ingredients
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8 mb-12">
                <div class="bg-white p-8 rounded-lg shadow-lg card-hover">
                    <div class="text-center mb-6">
                        <i class="fas fa-bread-slice text-4xl text-brown mb-4"></i>
                        <h3 class="text-2xl font-semibold text-brown mb-3">Artisan Breads</h3>
                    </div>
                    <ul class="space-y-2 text-gray-600">
                        <li>• Sourdough Boule (48-hour fermentation)</li>
                        <li>• French Baguette</li>
                        <li>• Ciabatta Loaf</li>
                        <li>• Focaccia (Herbed Italian)</li>
                        <li>• Brioche Loaf</li>
                        <li>• Fresh Bagels (Plain, Everything, Sesame)</li>
                    </ul>
                    <div class="text-center mt-6">
                        <span class="text-brown font-semibold">Starting at $5.99</span>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-lg shadow-lg card-hover">
                    <div class="text-center mb-6">
                        <i class="fas fa-cookie text-4xl text-brown mb-4"></i>
                        <h3 class="text-2xl font-semibold text-brown mb-3">Pastries & Treats</h3>
                    </div>
                    <ul class="space-y-2 text-gray-600">
                        <li>• Chocolate Croissant</li>
                        <li>• Almond Croissant</li>
                        <li>• Blueberry Muffins</li>
                        <li>• Cinnamon Rolls</li>
                        <li>• Apple Turnover</li>
                        <li>• Pain au Chocolat</li>
                    </ul>
                    <div class="text-center mt-6">
                        <span class="text-brown font-semibold">Starting at $4.49</span>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-lg shadow-lg card-hover">
                    <div class="text-center mb-6">
                        <i class="fas fa-coffee text-4xl text-brown mb-4"></i>
                        <h3 class="text-2xl font-semibold text-brown mb-3">Beverages</h3>
                    </div>
                    <ul class="space-y-2 text-gray-600">
                        <li>• Caramel Latte</li>
                        <li>• Cinnamon Crunch Latte</li>
                        <li>• Frozen Chocolate Cold Brew</li>
                        <li>• Green Passion Smoothie</li>
                        <li>• Iced Green Tea</li>
                        <li>• Fresh Brewed Coffee</li>
                    </ul>
                    <div class="text-center mt-6">
                        <span class="text-brown font-semibold">Starting at $3.99</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section id="menu" class="section-padding bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-brown mb-4">Lunch Menu</h2>
                                <p class="text-gray-600 max-w-2xl mx-auto">
                    (COMING SOON!)
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Fresh, handmade sandwiches, salads, and soups perfect for lunch
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <div class="bg-cream p-8 rounded-lg">
                    <h3 class="text-2xl font-semibold text-brown mb-6">Signature Sandwiches</h3>
                    <div class="space-y-4">
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Bacon Turkey Bravo</h4>
                            <p class="text-gray-600 text-sm">Turkey, bacon, cheese on focaccia with greens and tomato</p>
                            <span class="text-brown font-semibold">$9.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Smokehouse BBQ Chicken</h4>
                            <p class="text-gray-600 text-sm">Smoked chicken with BBQ sauce on miche bread</p>
                            <span class="text-brown font-semibold">$10.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Mediterranean Veggie</h4>
                            <p class="text-gray-600 text-sm">Veggies, hummus, feta on ciabatta</p>
                            <span class="text-brown font-semibold">$8.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Chipotle Chicken Avocado Melt</h4>
                            <p class="text-gray-600 text-sm">Pulled chicken, avocado, gouda on toasted bread</p>
                            <span class="text-brown font-semibold">$11.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Italian on Soft Ciabatta</h4>
                            <p class="text-gray-600 text-sm">Classic Italian meats and cheeses on soft ciabatta</p>
                            <span class="text-brown font-semibold">$10.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Reuben</h4>
                            <p class="text-gray-600 text-sm">Corned beef, melted Swiss, sauerkraut, and Thousand Island dressing on thick-sliced grilled rye</p>
                            <span class="text-brown font-semibold">$11.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Chicken Parmesan</h4>
                            <p class="text-gray-600 text-sm">Breaded chicken, house-made pomodoro sauce, melted mozzarella, on fresh-baked ciabatta</p>
                            <span class="text-brown font-semibold">$12.99</span>
                        </div>
                        <div>
                            <h4 class="font-semibold text-brown">T-BIRD</h4>
                            <p class="text-gray-600 text-sm">Roasted turkey breast with stuffing, cranberry sauce, melted cheddar, and mayo on fresh-baked ciabatta</p>
                            <span class="text-brown font-semibold">$10.99</span>
                        </div>
                    </div>
                </div>

                <div class="bg-cream p-8 rounded-lg">
                    <h3 class="text-2xl font-semibold text-brown mb-6">Soups & Salads</h3>
                    <div class="space-y-4">
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Fuji Apple Salad with Chicken</h4>
                            <p class="text-gray-600 text-sm">Chicken, apples, greens, goat cheese</p>
                            <span class="text-brown font-semibold">$9.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Turkey Chili</h4>
                            <p class="text-gray-600 text-sm">Hearty turkey chili with beans and veggies</p>
                            <span class="text-brown font-semibold">$7.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Bistro French Onion Soup</h4>
                            <p class="text-gray-600 text-sm">Classic onion soup with cheese</p>
                            <span class="text-brown font-semibold">$6.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Cheddar Broccoli Soup</h4>
                            <p class="text-gray-600 text-sm">Creamy cheddar broccoli soup with fresh herbs</p>
                            <span class="text-brown font-semibold">$7.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Chicken Noodle Soup</h4>
                            <p class="text-gray-600 text-sm">Homemade noodles and local vegetables in rich chicken broth</p>
                            <span class="text-brown font-semibold">$7.99</span>
                        </div>
                        <div class="border-b pb-4">
                            <h4 class="font-semibold text-brown">Italian Wedding Soup</h4>
                            <p class="text-gray-600 text-sm">Traditional Italian soup with meatballs and greens</p>
                            <span class="text-brown font-semibold">$7.99</span>
                        </div>
                        <div>
                            <h4 class="font-semibold text-brown">Greek Salad</h4>
                            <p class="text-gray-600 text-sm">Feta, olives, cucumber, tomato, greens</p>
                            <span class="text-brown font-semibold">$8.99</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Loaf Club Section -->
    <section id="bread-subscription" class="section-padding">
        <div class="hero-bg-club pt-32">
            <div class="container mx-auto px-6">
                <div class="text-center mb-12">
                    <h2 class="text-5xl md:text-6xl font-bold text-white mb-6">Loaf Club</h2>
                    <div class="inline-block bg-yellow-100 border-2 border-yellow-400 rounded-lg px-4 py-2 mb-6">
                        <span class="text-yellow-800 font-semibold text-sm">PRE-GRAND OPENING!</span>
                    </div>
                    <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
                        Exclusive Club! <br> Artisan bread, soups and treats delivered fresh to your door.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#bread-subscription-details" class="bg-white text-brown px-8 py-3 rounded-full font-semibold hover:bg-gray-100 transition">
                            Learn More
                        </a>
                        <a href="/app/index.php?page=subscriptions" class="border-2 border-white text-white px-8 py-3 rounded-full font-semibold hover:bg-white hover:text-brown transition">
                            Order Now!
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="bread-subscription-details" class="bg-cream">
            <div class="container mx-auto px-6 py-16">
                <div class="max-w-4xl mx-auto">
                    <div class="bg-white p-8 rounded-lg shadow-lg text-center">
                        <div class="mb-6">
                            <i class="fas fa-bread-slice text-6xl text-brown mb-4"></i>
                            <h3 class="text-2xl font-semibold text-brown mb-4">Available Pre-Grand Opening!</h3>
                            <p class="text-lg text-gray-600 mb-6">
                                Loaf Club is now! Order one time delivery or subscribe for weekly fresh bread.
                            </p>
                        </div>
                        
                        <div class="grid md:grid-cols-3 gap-6 mb-8">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-brown/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-calendar-alt text-2xl text-brown"></i>
                                </div>
                                <h4 class="font-semibold text-brown mb-2">Scheduled Delivery</h4>
                                <p class="text-sm text-gray-600">Fresh bread delivered to your door weekly, bi-weekly, or monthly</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-brown/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-leaf text-2xl text-brown"></i>
                                </div>
                                <h4 class="font-semibold text-brown mb-2">Artisan Quality</h4>
                                <p class="text-sm text-gray-600">Handcrafted with premium ingredients daily. Fresh milled flour from our own mill.</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-brown/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-users text-2xl text-brown"></i>
                                </div>
                                <h4 class="font-semibold text-brown mb-2">Exclusive Access</h4>
                                <p class="text-sm text-gray-600">Be first to taste and vote on our creations. Your input will help shape our final menu</p>
                            </div>
                        </div>
                        
                        <div class="bg-cream p-6 rounded-lg">
                            <p class="text-gray-700 mb-6">
                                <strong>What is it?</strong><p></p> The Loaf'n Ladle Loaf Club is a subscription service or one time order of fresh, artisanal bread, baked goods and soups delivered to your doorstep! We are currently offering a limited menu - and plan to grow our selection over time to include a variety of breads and baked goods, including fresh bread, artisanal bread, and a selection of sweets and pastries. 
                            </p>
                            <a href="/app/index.php?page=subscriptions" class="bg-brown text-white px-10 py-4 rounded-full font-semibold hover:bg-brown/80 transition inline-block mt-4">
                                Order Now!
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Catering Section -->
    <section id="catering" class="section-padding bg-cream">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-brown mb-4">Catering Services</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Perfect for corporate events, meetings, and special occasions
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-lg text-center card-hover">
                    <i class="fas fa-briefcase text-3xl text-brown mb-4"></i>
                    <h3 class="text-xl font-semibold text-brown mb-3">Corporate Catering</h3>
                    <p class="text-gray-600 mb-4">Daily lunch delivery for Mountain Top Business Park</p>
                    <ul class="text-sm text-gray-600 text-left">
                        <li>• Sandwich platters</li>
                        <li>• Salad boxes</li>
                        <li>• Soup containers</li>
                        <li>• Pastry assortments</li>
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg text-center card-hover">
                    <i class="fas fa-birthday-cake text-3xl text-brown mb-4"></i>
                    <h3 class="text-xl font-semibold text-brown mb-3">Event Catering</h3>
                    <p class="text-gray-600 mb-4">Custom cakes and desserts for celebrations</p>
                    <ul class="text-sm text-gray-600 text-left">
                        <li>• Custom cakes</li>
                        <li>• Dessert platters</li>
                        <li>• Pastry boxes</li>
                        <li>• Beverage service</li>
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg text-center card-hover">
                    <i class="fas fa-truck text-3xl text-brown mb-4"></i>
                    <h3 class="text-xl font-semibold text-brown mb-3">Delivery Service</h3>
                    <p class="text-gray-600 mb-4">Convenient delivery to your location</p>
                    <ul class="text-sm text-gray-600 text-left">
                        <li>• Free local delivery</li>
                        <li>• Scheduled orders</li>
                        <li>• Bulk ordering</li>
                        <li>• Regular subscriptions</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section-padding bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-brown mb-4">Get in Touch</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    We're coming soon! Sign up for updates and be the first to know when we open.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-2xl font-semibold text-brown mb-6">Contact Information</h3>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-envelope text-brown text-xl w-6"></i>
                            <div>
                                <div class="font-semibold text-brown">Email</div>
                                <div class="text-gray-600">info@loafnladle.com</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-map-marker-alt text-brown text-xl w-6"></i>
                            <div>
                                <div class="font-semibold text-brown">Address</div>
                                <div class="text-gray-600">Coming Soon - Mountain Top, PA</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-phone text-brown text-xl w-6"></i>
                            <div>
                                <div class="font-semibold text-brown">Phone</div>
                                <div class="text-gray-600">Coming Soon</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-mailbox text-brown text-xl w-6"></i>
                            <div>
                                <div class="font-semibold text-brown">Mail</div>
                                <div class="text-gray-600">PO Box 45, Mountain Top, PA 18707</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h4 class="font-semibold text-brown mb-4">Follow Us</h4>
                        <div class="flex space-x-4">
                            <a href="#" class="bg-brown text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-brown/80 transition">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="bg-brown text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-brown/80 transition">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="bg-brown text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-brown/80 transition">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-2xl font-semibold text-brown mb-6">Stay Updated</h3>
                    <form id="subscribeForm" class="space-y-4" method="POST" action="save-submission.php">
                        <div>
                            <label class="block text-gray-700 mb-2">Name</label>
                            <input type="text" name="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brown" placeholder="Your Name" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brown" placeholder="your@email.com" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">I'm interested in:</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="interests[]" value="grand_opening" class="mr-2">
                                    <span class="text-gray-700">Grand opening announcements</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="interests[]" value="daily_specials" class="mr-2">
                                    <span class="text-gray-700">Daily specials and promotions</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="interests[]" value="catering" class="mr-2">
                                    <span class="text-gray-700">Catering services</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="interests[]" value="premium_loaf_club" class="mr-2">
                                    <span class="text-gray-700">Loaf Club</span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gray-800 text-white py-3 rounded-lg font-semibold hover:bg-gray-900 transition">
                            Subscribe for Updates
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-100 text-gray-800 py-8">
        <div class="container mx-auto px-6">
            <div class="text-center">
                <div class="flex items-center justify-center space-x-2 mb-4">
                    <i class="fas fa-bread-slice text-2xl text-brown"></i>
                    <span class="text-xl font-bold">Loaf'n Ladle - Bakery & Cafe</span>
                </div>
                
                <div class="mb-6">
                    <a href="https://www.loafnladle.com/investors/" class="text-3xl font-bold text-brown hover:text-brown/80 transition">
                        Investor Information
                    </a>
                </div>
                
                <p class="mb-4">Coming Soon to Mountain Top, PA</p>
                <p class="text-sm opacity-75">© 2024 Loaf'n Ladle - Bakery & Cafe. All rights reserved.</p>
                <p class="text-sm opacity-75 mt-2">PO Box 45, Mountain Top, PA 18707</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    document.getElementById('mobile-menu').classList.add('hidden');
                }
            });
        });

        // Form submission
        document.getElementById('subscribeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            // Show loading state
            submitButton.textContent = 'Subscribing...';
            submitButton.disabled = true;
            
            // Send form data
            fetch('save-submission.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    this.reset();
                } else {
                    alert('Error: ' + (data.error || 'Something went wrong'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: Something went wrong. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            });
        });
    </script>
</body>
</html>
