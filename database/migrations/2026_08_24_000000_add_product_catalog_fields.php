<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bring installations imported from the original SQL dump up to the schema
     * expected by the product, combo, gallery, and cart code.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $fields = [
                'type' => fn (Blueprint $table) => $table->string('type', 50)->default('standard'),
                'combo_includes' => fn (Blueprint $table) => $table->text('combo_includes')->nullable(),
                'combo_products' => fn (Blueprint $table) => $table->text('combo_products')->nullable(),
                'variants' => fn (Blueprint $table) => $table->text('variants')->nullable(),
                'images' => fn (Blueprint $table) => $table->text('images')->nullable(),
            ];

            foreach ($fields as $column => $definition) {
                if (! Schema::hasColumn('products', $column)) {
                    Schema::table('products', $definition);
                }
            }

            DB::table('products')->whereNull('type')->update(['type' => 'standard']);
        }

        if (Schema::hasTable('cart_items') && ! Schema::hasColumn('cart_items', 'weight')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->string('weight')->nullable();
            });
        }
    }

    /**
     * Existing stores may already contain data in these columns, so rollback is
     * intentionally non-destructive.
     */
    public function down(): void
    {
        // No-op: do not delete live catalogue or cart metadata on rollback.
    }
};
