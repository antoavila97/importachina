<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // HU-04: el cliente puede cambiar su teléfono y su dirección de envío.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El teléfono solo puede tener números, espacios y los signos + - ( ).',
            'address.max' => 'La dirección no puede superar los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function profileData(): array
    {
        $data = $this->safe()->only(['name', 'email', 'phone', 'address']);

        // Un campo vacío se guarda como null, no como cadena en blanco.
        return array_map(
            fn (?string $value): ?string => $value === null ? null : trim($value),
            $data,
        );
    }
}
