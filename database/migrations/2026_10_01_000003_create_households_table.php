<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_code', 30)->unique();
            $table->string('head_name', 150);
            $table->text('address');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedSmallInteger('member_count')->default(1);

            // Current status, kept in sync from the latest VALIDATED household_survey.
            // Values: unknown | sorting | not_sorting
            $table->string('sorting_status', 20)->default('unknown')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rt', 'rw']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
