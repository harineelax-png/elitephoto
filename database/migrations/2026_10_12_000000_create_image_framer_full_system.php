<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0. Create frames table FIRST so foreign keys don't fail
        if (!Schema::hasTable('frames')) {
            Schema::create('frames', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('price', 10, 2)->nullable();
                $table->string('category', 100)->default('General');
                $table->text('description')->nullable();
                $table->string('template_path')->nullable();
                $table->integer('template_width')->nullable();
                $table->integer('template_height')->nullable();
                $table->json('slot_positions')->nullable();
                $table->integer('slot_width')->nullable();
                $table->integer('slot_height')->nullable();
                $table->boolean('is_uniform_slots')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 1. Update frames table if needed
        if (Schema::hasTable('frames')) {
            Schema::table('frames', function (Blueprint $table) {
                if (!Schema::hasColumn('frames', 'price')) {
                    $table->decimal('price', 10, 2)->nullable()->after('name');
                }
                if (!Schema::hasColumn('frames', 'category')) {
                    $table->string('category', 100)->default('General')->after('price');
                }
                if (!Schema::hasColumn('frames', 'description')) {
                    $table->text('description')->nullable()->after('category');
                }
                if (!Schema::hasColumn('frames', 'template_width')) {
                    $table->integer('template_width')->nullable()->after('template_path');
                }
                if (!Schema::hasColumn('frames', 'template_height')) {
                    $table->integer('template_height')->nullable()->after('template_width');
                }
                if (!Schema::hasColumn('frames', 'slot_width')) {
                    $table->integer('slot_width')->nullable()->after('slot_positions');
                }
                if (!Schema::hasColumn('frames', 'slot_height')) {
                    $table->integer('slot_height')->nullable()->after('slot_width');
                }
                if (!Schema::hasColumn('frames', 'is_uniform_slots')) {
                    $table->boolean('is_uniform_slots')->default(false)->after('slot_height');
                }
                if (!Schema::hasColumn('frames', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('is_uniform_slots');
                }
            });
        }

        // 2. Settings table
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }

        // 3. Photo Shoot Plans table
        if (!Schema::hasTable('photoshoot_plans')) {
            Schema::create('photoshoot_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('price', 10, 2);
                $table->string('duration');
                $table->string('deliverables')->nullable();
                $table->text('description')->nullable();
                $table->json('features')->nullable();
                $table->string('badge')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. Photo Shoot Bookings table
        if (!Schema::hasTable('photoshoot_bookings')) {
            Schema::create('photoshoot_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('booking_number')->unique();
                $table->foreignId('photoshoot_plan_id')->nullable()->constrained('photoshoot_plans')->nullOnDelete();
                $table->string('plan_name');
                $table->string('customer_name');
                $table->string('customer_email');
                $table->string('customer_phone');
                $table->date('booking_date');
                $table->string('booking_time');
                $table->string('location')->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
                $table->timestamps();
            });
        }

        // 5. Frame Orders table (with Razorpay integration)
        if (!Schema::hasTable('frame_orders')) {
            Schema::create('frame_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->foreignId('frame_id')->constrained('frames')->cascadeOnDelete();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone')->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('INR');
                $table->string('razorpay_order_id')->nullable()->index();
                $table->string('razorpay_payment_id')->nullable()->index();
                $table->string('razorpay_signature')->nullable();
                $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
                $table->string('generated_image_path')->nullable();
                $table->string('preview_image_path')->nullable();
                $table->string('download_token', 64)->unique();
                $table->integer('download_count')->default(0);
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('frame_orders');
        Schema::dropIfExists('photoshoot_bookings');
        Schema::dropIfExists('photoshoot_plans');
        Schema::dropIfExists('settings');
    }
};