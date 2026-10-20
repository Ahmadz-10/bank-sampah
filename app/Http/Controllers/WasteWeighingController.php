<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeighingSheetRequest;
use App\Models\Rw;
use App\Models\RwWasteReport;
use App\Services\WeighingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The monthly weighing sheet: one editable row per RW (the kelurahan's "Laporan timbangan sampah"). */
class WasteWeighingController extends Controller
{
    public function __construct(private WeighingService $weighings) {}

    public function index(Request $request): View
    {
        [$year, $month] = $this->period($request);
        $monthStart = Carbon::create($year, $month, 1);

        $reports = RwWasteReport::query()->where('year', $year)->where('month', $month)->get()->keyBy('rw_id');

        // Active RWs, plus any retired RW that still has a weighing in this month.
        $rws = Rw::query()
            ->withCount('households')
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $reports->keys()))
            ->orderBy('number')
            ->get();

        return view('waste-weighings.index', [
            'rws' => $rws,
            'reports' => $reports,
            'year' => $year,
            'month' => $month,
            'monthName' => __($monthStart->format('F')).' '.$year,
            'daysInMonth' => $monthStart->daysInMonth,
            'prev' => $monthStart->copy()->subMonth(),
            'next' => $monthStart->copy()->addMonth(),
        ]);
    }

    public function store(WeighingSheetRequest $request): RedirectResponse
    {
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');

        $stored = $this->weighings->saveSheet($year, $month, $request->input('rows'), $request->user());

        return redirect()->route('waste-weighings.index', ['year' => $year, 'month' => $month])
            ->with('status', __('Weighing sheet saved: :count RW reported.', ['count' => $stored]));
    }

    /** @return array{0:int,1:int} year and month from the query string, defaulting to this month */
    private function period(Request $request): array
    {
        $year = (int) $request->input('year', today()->format('Y'));
        $month = (int) $request->input('month', today()->format('n'));

        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            return [(int) today()->format('Y'), (int) today()->format('n')];
        }

        return [$year, $month];
    }
}
