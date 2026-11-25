<?php

namespace App\Http\Requests;

use App\Models\Centre;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('children.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $centreConfigured = Centre::query()->where('is_active', true)->exists();

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'external_registration_number' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'locality' => ['nullable', 'string', 'max:255'],
            'centre_id' => [Rule::requiredIf($centreConfigured), 'nullable', 'integer', Rule::exists('centres', 'id')->where('is_active', true), function ($attribute, $value, $fail): void {
                if ($value && $this->user()?->isCentreScoped() && ! $this->user()->centres()->whereKey($value)->exists()) {
                    $fail('Select one of your assigned vaccination centres.');
                }
            }],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'photograph' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
