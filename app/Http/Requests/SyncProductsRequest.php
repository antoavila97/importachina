<?php

namespace App\Http\Requests;

use App\Console\Commands\SyncAliExpressProducts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncProductsRequest extends FormRequest
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
            'keyword' => ['nullable', 'string', 'max:120'],
            'limit' => [
                'required',
                'integer',
                'min:'.SyncAliExpressProducts::MIN_LIMIT,
                'max:'.SyncAliExpressProducts::MAX_LIMIT,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'limit.required' => 'Indicar cuántos productos importar.',
            'limit.min' => 'Por criterio de aceptación hay que importar al menos '.SyncAliExpressProducts::MIN_LIMIT.' productos.',
            'limit.max' => 'El máximo por sincronización es '.SyncAliExpressProducts::MAX_LIMIT.' productos.',
        ];
    }

    public function keyword(): ?string
    {
        // validated() solo trae las claves que venian en el request: si el
        // cliente manda solo limit, keyword no existe y hay que tolerarlo.
        $keyword = trim((string) ($this->validated()['keyword'] ?? ''));

        return $keyword !== '' ? $keyword : null;
    }

    public function limit(): int
    {
        return (int) $this->validated()['limit'];
    }
}
