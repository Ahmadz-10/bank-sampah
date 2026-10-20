<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The kelurahan's real price list (workbook "Buku_laporan_sampah", sheet 2),
 * with each category mapped to one of the four report groups.
 * Prices are per kg, in Rupiah, effective today. Safe to run again.
 */
class WasteCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $today = $now->toDateString();

        // code, name, report group, buy price, sell price
        $items = [
            ['ORG', 'Organik', 'organik',   200,   350],
            ['PLS', 'Plastik', 'anorganik', 3000,  5000],
            ['KRT', 'Kertas',  'anorganik', 1500,  2500],
            ['LGM', 'Logam',   'anorganik', 8000,  12000],
            ['B3',  'B3',      'b3',        0,     0],
            ['RSD', 'Residu',  'residu',    0,     0],
        ];

        foreach ($items as [$code, $name, $group, $buy, $sell]) {
            DB::table('waste_categories')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'unit' => 'kg', 'waste_group' => $group, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );

            $categoryId = DB::table('waste_categories')->where('code', $code)->value('id');

            DB::table('waste_prices')->updateOrInsert(
                ['waste_category_id' => $categoryId, 'effective_date' => $today],
                ['buy_price' => $buy, 'sell_price' => $sell, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
