<?php

namespace App\Http\Requests\TeamMember;

use App\Models\TeamMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
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
            'uuid' => [
                'required',
                'uuid',
                Rule::exists(TeamMember::class, 'uuid'),
            ],
            'role' => ['required', 'string', Rule::in(TeamMember::ROLES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('id') ?? $this->route('uuid'),
        ]);
    }
}
