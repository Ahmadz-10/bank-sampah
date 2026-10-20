<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per RW per month: a line of the kelurahan's "Laporan timbangan sampah".
        // Totals are NOT stored; they are calculated (see App\Models\RwWasteReport).
        Schema::create('rw_waste_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rw_id')->constrained('rws')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('organic_kg_per_day', 10, 2)->default(0);
            $table->decimal('inorganic_kg_per_day', 10, 2)->default(0);
            $table->decimal('b3_kg_per_day', 10, 2)->default(0);
            $table->decimal('residue_kg_per_day', 10, 2)->default(0);
            $table->unsignedSmallInteger('carts')->default(0);              // Gerobak
            $table->unsignedSmallInteger('collectors')->default(0);         // Petugas
            $table->unsignedSmallInteger('pickup_frequency')->default(0);   // Frekuensi Angkut
            $table->unsignedTinyInteger('days_counted')->default(30);       // Hari/Bulan
            $table->string('notes')->nullable();                            // Keterangan
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rw_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rw_waste_reports');
    }
};
