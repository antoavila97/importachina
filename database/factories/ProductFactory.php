<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 5, 200);
        $margin = fake()->randomElement([20, 25, 30, 40, 50]);

        return [
            'category_id' => Category::factory(),
            'external_id' => fake()->unique()->numerify('AE-#####'),
            'title' => Str::title(fake()->unique()->words(3, true)),
            'description' => fake()->paragraph(),
            'cost_price' => $cost,
            'margin_pct' => $margin,
            'sale_price' => round($cost * (1 + $margin / 100), 2),
            'stock' => fake()->numberBetween(5, 200),
            // Sin imagen a proposito: la vista cae en el placeholder local
            // (public/images/placeholder.svg). fake()->imageUrl() apuntaba a
            // via.placeholder.com, que se apago en 2024, y dejaba todas las
            // imagenes rotas en la demo.
            'image_url' => null,
            'source_url' => 'https://www.aliexpress.com/item/'.fake()->numerify('#####'),
            'active' => true,
            'synced_at' => now(),
        ];
    }

    /**
     * Producto con una imagen concreta (URL externa, de la API de AliExpress).
     */
    public function conImagen(string $url): static
    {
        return $this->state(fn (array $attributes) => [
            'image_url' => $url,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
