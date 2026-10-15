<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\WasteSaleRequest;
use App\Models\WasteCategory;
use App\Models\WastePrice;
use App\Models\WasteSale;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WasteSaleController extends Controller
{
    public function __construct(
        private TransactionService $transactions,
        private StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));
        $voided = $request->input('status') === 'voided' && $request->user()->can('correct-transactions');

        $base = WasteSale::query()
            ->when($voided, fn ($query) => $query->onlyTrashed())
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('sale_number', 'like', "%{$term}%")
                ->orWhere('buyer_name', 'like', "%{$term}%")))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sale_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sale_date', '<=', $request->input('to')));

        $totals = (clone $base)->toBase()
            ->selectRaw('count(*) as cnt, coalesce(sum(total_weight), 0) as weight, coalesce(sum(total_amount), 0) as amount')
            ->first();

        $sales = $base->orderByDesc('sale_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // Current stock per category: active ones, plus any retired category that still holds stock.
        $levels = $this->stock->levels();
        $stockRows = WasteCategory::query()->orderBy('name')->get()
            ->map(fn (WasteCategory $c) => ['category' => $c, 'level' => (float) ($levels[$c->id] ?? 0.0)])
            ->filter(fn (array $row) => $row['category']->is_active || $row['level'] != 0.0)
            ->values();

        return view('waste-sales.index', compact('sales', 'totals', 'term', 'voided', 'stockRows'));
    }

    public function create(): View
    {
        $levels = $this->stock->levels();
        $categories = WasteCategory::query()->orderBy('code')->get(['id', 'code', 'name', 'unit', 'is_active']);

        return view('waste-sales.create', [
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->code} - {$c->name} ({$c->unit})", 'unit' => $c->unit])->values(),
            'priceTable' => $this->priceTable(),
            'stock' => $categories->mapWithKeys(fn ($c) => [$c->id => (float) ($levels[$c->id] ?? 0.0)])->all(),
            'initial' => old('lines', [[]]),
        ]);
    }

    public function store(WasteSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->transactions->createSale($request->validated(), $request->user());
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()->route('waste-sales.show', $sale)
            ->with('status', __('Sale :number was saved.', ['number' => $sale->sale_number]));
    }

    public function show(WasteSale $wasteSale): View
    {
        $wasteSale->load(['officer', 'details.category']);

        return view('waste-sales.show', ['sale' => $wasteSale]);
    }

    /** Void (soft delete); the weight returns to stock. Route middleware: can:correct-transactions. */
    public function destroy(WasteSale $wasteSale): RedirectResponse
    {
        $this->transactions->voidSale($wasteSale);

        return redirect()->route('waste-sales.index')
            ->with('status', __('Sale :number was voided.', ['number' => $wasteSale->sale_number]));
    }

    private function priceTable(): array
    {
        return WastePrice::query()
            ->orderBy('effective_date')
            ->get(['waste_category_id', 'buy_price', 'sell_price', 'effective_date'])
            ->groupBy('waste_category_id')
            ->map(fn ($rows) => $rows->map(fn (WastePrice $p) => [
                'date' => $p->effective_date->toDateString(),
                'buy' => (float) $p->buy_price,
                'sell' => $p->sell_price === null ? null : (float) $p->sell_price,
            ])->values())
            ->all();
    }
}
