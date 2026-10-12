<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HouseholdRequest extends FormRequest
{
    /** Access is already restricted by the `can:manage-households` route middleware. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head_name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:20'],
            'member_count' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
