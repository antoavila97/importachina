<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => fake()->randomElement(['efectivo', 'transferencia', 'tarjeta', 'qr']),
            'amount' => fake()->randomFloat(2, 20, 2000),
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now()->subDays(fake()->numberBetween(0, 20)),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_VOIDED,
        ]);
    }
}
