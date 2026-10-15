<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->foreignId('rw_id')->nullable()->after('rw')->constrained('rws')->nullOnDelete();
        });

        // Convert the old free-text RW ("002", "RW 02", "2") to the new link.
        // The old `rw` column is left untouched (no data loss); the app no longer uses it.
        $rwIds = DB::table('rws')->pluck('id', 'number');

        DB::table('households')->whereNotNull('rw')->orderBy('id')->each(function ($household) use ($rwIds) {
            $number = (int) preg_replace('/\D/', '', (string) $household->rw);

            if ($number > 0 && isset($rwIds[$number])) {
                DB::table('households')->where('id', $household->id)->update(['rw_id' => $rwIds[$number]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rw_id');
        });
    }
};
