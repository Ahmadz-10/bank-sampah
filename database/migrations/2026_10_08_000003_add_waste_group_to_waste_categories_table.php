<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_categories', function (Blueprint $table) {
            // organik | anorganik | b3 | residu : the four columns of the kelurahan's report.
            // Existing categories default to anorganik; an admin can correct them.
            $table->string('waste_group', 20)->default('anorganik')->after('unit')->index();
        });
    }

    public function down(): void
    {
        Schema::table('waste_categories', function (Blueprint $table) {
            $table->dropColumn('waste_group');
        });
    }
};
