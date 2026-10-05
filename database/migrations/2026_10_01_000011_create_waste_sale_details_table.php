<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_sale_id')->constrained('waste_sales')->cascadeOnDelete();
            $table->foreignId('waste_category_id')->constrained('waste_categories')->restrictOnDelete();
            $table->decimal('weight', 12, 3);
            $table->decimal('price_per_unit', 12, 2);   // selling price at sale time
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_sale_details');
    }
};
