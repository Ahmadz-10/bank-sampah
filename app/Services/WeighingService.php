<?php

namespace App\Services;

use App\Models\RwWasteReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Saves the weighing sheet of one month (one row per RW) and refreshes the monthly roll-up. */
class WeighingService
{
    /** Numeric inputs of one row. A row where all are 0 and no remark is typed counts as "not reported". */
    public const NUMERIC = ['organic_kg_per_day', 'inorganic_kg_per_day', 'b3_kg_per_day', 'residue_kg_per_day', 'carts', 'collectors', 'pickup_frequency'];

    public function __construct(private MonitoringService $monitoring) {}

    public static function daysIn(int $year, int $month): int
    {
        return Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows  keyed by RW id
     * @return int number of RW rows that are stored after saving
     */
    public function saveSheet(int $year, int $month, array $rows, User $user): int
    {
        return DB::transaction(function () use ($year, $month, $rows, $user) {
            $stored = 0;

            foreach ($rows as $rwId => $values) {
                $existing = RwWasteReport::query()
                    ->where('rw_id', $rwId)->where('year', $year)->where('month', $month)->first();

                $note = trim((string) ($values['notes'] ?? ''));
                $allZero = collect(self::NUMERIC)->every(fn ($key) => (float) ($values[$key] ?? 0) == 0.0);

                if ($allZero && $note === '') {
                    $existing?->delete();      // cleared row = not reported this month

                    continue;
                }

                $report = $existing ?? new RwWasteReport(['rw_id' => (int) $rwId, 'year' => $year, 'month' => $month]);
                $report->fill(collect(self::NUMERIC)->mapWithKeys(fn ($key) => [$key => $values[$key] ?? 0])->all());
                $report->days_counted = (int) ($values['days_counted'] ?? 0) ?: self::daysIn($year, $month);
                $report->notes = $note === '' ? null : $note;
                $report->recorded_by = $user->id;
                $report->save();

                $stored++;
            }

            $this->monitoring->recalculate($year, $month, $user);

            return $stored;
        });
    }
}
