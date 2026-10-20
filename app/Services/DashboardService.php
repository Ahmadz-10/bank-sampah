<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteDeposit;
use App\Models\WasteSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the dashboard shows, in one place.
 *
 * WHO SEES WHAT (decision D2): a block is shown when the user may already open the page behind it.
 * Pimpinan (viewer) can open none of those pages, but may read reports, so `view-reports` also
 * unlocks the read-only totals and charts. Rows with names (recent transactions) and the
 * pending-survey queue are operational, so they need the matching manage/survey ability.
 * Data for a hidden block is not even queried.
 *
 * Voided (soft-deleted) deposits and sales never count, and "this month" is the deposit/sale date,
 * exactly like the date filters on the list pages, so the totals can be compared with them.
 */
class DashboardService
{
    public function __construct(private StockService $stock, private MonitoringService $monitoring) {}

    /**
     * @return array<string, mixed>  'show' (which blocks), 'month_start', 'month_end', 'year', then one key per shown block
     */
    public function forUser(User $user): array
    {
        $reports = $user->can('view-reports');

        $show = [
            'households' => $reports || $user->can('manage-households'),
            'customers' => $reports || $user->can('manage-customers'),
            'transactions' => $reports || $user->can('manage-transactions'),   // deposits, sales, stock, waste per category
            'reduction' => $reports || $user->can('manage-monitoring'),
            'recent' => $user->can('manage-transactions'),
            'surveys' => $user->can('survey-households'),
        ];

        $start = today()->startOfMonth();
        $end = today()->endOfMonth();

        $data = [
            'show' => $show,
            'month_start' => $start->toDateString(),
            'month_end' => $end->toDateString(),
            'month_label' => $start->translatedFormat('F Y'),
            'year' => $start->year,
        ];

        if ($show['households']) {
            $data['households'] = $this->households();
        }

        if ($show['customers']) {
            $data['customers'] = ['active' => Customer::query()->where('is_active', true)->count(), 'total' => Customer::query()->count()];
        }

        if ($show['transactions']) {
            $data['deposits'] = $this->monthTotals(WasteDeposit::query(), 'deposit_date', $start, $end);
            $data['sales'] = $this->monthTotals(WasteSale::query(), 'sale_date', $start, $end);
            $data['stock_kg'] = $this->stockKg();
            $data['categories'] = $this->depositedByCategory($start, $end);
        }

        if ($show['reduction']) {
            $data['reduction_chart'] = $this->monitoring->reductionChart($this->monitoring->monthlyRows($start->year));
        }

        if ($show['recent']) {
            $data['recent_deposits'] = WasteDeposit::query()->with('customer:id,customer_code,name')
                ->orderByDesc('deposit_date')->orderByDesc('id')->limit(5)->get();
            $data['recent_sales'] = WasteSale::query()->orderByDesc('sale_date')->orderByDesc('id')->limit(5)->get();
        }

        if ($show['surveys']) {
            $data['pending_surveys'] = HouseholdSurvey::query()->where('validation_status', HouseholdSurvey::PENDING)->count();
        }

        return $data;
    }

    /** @return array{total: int, sorting: int, not_sorting: int, unknown: int} */
    private function households(): array
    {
        $counts = Household::query()->selectRaw('sorting_status, count(*) as total')->groupBy('sorting_status')->pluck('total', 'sorting_status');

        $sorting = (int) ($counts[Household::STATUS_SORTING] ?? 0);
        $notSorting = (int) ($counts[Household::STATUS_NOT_SORTING] ?? 0);
        $unknown = (int) ($counts[Household::STATUS_UNKNOWN] ?? 0);

        return ['total' => $sorting + $notSorting + $unknown, 'sorting' => $sorting, 'not_sorting' => $notSorting, 'unknown' => $unknown];
    }

    /**
     * Same query as the totals bar of the Deposits / Sales list pages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return array{count: int, weight: float, amount: float}
     */
    private function monthTotals($query, string $dateColumn, Carbon $start, Carbon $end): array
    {
        $row = $query
            ->whereDate($dateColumn, '>=', $start->toDateString())
            ->whereDate($dateColumn, '<=', $end->toDateString())
            ->toBase()
            ->selectRaw('count(*) as cnt, coalesce(sum(total_weight), 0) as weight, coalesce(sum(total_amount), 0) as amount')
            ->first();

        return ['count' => (int) $row->cnt, 'weight' => (float) $row->weight, 'amount' => (float) $row->amount];
    }

    /** Stock in kg: deposited minus sold, summed over the categories counted in kg (liter/pcs cannot be added to kg). */
    private function stockKg(): float
    {
        $kgCategories = WasteCategory::query()->where('unit', 'kg')->pluck('id')->all();

        return round((float) $this->stock->levels($kgCategories)->sum(), 3);
    }

    /**
     * Kg deposited this month per category, biggest first (top 8), voided deposits excluded.
     *
     * @return array<int, array{name: string, kg: float}>
     */
    private function depositedByCategory(Carbon $start, Carbon $end): array
    {
        return DB::table('waste_deposit_details as d')
            ->join('waste_deposits as h', 'h.id', '=', 'd.waste_deposit_id')
            ->join('waste_categories as c', 'c.id', '=', 'd.waste_category_id')
            ->whereNull('h.deleted_at')
            ->where('c.unit', 'kg')
            ->whereDate('h.deposit_date', '>=', $start->toDateString())
            ->whereDate('h.deposit_date', '<=', $end->toDateString())
            ->groupBy('c.id', 'c.name')
            ->selectRaw('c.name as name, SUM(d.weight) as kg')
            ->orderByDesc('kg')
            ->orderBy('c.name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'kg' => round((float) $row->kg, 3)])
            ->all();
    }
}
