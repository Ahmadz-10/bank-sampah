<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rws', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();          // 1 = "RW 01"
            $table->string('chairman_name', 150)->nullable();         // Nama Ketua RW
            $table->unsignedSmallInteger('household_count')->nullable(); // official Jumlah KK
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kelurahan Meruya Utara has RW 01 to RW 11 (see the weighbridge report).
        // Reference data lives here so every environment, including tests, has it.
        $now = now();
        DB::table('rws')->insert(array_map(
            fn (int $n) => ['number' => $n, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            range(1, 11)
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('rws');
    }
};
