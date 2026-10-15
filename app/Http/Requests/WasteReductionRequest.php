<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Only the target and notes are typed; the weights and percentage are calculated. */
class WasteReductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // gated by can:manage-monitoring
    }

    public function rules(): array
    {
        return [
            'target_percentage' => ['required', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
