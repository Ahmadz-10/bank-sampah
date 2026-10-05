<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WasteCategorySeeder extends Seeder
{
    /**
     * SAMPLE data. Replace names and prices with the real bank sampah price list.
     */
    public function run(): void
    {
        $now = now();
        $today = $now->toDateString();

        // code, name, unit, buy_price, sell_price
        $items = [
            ['PLS', 'Plastik (botol PET)', 'kg',    2000, 2500],
            ['KRT', 'Kertas',              'kg',    1000, 1300],
            ['KRD', 'Kardus',              'kg',    1500, 1900],
            ['LGM', 'Logam',               'kg',    4000, 5000],
            ['KCA', 'Kaca',                'kg',     300,  500],
            ['MNJ', 'Minyak jelantah',     'liter', 4000, 5500],
        ];

        foreach ($items as [$code, $name, $unit, $buy, $sell]) {
            DB::table('waste_categories')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'unit' => $unit, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );

            $categoryId = DB::table('waste_categories')->where('code', $code)->value('id');

            DB::table('waste_prices')->updateOrInsert(
                ['waste_category_id' => $categoryId, 'effective_date' => $today],
                ['buy_price' => $buy, 'sell_price' => $sell, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
