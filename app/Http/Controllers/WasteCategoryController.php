<?php

namespace App\Http\Controllers;

use App\Http\Requests\WasteCategoryRequest;
use App\Models\WasteCategory;
use App\Models\WastePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * No destroy action on purpose: categories are referenced by prices and (later) by
 * deposit/sale details. Use the "Active" switch to retire one without losing history.
 */
class WasteCategoryController extends Controller
{
    public function index(): View
    {
        $categories = WasteCategory::query()->with('prices')->orderBy('code')->get();

        return view('waste-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('waste-categories.form', ['category' => new WasteCategory]);
    }

    public function store(WasteCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(function () use ($request) {
            $category = WasteCategory::create($request->safe()->only(['code', 'name', 'unit', 'description', 'is_active']));

            $price = new WastePrice($request->safe()->only(['buy_price', 'sell_price', 'effective_date']));
            $price->waste_category_id = $category->id;
            $price->created_by = $request->user()->id;
            $price->save();

            return $category;
        });

        return redirect()->route('waste-categories.index')
            ->with('status', __('Category :code was created.', ['code' => $category->code]));
    }

    public function edit(WasteCategory $wasteCategory): View
    {
        return view('waste-categories.form', ['category' => $wasteCategory]);
    }

    public function update(WasteCategoryRequest $request, WasteCategory $wasteCategory): RedirectResponse
    {
        $wasteCategory->update($request->validated());

        return redirect()->route('waste-categories.index')
            ->with('status', __('Category :code was updated.', ['code' => $wasteCategory->code]));
    }
}
