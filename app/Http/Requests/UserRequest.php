<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** True when a user is editing their own account (role and status are then locked). */
    public function editingSelf(): bool
    {
        $user = $this->route('user');

        return $user !== null && $user->is($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            // Role and status inputs are disabled when editing yourself, so they are not required then.
            'role_id' => $this->editingSelf()
                ? ['nullable']
                : ['required', Rule::exists('roles', 'id')],
            'is_active' => ['boolean'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
