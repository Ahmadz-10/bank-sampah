<?php

namespace App\Http\Requests;

use App\Models\WastePrice;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WastePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The category cannot be changed when editing a price row; keep the existing one.
        if ($price = $this->route('waste_price')) {
            $this->merge(['waste_category_id' => $price->waste_category_id]);
        }
    }

    public function rules(): array
    {
        $price = $this->route('waste_price');

        return [
            'waste_category_id' => ['required', Rule::exists('waste_categories', 'id')],
            'buy_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'effective_date' => [
                'required', 'date',
                // One price per category per date. Done with whereDate() so it behaves the
                // same on MariaDB and SQLite.
                function (string $attribute, mixed $value, Closure $fail) use ($price) {
                    $duplicate = WastePrice::query()
                        ->where('waste_category_id', $this->input('waste_category_id'))
                        ->whereDate('effective_date', $value)
                        ->when($price, fn ($query) => $query->where('id', '!=', $price->id))
                        ->exists();

                    if ($duplicate) {
                        $fail(__('This category already has a price for that date.'));
                    }
                },
            ],
        ];
    }
}
