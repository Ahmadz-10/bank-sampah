<?php

namespace App\Http\Controllers;

use App\Http\Requests\HouseholdRequest;
use App\Models\Household;
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
        return view('households.form', ['household' => new Household]);
    }

    public function store(HouseholdRequest $request): RedirectResponse
    {
        $household = new Household($request->validated());
        $household->created_by = $request->user()->id;
        $household->save();

        return redirect()->route('households.index')
            ->with('status', __('Household :code was created.', ['code' => $household->household_code]));
    }

    public function edit(Household $household): View
    {
        return view('households.form', compact('household'));
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
}
