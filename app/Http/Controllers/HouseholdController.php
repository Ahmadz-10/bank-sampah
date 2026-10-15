<?php

namespace App\Http\Controllers;

use App\Http\Requests\HouseholdRequest;
use App\Models\Household;
use App\Models\Rw;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));

        $households = Household::query()
            ->withCount('customers')
            ->with('rw:id,number')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(fn ($q) => $q
                    ->where('household_code', 'like', "%{$term}%")
                    ->orWhere('head_name', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('sorting_status', $request->input('status')))
            ->orderBy('household_code')
            ->paginate(15)
            ->withQueryString();

        return view('households.index', compact('households', 'term'));
    }

    public function create(): View
    {
        return view('households.form', ['household' => new Household, 'rwOptions' => $this->rwOptions()]);
    }

    public function store(HouseholdRequest $request): RedirectResponse
    {
        $household = new Household($request->validated());
        $household->created_by = $request->user()->id;
        $household->save();

        return redirect()->route('households.index')
            ->with('status', __('Household :code was created.', ['code' => $household->household_code]));
    }

    /** Detail page: household data, customers, current status and the full survey history. */
    public function show(Household $household): View
    {
        $household->load(['customers', 'creator', 'rw']);

        $surveys = $household->surveys()
            ->with(['surveyor:id,name', 'validator:id,name'])
            ->orderByDesc('survey_date')
            ->orderByDesc('id')
            ->get();

        return view('households.show', compact('household', 'surveys'));
    }

    public function edit(Household $household): View
    {
        return view('households.form', ['household' => $household, 'rwOptions' => $this->rwOptions($household->rw_id)]);
    }

    public function update(HouseholdRequest $request, Household $household): RedirectResponse
    {
        $household->update($request->validated());

        return redirect()->route('households.index')
            ->with('status', __('Household :code was updated.', ['code' => $household->household_code]));
    }

    /** Soft delete: the record can be restored from the database if needed. */
    public function destroy(Household $household): RedirectResponse
    {
        $household->delete();

        return redirect()->route('households.index')
            ->with('status', __('Household :code was deleted.', ['code' => $household->household_code]));
    }

    /** @return array<int, string> RW id => "RW 01" (active RWs, plus the household's current one) */
    private function rwOptions(?int $currentId = null): array
    {
        return Rw::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q) => $q->orWhere('id', $currentId)))
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn (Rw $rw) => [$rw->id => $rw->name])
            ->all();
    }
}
