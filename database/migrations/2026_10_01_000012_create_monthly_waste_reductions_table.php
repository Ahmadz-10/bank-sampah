<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per month. Weights in kg.
        Schema::create('monthly_waste_reductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_waste_generated', 12, 2)->default(0);
            $table->decimal('waste_diverted', 12, 2)->default(0);   // handled via sorting / bank sampah
            $table->decimal('waste_to_tps', 12, 2)->default(0);     // still sent to TPS
            $table->decimal('reduction_percentage', 5, 2)->default(0);
            $table->decimal('target_percentage', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_waste_reductions');
    }
};
