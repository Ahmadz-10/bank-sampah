<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RwRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // gated by can:manage-master-data
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $rw = $this->route('rw');

        return [
            'number' => ['required', 'integer', 'between:1,99', Rule::unique('rws', 'number')->ignore($rw?->id)],
            'chairman_name' => ['nullable', 'string', 'max:150'],
            'household_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }
}
