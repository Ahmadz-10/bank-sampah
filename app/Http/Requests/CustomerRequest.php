<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'household_id' => ['nullable', Rule::exists('households', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            // NIK is optional; when given it must be 16 digits and not used by another customer.
            'id_number' => [
                'nullable', 'digits:16',
                Rule::unique('customers', 'id_number')->whereNull('deleted_at')->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'joined_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }
}
