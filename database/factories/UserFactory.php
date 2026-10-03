<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => User::STATUS_ACTIVE,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['name' => $name])->id,
        ]);
    }

    public function admin(): static
    {
        return $this->role('Administrador');
    }

    public function vendedor(): static
    {
        return $this->role('Vendedor');
    }

    public function cliente(): static
    {
        return $this->role('Cliente');
    }

    /**
     * Cliente con teléfono y dirección de envío ya cargados (HU-04).
     */
    public function conDireccion(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => '+591 70000000',
            'address' => 'Av. Siempre Viva 742, La Paz',
        ]);
    }
}
