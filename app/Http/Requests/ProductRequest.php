<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['image_url', 'source_url'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', Rule::exists(Category::class, 'id')],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // HU-07: se define el margen, no el precio de venta.
            'margin_pct' => ['required', 'numeric', 'min:0', 'max:500'],
            'price_locked' => ['nullable', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'active' => ['required', 'boolean'],
            'external_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(Product::class, 'external_id')->ignore($productId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El producto necesita un título.',
            'cost_price.min' => 'El costo no puede ser negativo.',
            'margin_pct.max' => 'El margen no puede superar el 500%.',
            'image_url.url' => 'La URL de la imagen no es válida. Debe empezar con https://',
            'source_url.url' => 'La URL del producto no es válida. Debe empezar con https://',
            'external_id.unique' => 'Ya existe un producto con ese identificador externo.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $data = $this->validated();

        $data['category_id'] = $data['category_id'] ?? null;
        $data['external_id'] = $data['external_id'] ?? null;
        $data['cost_price'] = round((float) $data['cost_price'], 2);
        $data['margin_pct'] = round((float) $data['margin_pct'], 2);
        $data['price_locked'] = $this->boolean('price_locked');
        $data['stock'] = (int) $data['stock'];
        $data['active'] = $this->boolean('active');

        // sale_price siempre se deriva: nunca se acepta desde el formulario.
        unset($data['sale_price']);

        return $data;
    }
}
