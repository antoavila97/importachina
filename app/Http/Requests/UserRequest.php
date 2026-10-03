<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreating = $this->route('user') === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
            // HU-03: el administrador asigna el rol.
            'role_id' => ['required', Rule::exists(Role::class, 'id')],
            'status' => ['required', Rule::in(User::STATUSES)],
            'password' => [
                $isCreating ? 'required' : 'nullable',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'role_id.exists' => 'El rol seleccionado no existe.',
            'status.in' => 'El estado debe ser active o inactive.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        $data = [
            'name' => trim($this->validated()['name']),
            'email' => $this->validated()['email'],
            'role_id' => (int) $this->validated()['role_id'],
            'status' => $this->validated()['status'],
        ];

        // En la edición la contraseña solo cambia si se envía una nueva.
        if (! empty($this->validated()['password'])) {
            $data['password'] = $this->validated()['password'];
        }

        return $data;
    }
}
