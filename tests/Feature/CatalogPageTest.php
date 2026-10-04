<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la pagina publica del catalogo.
 *
 * El sitio se mostraba sin estilos en produccion y con todas las imagenes
 * rotas. Las dos causas estan cubiertas aca:
 *   - URLs de assets generadas con http:// en una pagina https (contenido
 *     mixto, el navegador bloqueaba el CSS y el JS).
 *   - Imagenes de demo apuntando a via.placeholder.com, servicio apagado en
 *     2024, que dejaba las tarjetas con solo el texto alt.
 */
class CatalogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_urls_de_assets_se_generan_por_https_detras_de_un_proxy(): void
    {
        Product::factory()->count(2)->create();

        // Railway termina el TLS y reenvia con X-Forwarded-Proto. Sin confiar en
        // el proxy, Laravel cree que la peticion llego por http y armaba los
        // assets con http:// en una pagina https: el navegador los bloqueaba y
        // la pagina salia sin estilos.
        $html = $this->get(
            route('catalog.index'),
            ['X-Forwarded-Proto' => 'https', 'HTTP_HOST' => 'importachina-production.up.railway.app']
        )->assertOk()->getContent();

        preg_match_all('/(?:href|src)="([^"]*\/build\/assets\/[^"]*)"/', $html, $matches);
        $assets = array_filter($matches[1] ?? []);

        $this->assertNotEmpty($assets, 'La pagina tiene que enlazar los assets compilados de Vite.');

        foreach ($assets as $asset) {
            $this->assertStringStartsWith(
                'https://',
                $asset,
                "El asset {$asset} se sirve por http y el navegador lo bloquearia por contenido mixto."
            );
        }
    }

    public function test_un_producto_sin_imagen_muestra_el_placeholder_local(): void
    {
        Product::factory()->create(['image_url' => null, 'title' => 'Producto sin foto']);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('images/placeholder.svg');
    }

    public function test_una_imagen_externa_que_falla_cae_al_placeholder(): void
    {
        Product::factory()->create([
            'image_url' => 'https://cdn.invalido.example/no-existe.jpg',
            'title' => 'Producto con imagen caida',
        ]);

        $html = $this->get(route('catalog.index'))->assertOk()->getContent();

        // El onerror cambia al placeholder si el CDN responde 403 o 404.
        $this->assertStringContainsString('onerror=', $html);
        $this->assertStringContainsString('images/placeholder.svg', $html);
    }

    public function test_no_deja_urls_de_servicios_de_imagen_apagados(): void
    {
        Product::factory()->count(10)->create();

        $html = $this->get(route('catalog.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('via.placeholder.com', $html);
        $this->assertStringNotContainsString('placehold.co', $html);
        $this->assertStringNotContainsString('placekitten.com', $html);
    }

    public function test_muestra_badge_de_agotado_y_no_deja_comprar(): void
    {
        Product::factory()->create(['stock' => 0, 'title' => 'Producto agotado']);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Producto agotado')
            ->assertSee('Agotado');
    }

    public function test_el_filtro_por_categoria_sigue_funcionando(): void
    {
        $electronica = Category::factory()->create(['name' => 'Electronica', 'slug' => 'electronica']);
        $hogar = Category::factory()->create(['name' => 'Hogar', 'slug' => 'hogar']);

        Product::factory()->create(['title' => 'Auriculares inalambricos', 'category_id' => $electronica->id]);
        Product::factory()->create(['title' => 'Organizador plegable', 'category_id' => $hogar->id]);

        $this->get(route('catalog.index', ['category' => 'electronica']))
            ->assertOk()
            ->assertSee('Auriculares inalambricos')
            ->assertDontSee('Organizador plegable');
    }

    public function test_la_busqueda_por_texto_sigue_funcionando(): void
    {
        Product::factory()->create(['title' => 'Power bank 20000mAh']);
        Product::factory()->create(['title' => 'Bolso tote de lona']);

        $this->get(route('catalog.index', ['q' => 'Power bank']))
            ->assertOk()
            ->assertSee('Power bank 20000mAh')
            ->assertDontSee('Bolso tote de lona');
    }

    public function test_muestra_el_estado_vacio_cuando_no_hay_coincidencias(): void
    {
        Product::factory()->create(['title' => 'Algo real']);

        $this->get(route('catalog.index', ['q' => 'no-existe-este-texto']))
            ->assertOk()
            ->assertSee('No se encontraron productos');
    }

    public function test_pagina_cuando_hay_mas_de_doce_productos(): void
    {
        Product::factory()->count(14)->create();

        // El total es de los 14, no de los 12 de la pagina: es el numero de
        // resultados que encontro la busqueda.
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('14 productos encontrados')
            ->assertSee('Mostrando')
            ->assertSee('page=2');

        // Pagina 2: los 2 restantes, y se anuncia el total, no los de la pagina.
        $this->get(route('catalog.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('14 productos encontrados')
            ->assertSee('Mostrando 13 a 14 de 14 productos');
    }

    public function test_el_pie_de_pagina_y_el_menu_no_dependen_del_rol(): void
    {
        $html = $this->get(route('catalog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Abrir menu', $html);
        $this->assertStringContainsString('Catalogo', $html);

        // Un admin ve los enlaces de admin en el nav y tambien el pie de pagina.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Reportes');
    }
}
