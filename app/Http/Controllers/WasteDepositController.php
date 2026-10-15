<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\WasteDepositRequest;
use App\Models\Customer;
use App\Models\WasteCategory;
use App\Models\WastePrice;
use App\Models\WasteDeposit;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WasteDepositController extends Controller
{
    public function __construct(private TransactionService $transactions) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));
        // Only users who may void can look at voided deposits.
        $voided = $request->input('status') === 'voided' && $request->user()->can('correct-transactions');

        $base = WasteDeposit::query()
            ->when($voided, fn ($query) => $query->onlyTrashed())
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('deposit_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where(fn ($c) => $c
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('customer_code', 'like', "%{$term}%")))))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('deposit_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('deposit_date', '<=', $request->input('to')));

        // Totals for everything that matches the filters (not just this page).
        $totals = (clone $base)->toBase()
            ->selectRaw('count(*) as cnt, coalesce(sum(total_weight), 0) as weight, coalesce(sum(total_amount), 0) as amount')
            ->first();

        $deposits = $base
            ->with('customer:id,customer_code,name')
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('waste-deposits.index', compact('deposits', 'totals', 'term', 'voided'));
    }

    public function create(Request $request): View
    {
        $categories = WasteCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'unit']);

        $customerId = $request->integer('customer') ?: null;
        $initial = old('lines', [[]]);

        return view('waste-deposits.create', [
            'customers' => $this->customerOptions(),
            'customerId' => $customerId,
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->code} - {$c->name} ({$c->unit})", 'unit' => $c->unit])->values(),
            'priceTable' => $this->priceTable(),
            'initial' => $initial,
        ]);
    }

    public function store(WasteDepositRequest $request): RedirectResponse
    {
        $deposit = $this->transactions->createDeposit($request->validated(), $request->user());

        return redirect()->route('waste-deposits.show', $deposit)
            ->with('status', __('Deposit :number was saved.', ['number' => $deposit->deposit_number]));
    }

    public function show(WasteDeposit $wasteDeposit): View
    {
        $wasteDeposit->load(['customer', 'officer', 'details.category']);

        return view('waste-deposits.show', ['deposit' => $wasteDeposit]);
    }

    /** Void (soft delete). Route middleware: can:correct-transactions. */
    public function destroy(WasteDeposit $wasteDeposit): RedirectResponse
    {
        try {
            $this->transactions->voidDeposit($wasteDeposit);
        } catch (InsufficientStockException $e) {
            return back()->with('error', __('This deposit cannot be voided because part of it was already sold.').' '.$e->getMessage());
        }

        return redirect()->route('waste-deposits.index')
            ->with('status', __('Deposit :number was voided.', ['number' => $wasteDeposit->deposit_number]));
    }

    /** @return array<int, string> customer id => "NSB-0001 - Name" (active customers only) */
    private function customerOptions(): array
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('customer_code')
            ->get(['id', 'customer_code', 'name'])
            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->customer_code.' - '.$c->name])
            ->all();
    }

    /** category id => [{date, buy, sell}, ...] ordered by date: lets the form estimate prices live. */
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
