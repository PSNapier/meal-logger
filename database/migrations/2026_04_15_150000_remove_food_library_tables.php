<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_item_id');
            $table->dropColumn('quantity');
        });

        Schema::dropIfExists('food_items');
    }

    public function down(): void
    {
        Schema::create('food_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('unit', 32)->default('oz');
            $table->string('unit_dimension', 16)->default('mass');
            $table->decimal('unit_quantity', 10, 3)->default(1);
            $table->unsignedInteger('calories_per_unit')->default(0);
            $table->decimal('protein_g_per_unit', 10, 3)->default(0);
            $table->decimal('carbs_g_per_unit', 10, 3)->default(0);
            $table->decimal('fat_g_per_unit', 10, 3)->default(0);
            $table->decimal('sugar_g_per_unit', 10, 3)->default(0);
            $table->decimal('fiber_g_per_unit', 10, 3)->default(0);
            $table->decimal('water_oz_per_unit', 10, 3)->default(0);
            $table->string('source', 24)->default('user_manual');
            $table->timestamps();

            $table->unique(['user_id', 'normalized_name']);
            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'normalized_name']);
        });

        Schema::table('meal_items', function (Blueprint $table) {
            $table->foreignId('food_item_id')->nullable()->after('daily_log_id')->constrained('food_items')->nullOnDelete();
            $table->decimal('quantity', 10, 3)->nullable()->after('description');
        });
    }
};
