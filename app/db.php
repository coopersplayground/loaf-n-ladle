<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $this->pdo = new PDO('sqlite:' . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->initialize();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    private function initialize() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'client',
                phone TEXT,
                address TEXT,
                stripe_customer_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                icon TEXT DEFAULT 'fa-bread-slice',
                sort_order INTEGER DEFAULT 0,
                active INTEGER DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                cost REAL DEFAULT 0,
                image TEXT,
                stripe_price_id TEXT,
                available INTEGER DEFAULT 1,
                featured INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );

            CREATE TABLE IF NOT EXISTS subscription_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                loaves_per_week INTEGER NOT NULL,
                price_per_week REAL NOT NULL,
                stripe_price_id TEXT,
                stripe_product_id TEXT,
                features TEXT,
                active INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS subscription_plan_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                max_selections INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );

            CREATE TABLE IF NOT EXISTS subscription_plan_item_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_item_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (plan_item_id) REFERENCES subscription_plan_items(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                UNIQUE(plan_item_id, product_id)
            );

            CREATE TABLE IF NOT EXISTS page_management (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_key TEXT UNIQUE NOT NULL,
                page_name TEXT NOT NULL,
                page_url TEXT NOT NULL,
                is_enabled INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                order_number TEXT UNIQUE NOT NULL,
                status TEXT DEFAULT 'pending',
                subtotal REAL NOT NULL,
                tax REAL DEFAULT 0,
                total REAL NOT NULL,
                notes TEXT,
                stripe_payment_intent_id TEXT,
                stripe_session_id TEXT,
                delivery_name TEXT,
                delivery_email TEXT,
                delivery_phone TEXT,
                delivery_address TEXT,
                gift_loaf INTEGER DEFAULT 0,
                gift_recipient_name TEXT,
                gift_recipient_address TEXT,
                gift_note TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER,
                product_name TEXT NOT NULL,
                product_price REAL NOT NULL,
                quantity INTEGER NOT NULL,
                subtotal REAL NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            );

            CREATE TABLE IF NOT EXISTS delivery_days (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                day_name TEXT NOT NULL,
                is_available INTEGER DEFAULT 1,
                max_deliveries INTEGER DEFAULT 10,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS subscription_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subscription_id INTEGER NOT NULL,
                delivery_day_id INTEGER NOT NULL,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
                FOREIGN KEY (delivery_day_id) REFERENCES delivery_days(id)
            );

            CREATE TABLE IF NOT EXISTS subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                plan_id INTEGER NOT NULL,
                status TEXT DEFAULT 'active',
                stripe_subscription_id TEXT,
                stripe_customer_id TEXT,
                stripe_session_id TEXT,
                delivery_day_id INTEGER,
                current_period_start DATETIME,
                current_period_end DATETIME,
                bread_selections TEXT,
                delivery_address TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
            );

            CREATE TABLE IF NOT EXISTS pending_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stripe_session_id TEXT UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                plan_id INTEGER NOT NULL,
                bread_selections TEXT,
                delivery_address TEXT,
                notes TEXT,
                frequency TEXT DEFAULT 'weekly',
                delivery_day_id INTEGER,
                gift_loaf INTEGER DEFAULT 0,
                gift_recipient_name TEXT,
                gift_recipient_address TEXT,
                gift_note TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                used INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Migrate: add gift loaf columns to orders if not present
        $cols = $this->fetchAll("PRAGMA table_info(orders)");
        $colNames = array_column($cols, 'name');
        if (!in_array('gift_loaf', $colNames)) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN gift_loaf INTEGER DEFAULT 0");
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN gift_recipient_name TEXT");
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN gift_recipient_address TEXT");
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN gift_note TEXT");
        }

        // Migrate: add paid_via_stripe column to orders if not present
        if (!in_array('paid_via_stripe', $colNames)) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN paid_via_stripe INTEGER DEFAULT 0");
        }

        // Migrate: add daily_order_number column to orders if not present
        if (!in_array('daily_order_number', $colNames)) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN daily_order_number INTEGER DEFAULT NULL");
        }

        // Backfill daily_order_number for any orders that don't have one yet
        $unassigned = $this->fetchAll(
            "SELECT id, DATE(created_at) as order_date FROM orders WHERE daily_order_number IS NULL ORDER BY created_at ASC"
        );
        if (!empty($unassigned)) {
            $dailyCounters = [];
            foreach ($unassigned as $row) {
                $d = $row['order_date'];
                if (!isset($dailyCounters[$d])) {
                    $existing = $this->fetchOne(
                        "SELECT MAX(daily_order_number) as max_num FROM orders WHERE DATE(created_at) = ? AND daily_order_number IS NOT NULL",
                        [$d]
                    );
                    $dailyCounters[$d] = (int)($existing['max_num'] ?? 0);
                }
                $dailyCounters[$d] = ($dailyCounters[$d] % 999) + 1;
                $this->pdo->exec("UPDATE orders SET daily_order_number = {$dailyCounters[$d]} WHERE id = {$row['id']}");
            }
        }

        // Always backfill: any order with a Stripe session or payment intent is an online order
        $this->pdo->exec("UPDATE orders SET paid_via_stripe = 1 WHERE paid_via_stripe = 0 AND ((stripe_session_id IS NOT NULL AND stripe_session_id != '') OR (stripe_payment_intent_id IS NOT NULL AND stripe_payment_intent_id != ''))");

        // Migrate: remap old order statuses to new status names
        $this->pdo->exec("UPDATE orders SET status = 'ordered'   WHERE status = 'pending'");
        $this->pdo->exec("UPDATE orders SET status = 'preparing' WHERE status = 'processing'");
        $this->pdo->exec("UPDATE orders SET status = 'pickup'    WHERE status = 'ready'");

        // Migrate: add stripe_session_id and delivery_day_id to subscriptions if not present
        $subCols = $this->fetchAll("PRAGMA table_info(subscriptions)");
        $subColNames = array_column($subCols, 'name');
        if (!in_array('stripe_session_id', $subColNames)) {
            $this->pdo->exec("ALTER TABLE subscriptions ADD COLUMN stripe_session_id TEXT");
        }
        if (!in_array('delivery_day_id', $subColNames)) {
            $this->pdo->exec("ALTER TABLE subscriptions ADD COLUMN delivery_day_id INTEGER");
        }

        $this->seedDefaults();
    }

    private function seedDefaults() {
        // Seed admin user if none exists
        $admin = $this->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if (!$admin) {
            $hash = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $this->insert(
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')",
                ["Admin User", ADMIN_EMAIL, $hash]
            );
        }

        // Seed default settings
        $defaults = [
            'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
            'stripe_secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
            'stripe_webhook_secret' => STRIPE_WEBHOOK_SECRET,
            'tax_rate' => '0.08',
            'delivery_radius_miles' => '20',
            'store_email' => 'hello@loafnladle.com',
            'store_phone' => '(555) 123-4567',
            'store_address' => '123 Artisan Way, Local Town, ST 12345',
            'store_hours' => 'Mon-Fri: 7am-7pm | Sat-Sun: 8am-5pm',
        ];
        foreach ($defaults as $key => $value) {
            $this->execute(
                "INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)",
                [$key, $value]
            );
        }

        // Seed categories
        $catCount = $this->fetchOne("SELECT COUNT(*) as c FROM categories")['c'];
        if ($catCount == 0) {
            $cats = [
                ['Breads', 'breads', 'Fresh artisanal breads baked daily', 'fa-bread-slice', 1],
                ['Pastries', 'pastries', 'Sweet and savory pastries baked fresh daily', 'fa-cake-candles', 2],
                ['Sandwiches', 'sandwiches', 'Handcrafted sandwiches on our house-baked bread', 'fa-sandwich', 3],
                ['Salads', 'salads', 'Fresh, vibrant salads made with local ingredients', 'fa-leaf', 4],
                ['Soups & Bowls', 'soups-bowls', 'Hearty soups and nourishing bowls', 'fa-bowl-food', 5],
                ['Beverages', 'beverages', 'Specialty coffees, teas, and smoothies', 'fa-mug-hot', 6],
                ['Desserts & Sides', 'desserts-sides', 'Sweet treats and sides to complete your meal', 'fa-cake-candles', 7],
                ['Catering', 'catering', 'Full-service catering for events and gatherings', 'fa-utensils', 8],
            ];
            foreach ($cats as $c) {
                $this->insert(
                    "INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?,?,?,?,?)",
                    $c
                );
            }
        }

        // Seed products
        $prodCount = $this->fetchOne("SELECT COUNT(*) as c FROM products")['c'];
        if ($prodCount == 0) {
            $this->seedProducts();
        }

        // Seed subscription plans
        $planCount = $this->fetchOne("SELECT COUNT(*) as c FROM subscription_plans")['c'];
        if ($planCount == 0) {
            $plans = [
                ['Basic Plan', 'basic', '2 loaves per week — Choose from sourdough, French baguette, or ciabatta', 2, 15.00, '["2 loaves/week","Free delivery within 20 miles","Choose your breads","Cancel anytime"]', 1, 1],
                ['Gourmet Plan', 'gourmet', '3 loaves per week — Includes specialty breads like focaccia, brioche, and herb bread', 3, 22.00, '["3 loaves/week","Free delivery within 20 miles","Specialty bread selection","Cancel anytime"]', 1, 2],
                ['Family Plan', 'family', '4 loaves per week — Mix of all available breads', 4, 28.00, '["4 loaves/week","Free delivery within 20 miles","Full bread variety","Priority scheduling","Cancel anytime"]', 1, 3],
                ['Premium Plan', 'premium', '5 loaves per week — Custom bread options and pastries included', 5, 35.00, '["5 loaves/week","Free delivery within 20 miles","Custom bread requests","Monthly pastry box","Priority scheduling","Cancel anytime"]', 1, 4],
            ];
            foreach ($plans as $p) {
                $this->insert(
                    "INSERT INTO subscription_plans (name, slug, description, loaves_per_week, price_per_week, features, active, sort_order) VALUES (?,?,?,?,?,?,?,?)",
                    $p
                );
            }
        }

        // Seed delivery days
        $dayCount = $this->fetchOne("SELECT COUNT(*) as c FROM delivery_days")['c'];
        if ($dayCount == 0) {
            $days = [
                ['Monday', 1, 10, 1],
                ['Tuesday', 1, 10, 2],
                ['Wednesday', 1, 10, 3],
                ['Thursday', 1, 10, 4],
                ['Friday', 1, 10, 5],
                ['Saturday', 1, 10, 6],
                ['Sunday', 0, 10, 7],
            ];
            foreach ($days as $d) {
                $this->insert(
                    "INSERT INTO delivery_days (day_name, is_available, max_deliveries, sort_order) VALUES (?,?,?,?)",
                    $d
                );
            }
        }

        // Seed page management
        $pageCount = $this->fetchOne("SELECT COUNT(*) as c FROM page_management")['c'];
        if ($pageCount == 0) {
            $pages = [
                ['home', 'Home', 'home', 1, 1],
                ['shop', 'Shop', 'shop', 1, 2],
                ['subscriptions', 'PL Club', 'subscriptions', 1, 3],
                ['catering', 'Catering', 'catering', 1, 4],
                ['login', 'Login', 'login', 1, 5],
                ['register', 'Register', 'register', 1, 6],
                ['cart', 'Cart', 'cart', 1, 7],
                ['checkout', 'Checkout', 'checkout', 1, 8],
                ['portal', 'Client Portal', 'portal', 1, 9],
            ];
            foreach ($pages as $p) {
                $this->insert(
                    "INSERT INTO page_management (page_key, page_name, page_url, is_enabled, sort_order) VALUES (?,?,?,?,?)",
                    $p
                );
            }
        }
    }

    private function seedProducts() {
        $catMap = [];
        $cats = $this->fetchAll("SELECT id, slug FROM categories");
        foreach ($cats as $c) $catMap[$c['slug']] = $c['id'];

        $products = [
            // Breads
            [$catMap['breads'], 'Sourdough Boule', 'sourdough-boule', 'Artisanal sourdough bread with a crispy crust and chewy interior', 2.78, 1.85, 1],
            [$catMap['breads'], 'French Baguette', 'french-baguette', 'Crisp, traditional French bread baked fresh daily', 2.31, 1.54, 0],
            [$catMap['breads'], 'Ciabatta Loaf', 'ciabatta-loaf', 'Italian-style flatbread with an open crumb and crispy crust', 2.46, 1.64, 0],
            [$catMap['breads'], 'Focaccia', 'focaccia', 'Herbed Italian flatbread drizzled with olive oil', 3.20, 2.13, 0],
            [$catMap['breads'], 'Brioche Loaf', 'brioche-loaf', 'Sweet, buttery bread with a tender crumb', 3.05, 2.03, 0],
            [$catMap['breads'], 'Bagel', 'bagel', 'Hand-rolled bagels — plain, everything, or sesame', 1.25, 0.83, 0],
            // Pastries
            [$catMap['pastries'], 'Chocolate Croissant', 'chocolate-croissant', 'Buttery, flaky pastry filled with rich dark chocolate', 2.10, 1.40, 1],
            [$catMap['pastries'], 'Almond Croissant', 'almond-croissant', 'Flaky pastry filled with sweet almond cream and topped with sliced almonds', 2.25, 1.50, 0],
            [$catMap['pastries'], 'Blueberry Muffin', 'blueberry-muffin', 'Fresh baked with local blueberries, moist and golden', 1.52, 1.01, 0],
            [$catMap['pastries'], 'Cinnamon Roll', 'cinnamon-roll', 'Soft, gooey cinnamon roll topped with cream cheese frosting', 1.83, 1.22, 1],
            [$catMap['pastries'], 'Apple Turnover', 'apple-turnover', 'Flaky pastry filled with spiced apple filling', 1.82, 1.21, 0],
            [$catMap['pastries'], 'Pain au Chocolat', 'pain-au-chocolat', 'Chocolate-filled croissant, buttery and indulgent', 1.88, 1.25, 0],
            // Sandwiches
            [$catMap['sandwiches'], 'Bacon Turkey Bravo', 'bacon-turkey-bravo', 'Turkey, bacon, and cheese on focaccia with greens and tomato', 8.33, 5.55, 1],
            [$catMap['sandwiches'], 'Smokehouse BBQ Chicken', 'smokehouse-bbq-chicken', 'Smoked chicken with BBQ sauce on miche bread', 7.88, 5.25, 0],
            [$catMap['sandwiches'], 'Chipotle Chicken Avocado Melt', 'chipotle-chicken-avocado', 'Pulled chicken, avocado, and gouda on toasted bread', 9.11, 6.07, 1],
            [$catMap['sandwiches'], 'Toasted Frontega Chicken', 'frontega-chicken', 'Smoked chicken, basil, and chipotle sauce on focaccia', 8.51, 5.67, 0],
            [$catMap['sandwiches'], 'Tuna Salad Sandwich', 'tuna-salad-sandwich', 'Tuna salad with greens, tomato, and red onion on black pepper focaccia', 6.95, 4.63, 0],
            [$catMap['sandwiches'], 'Mediterranean Veggie', 'mediterranean-veggie', 'Veggies, hummus, and feta on ciabatta', 6.05, 4.03, 0],
            [$catMap['sandwiches'], 'Chicken Green Goddess Caprese', 'chicken-caprese', 'Chicken, mozzarella, and greens on toasted bread', 8.66, 5.77, 0],
            [$catMap['sandwiches'], 'Pepperoni Mozzarella Melt', 'pepperoni-mozzarella', 'Pepperoni, mozzarella, and arugula on toasted baguette', 7.16, 4.77, 0],
            [$catMap['sandwiches'], 'Chicken Cordon Bleu Melt', 'chicken-cordon-bleu', 'Chicken, ham, and gouda on baguette', 8.81, 5.87, 0],
            // Salads
            [$catMap['salads'], 'Fuji Apple Salad with Chicken', 'fuji-apple-salad', 'Chicken, apples, greens, and goat cheese with vinaigrette', 11.51, 7.67, 1],
            [$catMap['salads'], 'Citrus Asian Crunch Salad', 'citrus-asian-salad', 'Chicken, citrus, crunchy toppings, and greens', 11.21, 7.47, 0],
            [$catMap['salads'], 'Greek Salad', 'greek-salad', 'Feta, olives, cucumber, tomato, and greens with Greek dressing', 8.75, 5.83, 0],
            // Soups & Bowls
            [$catMap['soups-bowls'], 'Turkey Chili', 'turkey-chili', 'Hearty turkey chili with beans and vegetables', 6.05, 4.03, 1],
            [$catMap['soups-bowls'], 'Bistro French Onion Soup', 'french-onion-soup', 'Classic onion soup topped with melted cheese and croutons', 5.00, 3.33, 0],
            [$catMap['soups-bowls'], 'Teriyaki Chicken & Broccoli Bowl', 'teriyaki-bowl', 'Chicken, broccoli, and rice with house teriyaki sauce', 8.48, 5.65, 0],
            [$catMap['soups-bowls'], 'Mac & Cheese', 'mac-cheese', 'Creamy pasta with our signature cheese sauce', 4.47, 2.98, 0],
            // Beverages
            [$catMap['beverages'], 'Caramel Latte', 'caramel-latte', 'Espresso with caramel syrup and whipped cream (16 oz)', 2.66, 1.77, 1],
            [$catMap['beverages'], 'Cinnamon Crunch Latte', 'cinnamon-latte', 'Espresso with cinnamon syrup and crunchy topping (16 oz)', 2.81, 1.87, 0],
            [$catMap['beverages'], 'Frozen Chocolate Cold Brew', 'frozen-cold-brew', 'Cold brew blended with chocolate and ice (16 oz)', 2.13, 1.42, 0],
            [$catMap['beverages'], 'Green Passion Smoothie', 'green-smoothie', 'Spinach, grape, and passionfruit juice blend (16 oz)', 3.03, 2.02, 0],
            [$catMap['beverages'], 'Iced Green Tea', 'iced-green-tea', 'Fresh brewed green tea over ice (16 oz)', 0.87, 0.58, 0],
            // Desserts & Sides
            [$catMap['desserts-sides'], 'Greek Yogurt Parfait', 'greek-yogurt-parfait', 'Greek yogurt with granola, mixed berries, and honey', 3.26, 2.17, 1],
            // Catering
            [$catMap['catering'], 'Assorted Sandwich Platter', 'catering-sandwich-platter', 'Mix of chicken, turkey, and veggie sandwiches (per serving, min 12)', 8.33, 5.55, 1],
            [$catMap['catering'], 'Pastry Platter', 'catering-pastry-platter', 'Assorted croissants, muffins, and turnovers (per piece, min 24)', 1.62, 1.08, 0],
            [$catMap['catering'], 'Coffee Service', 'catering-coffee', 'Hot brewed coffee with creamers and sugars (per serving)', 1.02, 0.68, 0],
        ];

        foreach ($products as $p) {
            $slug = $p[2];
            $this->insert(
                "INSERT INTO products (category_id, name, slug, description, price, cost, featured) VALUES (?,?,?,?,?,?,?)",
                [$p[0], $p[1], $slug, $p[3], $p[4], $p[5], $p[6]]
            );
        }
    }
}
