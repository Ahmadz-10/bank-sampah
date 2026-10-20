<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WasteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // gated by the can:manage-transactions route middleware
    }

    /**
     * The price per unit IS taken from the form here (selling prices are negotiated);
     * totals are not. Stock is checked in TransactionService, under a lock.
     */
    public function rules(): array
    {
        return [
            'buyer_name' => ['required', 'string', 'max:150'],
            'buyer_contact' => ['nullable', 'string', 'max:50'],
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            // Inactive categories are allowed: they may still hold stock to sell off.
            'lines.*.waste_category_id' => ['required', 'distinct', Rule::exists('waste_categories', 'id')],
            'lines.*.weight' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.price_per_unit' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lines' => __('lines'),
            'lines.*.waste_category_id' => __('category'),
            'lines.*.weight' => __('weight'),
            'lines.*.price_per_unit' => __('price per unit'),
        ];
    }
}
