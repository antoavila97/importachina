<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\DemoCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Reescribe en el sitio el catalogo de demo que creo la factory vieja.
 *
 * Los productos de la demo inicial tenian titles de Faker ("Sint Saepe
 * Ipsa") y categorias tambien de Faker ("Animi Magnam"). El DemoSalesSeeder
 * nuevo solo crea su catalogo en español cuando la tabla products esta
 * vacia, asi que en una base que ya tenia los productos viejos estos nunca se
 * reemplazaron y el catalogo seguia en latin.
 *
 * Se UPDATEan los productos en el sitio en vez de borrarlos y recrearlos
 * porque order_items los referencia: los pedidos de demo ya registrados
 * tienen que seguir apuntando a productos validos.
 *
 * Solo toca productos cuyo external_id empieza con AE-, que es el formato de
 * la factory. Los productos que vengan de la API de AliExpress traen el
 * product_id numerico y no se tocan nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $demos = Product::where('external_id', 'like', 'AE-%')->orderBy('id')->get();

        if ($demos->isEmpty()) {
            return;
        }

        foreach ($demos->values() as $i => $producto) {
            [$categoria, $titulo, $costo, $margen] = DemoCatalog::forIndex($i);

            $producto->update([
                'category_id' => $this->categoria($categoria)->id,
                'title' => $titulo,
                'description' => DemoCatalog::DESCRIPTION,
                'cost_price' => $costo,
                'margin_pct' => $margen,
                'sale_price' => round($costo * (1 + $margen / 100), 2),
                // Las URLs de la factory eran de via.placeholder.com (apagado
                // en 2024) y el source_url apuntaba a un item de AliExpress que
                // no existe. Con null la vista usa el placeholder local.
                'image_url' => null,
                'source_url' => null,
            ]);
        }

        $this->borrarCategoriasDeDemo();
    }

    public function down(): void
    {
        // No se puede deshacer: los titles originales eran de Faker.
    }

    private function categoria(string $nombre): Category
    {
        return Category::firstOrCreate(
            ['name' => $nombre],
            ['slug' => Category::uniqueSlug($nombre)]
        );
    }

    /**
     * Las categorias de Faker quedaron sin productos y solo mostraban ruido en
     * el filtro del catalogo. Solo se borran las que no referencia ningun
     * producto, asi que una categoria real nunca se pierde.
     */
    private function borrarCategoriasDeDemo(): void
    {
        Category::doesntHave('products')->delete();
    }
};
