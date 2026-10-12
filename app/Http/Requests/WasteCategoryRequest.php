<?php

namespace App\Http\Requests;

use App\Models\WasteCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WasteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $category = $this->route('waste_category');

        $rules = [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('waste_categories', 'code')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', Rule::in(array_keys(WasteCategory::UNITS))],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];

        // Only when creating: the first price row is created together with the category.
        // Afterwards prices are managed on the Waste Prices page.
        if ($this->isMethod('post')) {
            $rules += [
                'buy_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
                'sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'effective_date' => ['required', 'date'],
            ];
        }

        return $rules;
    }
}
