<?php

namespace App\Http\Requests;

use App\Models\WasteCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WasteDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // gated by the can:manage-transactions route middleware
    }

    /**
     * Prices and totals are deliberately NOT accepted from the form: they are decided by
     * TransactionService from the price list.
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'deposit_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.waste_category_id' => ['required', 'distinct', Rule::exists('waste_categories', 'id')->where('is_active', true)],
            'lines.*.weight' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lines' => __('lines'),
            'lines.*.waste_category_id' => __('category'),
            'lines.*.weight' => __('weight'),
        ];
    }

    /** Every category must have a buy price in effect on the deposit date. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = $this->input('deposit_date');
            $categories = WasteCategory::query()
                ->with('prices')
                ->whereIn('id', collect($this->input('lines'))->pluck('waste_category_id'))
                ->get()
                ->keyBy('id');

            foreach ($this->input('lines') as $index => $line) {
                $category = $categories[$line['waste_category_id']];

                if ($category->priceOn($date) === null) {
                    $validator->errors()->add(
                        "lines.{$index}.waste_category_id",
                        __('No price is set for :category on :date.', [
                            'category' => $category->name,
                            'date' => \Illuminate\Support\Carbon::parse($date)->format('d/m/Y'),
                        ])
                    );
                }
            }
        });
    }
}
