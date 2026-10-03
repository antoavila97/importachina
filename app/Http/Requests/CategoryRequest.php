<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            // HU-06: cada categoría tiene nombre único.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Category::class, 'name')->ignore($categoryId),
            ],
            'external_category_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(Category::class, 'external_category_id')->ignore($categoryId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
            'external_category_id.unique' => 'Ese identificador externo ya está en uso.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryData(?int $ignoreId = null): array
    {
        $name = trim($this->validated()['name']);

        return [
            'name' => $name,
            'slug' => Category::uniqueSlug($name, $ignoreId),
            'external_category_id' => $this->validated()['external_category_id'] ?? null,
        ];
    }
}
