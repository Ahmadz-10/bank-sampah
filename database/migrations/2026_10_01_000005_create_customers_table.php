<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 30)->unique();   // nomor nasabah
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('name', 150)->index();
            $table->string('id_number', 20)->nullable();     // NIK
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->date('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
