<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): Carbon
    {
        $value = $this->validated()['from'] ?? null;

        return $value
            ? Carbon::parse($value)->startOfDay()
            : now()->subDays(29)->startOfDay();
    }

    public function to(): Carbon
    {
        $value = $this->validated()['to'] ?? null;

        return $value
            ? Carbon::parse($value)->endOfDay()
            : now()->endOfDay();
    }

    public function days(): int
    {
        return (int) $this->from()->diffInDays($this->to()) + 1;
    }
}
