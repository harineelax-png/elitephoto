<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ECommerceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create the schema in the in-memory SQLite database since we are not using migrations
        DB::unprepared("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                email_verified_at TIMESTAMP NULL,
                password VARCHAR(255) NOT NULL,
                role TEXT CHECK(role IN ('customer', 'admin')) NOT NULL DEFAULT 'customer',
                phone VARCHAR(255) NULL,
                address TEXT NULL,
                city VARCHAR(255) NULL,
                state VARCHAR(255) NULL,
                zip_code VARCHAR(255) NULL,
                remember_token VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT NULL,
                image VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'standard',
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT NOT NULL,
                combo_includes TEXT NULL,
                combo_products TEXT NULL,
                price DECIMAL(10,2) NOT NULL,
                variants TEXT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                image VARCHAR(255) NULL,
                images TEXT NULL,
                status TEXT CHECK(status IN ('active', 'inactive')) NOT NULL DEFAULT 'active',
                is_featured INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                weight VARCHAR(255) NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS wishlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                review TEXT NULL,
                status TEXT CHECK(status IN ('pending', 'approved', 'rejected')) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS banners (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NULL,
                image VARCHAR(255) NOT NULL,
                link VARCHAR(255) NULL,
                status TEXT CHECK(status IN ('active', 'inactive')) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug VARCHAR(255) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total DECIMAL(10,2) NOT NULL,
                status TEXT CHECK(status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')) NOT NULL DEFAULT 'pending',
                payment_status TEXT CHECK(payment_status IN ('pending', 'paid', 'failed')) NOT NULL DEFAULT 'pending',
                payment_method VARCHAR(50) NOT NULL DEFAULT 'razorpay',
                razorpay_order_id VARCHAR(255) NULL,
                razorpay_payment_id VARCHAR(255) NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(255) NOT NULL,
                shipping_address TEXT NOT NULL,
                city VARCHAR(255) NOT NULL,
                state VARCHAR(255) NOT NULL,
                zip_code VARCHAR(255) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            );
        ");

        // Seed page text dynamically so views don't error
        DB::table('pages')->insert([
            ['slug' => 'about', 'title' => 'About Us', 'content' => 'Fresh cookies daily.'],
            ['slug' => 'privacy', 'title' => 'Privacy Policy', 'content' => 'Secure privacy.'],
            ['slug' => 'shipping', 'title' => 'Shipping Policy', 'content' => 'Fast shipping.'],
        ]);
    }

    /** @test */
    public function test_custom_registration_and_login_works()
    {
        $response = $this->post('/register', [
            'name' => 'John Customer',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
            'address' => '123 Street',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'zip_code' => '560001'
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', [
            'email' => 'john@test.com',
            'role' => 'customer'
        ]);
    }

    /** @test */
    public function test_storefront_shop_catalog_displays_products()
    {
        // Seed category and product
        $category = Category::create([
            'name' => 'Classic Collection',
            'slug' => 'classic-collection',
            'description' => 'Plain cookies.'
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chocolate Chip Extra Chunky',
            'slug' => 'chocolate-chip-extra-chunky',
            'description' => 'Real butter cookies.',
            'price' => 150.00,
            'stock' => 20,
            'status' => 'active',
            'is_featured' => true
        ]);

        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('Chocolate Chip Extra Chunky');
        $response->assertSee('₹150');
    }

    /** @test */
    public function test_shopping_cart_logic_requires_auth_and_persists()
    {
        $category = Category::create([
            'name' => 'Stuffed Cookies',
            'slug' => 'stuffed-cookies',
            'description' => 'Oozing centers.'
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Nutella Lava Melt',
            'slug' => 'nutella-lava-melt',
            'description' => 'Hazelnut spread center.',
            'price' => 200.00,
            'stock' => 50,
            'status' => 'active'
        ]);

        // Accessing cart without auth should redirect to login
        $this->get('/cart')->assertRedirect(route('login'));

        // Register/Login a customer
        $user = User::create([
            'name' => 'Alice Baker',
            'email' => 'alice@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer'
        ]);

        $this->actingAs($user);

        // Add to cart
        $response = $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        // View Cart
        $cartResponse = $this->get('/cart');
        $cartResponse->assertStatus(200);
        $cartResponse->assertSee('Nutella Lava Melt');
        $cartResponse->assertSee('₹400'); // 2 * 200
    }

    /** @test */
    public function test_admin_middleware_guards_dashboard()
    {
        $customer = User::create([
            'name' => 'Bob Customer',
            'email' => 'bob@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer'
        ]);

        $admin = User::create([
            'name' => 'Admin Owner',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        // Guest access gets redirected to admin login
        $this->get('/admin/dashboard')->assertRedirect(route('login'));

        // Customer access gets redirected to home with error
        $this->actingAs($customer);
        $this->get('/admin/dashboard')->assertRedirect(route('home'));

        // Admin access gets through successfully
        $this->actingAs($admin);
        $this->get('/admin/dashboard')->assertStatus(200);
    }

    /** @test */
    public function test_review_submission_moderation_flow_works()
    {
        $customer = User::create([
            'name' => 'David Reviewer',
            'email' => 'david@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer'
        ]);

        $admin = User::create([
            'name' => 'Admin Moderator',
            'email' => 'admin@moderator.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Macarons',
            'slug' => 'macarons'
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Rose Macaron',
            'slug' => 'rose-macaron',
            'description' => 'Rose water shells.',
            'price' => 80.00,
            'stock' => 10,
            'status' => 'active'
        ]);

        $this->actingAs($customer);

        // Submit review
        $this->post("/product/{$product->id}/rate", [
            'rating' => 4,
            'review' => 'Delightful flavour.'
        ]);

        // Review should be in DB as 'pending'
        $this->assertDatabaseHas('ratings', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 4,
            'review' => 'Delightful flavour.',
            'status' => 'pending'
        ]);

        // Product details page should NOT display this review yet
        $this->get("/product/{$product->slug}")->assertDontSee('Delightful flavour.');

        // Login as Admin and Approve
        $this->actingAs($admin);
        $rating = Rating::first();
        $this->patch("/admin/ratings/{$rating->id}/approve");

        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'status' => 'approved'
        ]);

        // Now product details page should display the review
        $this->actingAs($customer);
        $this->get("/product/{$product->slug}")->assertSee('Delightful flavour.');
    }
}
