<?php

namespace App\Services;

use App\Models\MonthlyWasteReduction;
use App\Models\RwWasteReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the RW weighings of a month into the kelurahan-wide monthly record.
 * The grouping rules live in config/monitoring.php; the percentage lives here, once.
 */
class MonitoringService
{
    /** reduction % = diverted / total x 100 (0 when nothing was generated). */
    public static function percentage(float $diverted, float $total): float
    {
        return $total > 0 ? round($diverted / $total * 100, 2) : 0.0;
    }

    /**
     * Rebuild the monthly record from that month's RW weighings.
     * Returns null when there is nothing to record and no record exists yet.
     */
    public function recalculate(int $year, int $month, ?User $by = null): ?MonthlyWasteReduction
    {
        $reports = RwWasteReport::query()->where('year', $year)->where('month', $month)->get();
        $record = MonthlyWasteReduction::query()->where('year', $year)->where('month', $month)->first();

        if ($reports->isEmpty() && $record === null) {
            return null;
        }

        $diverted = (array) config('monitoring.diverted_groups', []);
        $toTps = (array) config('monitoring.to_tps_groups', []);

        $total = round((float) $reports->sum(fn (RwWasteReport $r) => $r->monthlyKg()), 2);
        $divertedKg = round((float) $reports->sum(fn (RwWasteReport $r) => $r->monthlyKg($diverted)), 2);
        $tpsKg = round((float) $reports->sum(fn (RwWasteReport $r) => $r->monthlyKg($toTps)), 2);

        $record ??= new MonthlyWasteReduction;

        if (! $record->exists) {
            $record->forceFill(['year' => $year, 'month' => $month, 'target_percentage' => $this->defaultTarget($year, $month)]);
        }

        $record->forceFill([
            'total_waste_generated' => $total,
            'waste_diverted' => $divertedKg,
            'waste_to_tps' => $tpsKg,
            'reduction_percentage' => self::percentage($divertedKg, $total),
            'recorded_by' => $by?->id ?? $record->recorded_by,
        ])->save();

        return $record;
    }

    /** A new month inherits the target of the closest earlier month, else the config default. */
    public function defaultTarget(int $year, int $month): float
    {
        $previous = MonthlyWasteReduction::query()
            ->where(fn ($q) => $q->where('year', '<', $year)->orWhere(fn ($q) => $q->where('year', $year)->where('month', '<', $month)))
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        return (float) ($previous?->target_percentage ?? config('monitoring.default_target', 0));
    }

    /**
     * Weight recorded through bank-sampah deposits per month of a year (voided ones excluded).
     * Shown beside the weighings as a cross-check; it is not part of the formula.
     *
     * @return Collection<int, float> month number => kg
     */
    public function depositedKgByMonth(int $year): Collection
    {
        $start = Carbon::create($year, 1, 1)->toDateString();
        $end = Carbon::create($year, 12, 31)->toDateString();

        return DB::table('waste_deposit_details as d')
            ->join('waste_deposits as h', 'h.id', '=', 'd.waste_deposit_id')
            ->whereNull('h.deleted_at')
            ->whereDate('h.deposit_date', '>=', $start)
            ->whereDate('h.deposit_date', '<=', $end)
            ->get(['h.deposit_date', 'd.weight'])
            ->groupBy(fn ($row) => (int) substr((string) $row->deposit_date, 5, 2))
            ->map(fn ($rows) => round((float) $rows->sum('weight'), 3));
    }
}
