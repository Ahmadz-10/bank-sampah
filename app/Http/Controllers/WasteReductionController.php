<?php

namespace App\Http\Controllers;

use App\Http\Requests\WasteReductionRequest;
use App\Models\MonthlyWasteReduction;
use App\Models\RwWasteReport;
use App\Services\MonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Monthly waste-reduction summary. Records are created by the weighing sheet, never typed:
 * this screen only reads them and lets an admin set the target and a note.
 */
class WasteReductionController extends Controller
{
    public function __construct(private MonitoringService $monitoring) {}

    public function index(Request $request): View
    {
        $year = (int) $request->input('year', today()->format('Y'));
        if ($year < 2020 || $year > 2100) {
            $year = (int) today()->format('Y');
        }

        $records = MonthlyWasteReduction::query()->where('year', $year)->get()->keyBy('month');
        $rwCounts = RwWasteReport::query()->where('year', $year)->selectRaw('month, count(*) as total')->groupBy('month')->pluck('total', 'month');
        $deposited = $this->monitoring->depositedKgByMonth($year);

        $rows = collect(range(1, 12))->map(fn (int $m) => [
            'month' => $m,
            'record' => $records->get($m),
            'rw_reported' => (int) ($rwCounts[$m] ?? 0),
            'deposited_kg' => (float) ($deposited[$m] ?? 0),
        ]);

        $years = MonthlyWasteReduction::query()->distinct()->pluck('year')
            ->merge(RwWasteReport::query()->distinct()->pluck('year'))
            ->push((int) today()->format('Y'))->push($year)
            ->unique()->sortDesc()->values();

        return view('waste-reductions.index', [
            'rows' => $rows,
            'chart' => $this->monitoring->reductionChart($rows),
            'year' => $year,
            'years' => $years,
            'totals' => [
                'generated' => (float) $records->sum('total_waste_generated'),
                'diverted' => (float) $records->sum('waste_diverted'),
                'to_tps' => (float) $records->sum('waste_to_tps'),
                'percentage' => MonitoringService::percentage((float) $records->sum('waste_diverted'), (float) $records->sum('total_waste_generated')),
            ],
        ]);
    }

    public function edit(MonthlyWasteReduction $wasteReduction): View
    {
        return view('waste-reductions.form', ['reduction' => $wasteReduction]);
    }

    public function update(WasteReductionRequest $request, MonthlyWasteReduction $wasteReduction): RedirectResponse
    {
        $wasteReduction->update($request->validated());

        return redirect()->route('waste-reductions.index', ['year' => $wasteReduction->year])
            ->with('status', __('Target was updated.'));
    }
}
