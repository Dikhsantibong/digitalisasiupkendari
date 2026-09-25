<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceLocationRequest extends FormRequest
{
    /**
     * Authorisation is handled by the controller's permission check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Latitude and longitude are both set or both cleared.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'attendance_radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'attendance_radius_m' => 'radius',
        ];
    }
}
