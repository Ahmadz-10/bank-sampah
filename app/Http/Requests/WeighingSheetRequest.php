<?php

namespace App\Http\Requests;

use App\Models\Rw;
use App\Services\WeighingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WeighingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // gated by can:manage-weighings
    }

    public function rules(): array
    {
        $kg = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $count = ['nullable', 'integer', 'min:0', 'max:65000'];

        return [
            'year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'rows' => ['required', 'array', 'max:100'],
            'rows.*.organic_kg_per_day' => $kg,
            'rows.*.inorganic_kg_per_day' => $kg,
            'rows.*.b3_kg_per_day' => $kg,
            'rows.*.residue_kg_per_day' => $kg,
            'rows.*.carts' => $count,
            'rows.*.collectors' => $count,
            'rows.*.pickup_frequency' => $count,
            'rows.*.days_counted' => ['nullable', 'integer', 'between:1,31'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rows.*.organic_kg_per_day' => __('Organic (kg/day)'),
            'rows.*.inorganic_kg_per_day' => __('Inorganic (kg/day)'),
            'rows.*.b3_kg_per_day' => __('B3 (kg/day)'),
            'rows.*.residue_kg_per_day' => __('Residue (kg/day)'),
            'rows.*.carts' => __('Carts'),
            'rows.*.collectors' => __('Collectors'),
            'rows.*.pickup_frequency' => __('Pickup frequency'),
            'rows.*.days_counted' => __('Days counted'),
            'rows.*.notes' => __('Remarks'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $year = (int) $this->input('year');
            $month = (int) $this->input('month');

            // No weighings for a month that has not started.
            if ($year * 12 + $month > (int) today()->format('Y') * 12 + (int) today()->format('n')) {
                $validator->errors()->add('month', __('You cannot record a future month.'));

                return;
            }

            $known = Rw::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
            foreach (array_keys($this->input('rows')) as $rwId) {
                if (! in_array((string) $rwId, $known, true)) {
                    $validator->errors()->add('rows', __('Unknown RW in the sheet.'));

                    return;
                }
            }

            $days = WeighingService::daysIn($year, $month);
            foreach ($this->input('rows') as $rwId => $row) {
                if (! empty($row['days_counted']) && (int) $row['days_counted'] > $days) {
                    $validator->errors()->add("rows.{$rwId}.days_counted", __('This month has only :days days.', ['days' => $days]));
                }
            }
        });
    }
}
