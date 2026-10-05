<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);              // login, logout, created, updated, deleted, validated ...
            $table->string('module', 50)->nullable();  // households, deposits, ...
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');         // the record that was affected
            $table->json('properties')->nullable();    // old/new values
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
