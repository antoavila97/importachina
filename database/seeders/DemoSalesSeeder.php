<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->limpiarImagenesDeServiciosApagados();

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
            $catalog = $this->crearCatalogoDeDemo();
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

            // Sin el min(), Collection::random() lanza si se piden mas lineas
            // de las que hay en el catalogo: con un solo producto, pedir 2 o 3
            // reventaba el arranque del servidor.
            $lineas = min(3, max(1, $catalog->count()));
            $lines = $catalog->random(fake()->numberBetween(1, $lineas));
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

    /**
     * Los productos de la demo vieja guardan la URL de via.placeholder.com, un
     * servicio que se apago en 2024. Cada visita al catalogo los pedia y el
     * navegador esperaba un timeout antes de mostrar el placeholder.
     *
     * Poner image_url en null hace que la vista muestre el placeholder local de
     * una vez. Es idempotente (WHERE ... LIKE sobre lo que ya se limpio no
     * cambia nada) y va antes del early return, porque aunque no haya que crear
     * ventas la limpieza sigue siendo necesaria.
     */
    private function limpiarImagenesDeServiciosApagados(): void
    {
        $obsoletos = ['via.placeholder.com', 'placehold.co', 'placekitten.com', 'dummyimage.com'];

        $total = 0;

        foreach ($obsoletos as $host) {
            $total += Product::where('image_url', 'like', '%'.$host.'%')
                ->update(['image_url' => null]);
        }

        if ($total > 0) {
            $this->command?->info("Imagenes de servicios apagados limpiadas: {$total}.");
        }
    }

    /**
     * Catalogo de ejemplo con nombres reales de producto de importacion.
     *
     * Antes se usaba Product::factory(), que ponia titles de Faker del tipo
     * "Sint Saepe Ipsa" y categorias tambien de Faker. En una demo que se
     * presenta, eso se ve falso.
     *
     * Los productos quedan con image_url en null a proposito: la vista muestra
     * el placeholder local. Las imagenes de verdad llegan con la
     * sincronizacion de la API de AliExpress (HU-05).
     *
     * La lista vive en DemoCatalog porque la migracion que reescribe los
     * productos viejos en produccion necesita exactamente los mismos datos.
     *
     * @return Collection<int, Product>
     */
    private function crearCatalogoDeDemo()
    {
        $productos = collect();

        foreach (array_values(DemoCatalog::PRODUCTS) as $i => [$categoria, $titulo, $costo, $margen]) {
            $productos->push(Product::factory()->create([
                'category_id' => Category::firstOrCreate(
                    ['name' => $categoria],
                    ['slug' => Str::slug($categoria)]
                )->id,
                'external_id' => 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'title' => $titulo,
                'description' => DemoCatalog::DESCRIPTION,
                'cost_price' => $costo,
                'margin_pct' => $margen,
                'sale_price' => round($costo * (1 + $margen / 100), 2),
                'image_url' => null,
                'source_url' => null,
            ]));
        }

        $this->command?->info('Catalogo de demo: '.$productos->count().' productos.');

        return $productos;
    }
}
