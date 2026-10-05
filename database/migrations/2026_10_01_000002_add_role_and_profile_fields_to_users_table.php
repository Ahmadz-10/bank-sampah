<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable so the migration is safe on a table that already has rows.
            // Enforce "every user has a role" in the app (Phase 1).
            $table->foreignId('role_id')->nullable()->after('id')
                ->constrained('roles')->restrictOnDelete();
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('locale', 5)->default('id')->after('is_active');
            $table->string('theme', 10)->default('light')->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'is_active', 'locale', 'theme', 'last_login_at']);
        });
    }
};
