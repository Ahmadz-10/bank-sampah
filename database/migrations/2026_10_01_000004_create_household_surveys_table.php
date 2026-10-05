<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->restrictOnDelete();
            $table->foreignId('surveyed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('survey_date');
            $table->string('sorting_status', 20);          // sorting | not_sorting
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();      // field evidence (storage/app/public)

            // Validation workflow: pending -> validated | rejected
            $table->string('validation_status', 20)->default('pending')->index();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();

            $table->timestamps();

            $table->index(['household_id', 'survey_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_surveys');
    }
};
