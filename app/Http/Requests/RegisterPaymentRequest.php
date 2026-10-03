<?php

namespace App\Http\Requests;

use App\Http\Controllers\Vendedor\PaymentController;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPaymentRequest extends FormRequest
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
        return [
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(PaymentController::METHODS))],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.in' => 'El metodo de pago no es valido.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ];
    }

    public function method(): string
    {
        return $this->validated()['method'];
    }

    public function amount(): ?float
    {
        $value = $this->validated()['amount'] ?? null;

        return $value === null ? null : round((float) $value, 2);
    }

    public function paidAt(): ?Carbon
    {
        $value = $this->validated()['paid_at'] ?? null;

        return $value ? Carbon::parse($value) : null;
    }
}
