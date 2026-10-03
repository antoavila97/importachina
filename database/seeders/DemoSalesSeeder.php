<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: corre en cada arranque del servidor, asi que si ya hay
        // pedidos no se vuelve a generar nada. Sin este guard, cada despliegue
        // sumaria 14 pedidos mas y el reporte de ventas quedaria inflado.
        if (Order::exists()) {
            $this->command?->info('Ya hay pedidos cargados: no se generan ventas de demo.');

            return;
        }

        $customers = User::whereHas('role', fn ($q) => $q->where('name', 'Cliente'))->get();

        if ($customers->isEmpty()) {
            $customers = User::factory()->cliente()->count(3)->create();
        }

        $catalog = Product::where('active', true)->get();

        if ($catalog->isEmpty()) {
            $catalog = Product::factory()->count(8)->create();
        }

        $statuses = [
            Order::STATUS_PAGADO,
            Order::STATUS_PAGADO,
            Order::STATUS_ENVIADO,
            Order::STATUS_ENTREGADO,
            Order::STATUS_ENTREGADO,
        ];

        foreach (range(1, 14) as $index) {
            $status = $statuses[$index % count($statuses)];
            $daysAgo = fake()->numberBetween(0, 25);
            $placedAt = now()->subDays($daysAgo)->setTime(fake()->numberBetween(9, 21), fake()->numberBetween(0, 59));

            $order = Order::create([
                'user_id' => $customers->random()->id,
                'status' => $status,
                'shipping_address' => fake()->address(),
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            $lines = $catalog->random(fake()->numberBetween(1, 3));
            $total = 0;

            foreach ($lines as $product) {
                $quantity = fake()->numberBetween(1, 4);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                ]);

                $total += $product->sale_price * $quantity;
            }

            $order->update(['total' => round($total, 2)]);

            if ($status !== Order::STATUS_PENDIENTE) {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => fake()->randomElement(['efectivo', 'transferencia', 'tarjeta', 'qr']),
                    'amount' => $order->total,
                    'status' => 'completed',
                    'paid_at' => $placedAt->copy()->addMinutes(fake()->numberBetween(5, 240)),
                ]);
            }
        }

        $this->command?->info('Ventas de demo generadas: '.Order::count().' pedidos.');
    }
}
