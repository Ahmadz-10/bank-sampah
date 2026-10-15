<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HouseholdSurveyRequest extends FormRequest
{
    /**
     * Route middleware already requires `survey-households`. When editing, also enforce the
     * workflow rule: validated surveys are locked, and officers only change their own.
     */
    public function authorize(): bool
    {
        $survey = $this->route('household_survey');

        return $survey === null || $survey->canBeModifiedBy($this->user());
    }

    protected function prepareForValidation(): void
    {
        // A survey cannot be moved to another household: keep the existing one when editing.
        if ($survey = $this->route('household_survey')) {
            $this->merge(['household_id' => $survey->household_id]);
        }

        $this->merge(['remove_photo' => $this->boolean('remove_photo')]);
    }

    public function rules(): array
    {
        return [
            'household_id' => ['required', Rule::exists('households', 'id')->whereNull('deleted_at')],
            'survey_date' => ['required', 'date', 'before_or_equal:today'],
            'sorting_status' => ['required', Rule::in([Household::STATUS_SORTING, Household::STATUS_NOT_SORTING])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['boolean'],
        ];
    }
}
