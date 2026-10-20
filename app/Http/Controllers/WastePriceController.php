<?php

namespace App\Http\Controllers;

use App\Http\Requests\WastePriceRequest;
use App\Models\WasteCategory;
use App\Models\WastePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WastePriceController extends Controller
{
    public function index(Request $request): View
    {
        $prices = WastePrice::query()
            ->with('category:id,code,name,unit')
            ->when($request->filled('category'), fn ($query) => $query->where('waste_category_id', $request->input('category')))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Which price row is "current" for each category (latest row dated today or earlier).
        $currentIds = WastePrice::query()
            ->whereDate('effective_date', '<=', today())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get(['id', 'waste_category_id'])
            ->groupBy('waste_category_id')
            ->map(fn ($rows) => $rows->last()->id)
            ->values()
            ->all();

        $categories = WasteCategory::orderBy('code')->get(['id', 'code', 'name']);

        return view('waste-prices.index', compact('prices', 'currentIds', 'categories'));
    }

    public function create(Request $request): View
    {
        $price = new WastePrice;
        $price->effective_date = today();
        $price->waste_category_id = $request->integer('category') ?: null;

        return view('waste-prices.form', [
            'price' => $price,
            'categoryOptions' => $this->categoryOptions(onlyActive: true),
        ]);
    }

    public function store(WastePriceRequest $request): RedirectResponse
    {
        $price = new WastePrice($request->validated());
        $price->created_by = $request->user()->id;
        $price->save();

        return redirect()->route('waste-prices.index', ['category' => $price->waste_category_id])
            ->with('status', __('Price was added.'));
    }

    public function edit(WastePrice $wastePrice): View
    {
        return view('waste-prices.form', [
            'price' => $wastePrice->load('category'),
            'categoryOptions' => [],
        ]);
    }

    public function update(WastePriceRequest $request, WastePrice $wastePrice): RedirectResponse
    {
        $wastePrice->update($request->validated());

        return redirect()->route('waste-prices.index', ['category' => $wastePrice->waste_category_id])
            ->with('status', __('Price was updated.'));
    }

    public function destroy(WastePrice $wastePrice): RedirectResponse
    {
        // Every category must keep at least one price, or deposits could not be valued.
        $remaining = WastePrice::where('waste_category_id', $wastePrice->waste_category_id)->count();

        if ($remaining <= 1) {
            return back()->with('error', __('A category must keep at least one price.'));
        }

        $wastePrice->delete();

        return back()->with('status', __('Price was deleted.'));
    }

    /** @return array<int, string> category id => "PLS - Plastik" */
    private function categoryOptions(bool $onlyActive): array
    {
        return WasteCategory::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (WasteCategory $c) => [$c->id => $c->code.' - '.$c->name])
            ->all();
    }
}
