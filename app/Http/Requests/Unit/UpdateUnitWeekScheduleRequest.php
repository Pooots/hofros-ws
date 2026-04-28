<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitWeekScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weekSchedule' => ['required', 'array'],
            'weekSchedule.mon' => ['required', 'boolean'],
            'weekSchedule.tue' => ['required', 'boolean'],
            'weekSchedule.wed' => ['required', 'boolean'],
            'weekSchedule.thu' => ['required', 'boolean'],
            'weekSchedule.fri' => ['required', 'boolean'],
            'weekSchedule.sat' => ['required', 'boolean'],
            'weekSchedule.sun' => ['required', 'boolean'],
        ];
    }
}
