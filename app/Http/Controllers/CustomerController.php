<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));

        $customers = Customer::query()
            ->with('household:id,household_code,head_name')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(fn ($q) => $q
                    ->where('customer_code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderBy('customer_code')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'term'));
    }

    public function create(): View
    {
        return view('customers.form', [
            'customer' => new Customer,
            'householdOptions' => $this->householdOptions(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('status', __('Customer :code was created.', ['code' => $customer->customer_code]));
    }

    /** Detail page: customer data, household, and deposit history with totals. */
    public function show(Customer $customer): View
    {
        $customer->load('household');

        $deposits = $customer->deposits()
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $totals = $customer->deposits()
            ->toBase()
            ->selectRaw('count(*) as cnt, coalesce(sum(total_weight), 0) as weight, coalesce(sum(total_amount), 0) as amount')
            ->first();

        return view('customers.show', compact('customer', 'deposits', 'totals'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', [
            'customer' => $customer,
            'householdOptions' => $this->householdOptions(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('status', __('Customer :code was updated.', ['code' => $customer->customer_code]));
    }

    /** Soft delete. Customers with transactions (Phase 4) keep their history. */
    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('status', __('Customer :code was deleted.', ['code' => $customer->customer_code]));
    }

    /** @return array<int, string> household id => "RMH-0001 - Head name" */
    private function householdOptions(): array
    {
        return Household::query()
            ->orderBy('household_code')
            ->get(['id', 'household_code', 'head_name'])
            ->mapWithKeys(fn (Household $h) => [$h->id => $h->household_code.' - '.$h->head_name])
            ->all();
    }
}
