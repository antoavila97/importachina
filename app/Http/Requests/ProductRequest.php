<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use App\Support\ImageUrl;
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

        // La portada y la galeria se guardan en su version original: el sufijo
        // de transformacion de AliExpress solo achica la imagen.
        $cover = $this->input('image_url');

        if (is_string($cover) && $cover !== '') {
            $this->merge(['image_url' => ImageUrl::upgrade($cover) ?? $cover]);
        }

        $images = $this->input('images');

        if (is_array($images)) {
            $images = array_values(array_filter(array_map(
                fn ($url) => is_string($url) ? trim($url) : null,
                $images,
            )));

            $this->merge([
                'images' => array_map(fn ($url) => ImageUrl::upgrade($url) ?? $url, $images),
            ]);
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
            'image_url' => ['nullable', 'url', 'max:2000'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            // HU-08: hasta 12 fotos adicionales, como las fichas de AliExpress.
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['required', 'string', 'url', 'max:2000'],
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
        $messages = [
            'title.required' => 'El producto necesita un título.',
            'cost_price.min' => 'El costo no puede ser negativo.',
            'margin_pct.max' => 'El margen no puede superar el 500%.',
            'image_url.url' => 'La URL de la imagen no es válida. Debe empezar con https://',
            'source_url.url' => 'La URL del producto no es válida. Debe empezar con https://',
            'image_url.max' => 'La URL de la imagen es demasiado larga (máximo 2000 caracteres).',
            'source_url.max' => 'La URL del producto es demasiado larga (máximo 2000 caracteres).',
            'external_id.unique' => 'Ya existe un producto con ese identificador externo.',
            'images.array' => 'Las imágenes deben enviarse como una lista.',
            'images.max' => 'Un producto puede tener hasta :max imágenes.',
        ];

        // Cada renglón de la galería se explica por su número.
        foreach (array_keys((array) $this->input('images', [])) as $index) {
            $number = ((int) $index) + 1;

            $messages["images.{$index}.required"] = "La imagen #{$number} está vacía.";
            $messages["images.{$index}.url"] = "La imagen #{$number} no es válida. Debe empezar con https://";
            $messages["images.{$index}.max"] = "La imagen #{$number} es demasiado larga (máximo 2000 caracteres).";
        }

        return $messages;
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
