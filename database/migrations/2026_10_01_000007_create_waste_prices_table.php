<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per price change, so history is preserved.
        // Current price = latest row with effective_date <= today.
        Schema::create('waste_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_category_id')->constrained('waste_categories')->restrictOnDelete();
            $table->decimal('buy_price', 12, 2);              // paid to customers per unit
            $table->decimal('sell_price', 12, 2)->nullable(); // reference price when selling to collectors
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['waste_category_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_prices');
    }
};
