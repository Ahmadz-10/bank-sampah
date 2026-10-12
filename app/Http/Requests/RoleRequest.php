<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the display label and description are editable. The role `name` (slug) is
     * referenced by config/permissions.php, so it is never changed from the UI.
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
