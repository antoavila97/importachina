<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU-06: el administrador puede crear, editar y eliminar categorías,
 * y el nombre debe ser único.
 */
class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_el_administrador_ve_el_listado_de_categorias(): void
    {
        Category::factory()->create(['name' => 'Electronica']);

        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Categorias')
            ->assertSee('Electronica');
    }

    public function test_el_administrador_crea_una_categoria_y_genera_el_slug(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('Nueva categoría');

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => '  Ropa y Calzado  '])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Ropa y Calzado',
            'slug' => 'ropa-y-calzado',
        ]);
    }

    public function test_el_nombre_de_categoria_es_obligatorio_y_unico(): void
    {
        Category::factory()->create(['name' => 'Electronica']);

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Electronica'])
            ->assertSessionHasErrors('name');
    }

    public function test_el_administrador_renombra_una_categoria_y_el_slug_se_actualiza(): void
    {
        $category = Category::factory()->create(['name' => 'Vieja', 'slug' => 'vieja']);

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), ['name' => 'Nueva'])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertSame('Nueva', $category->fresh()->name);
        $this->assertSame('nueva', $category->fresh()->slug);
    }

    public function test_renombrar_una_categoria_no_choca_con_el_slug_de_otra(): void
    {
        Category::factory()->create(['name' => 'Ropa de invierno', 'slug' => 'ropa-de-invierno']);
        $category = Category::factory()->create(['name' => 'Cocina', 'slug' => 'cocina']);

        // Nombres distintos que generan el mismo slug (signos y mayusculas).
        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), ['name' => 'ROPA  de  Invierno!'])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertSame('ropa-de-invierno-2', $category->fresh()->slug);
    }

    public function test_una_categoria_sin_productos_se_elimina(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($category);
    }

    public function test_hu06_una_categoria_con_productos_no_se_puede_eliminar(): void
    {
        $category = Category::factory()->create(['name' => 'Electronica']);
        Product::factory()->for($category)->count(2)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($category);
    }

    public function test_el_listado_muestra_cuantos_productos_tiene_cada_categoria(): void
    {
        $category = Category::factory()->create(['name' => 'ConStock']);
        Product::factory()->for($category)->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('ConStock')
            ->assertSeeInOrder(['ConStock', '3']);
    }

    public function test_un_cliente_no_puede_gestionar_categorias(): void
    {
        $cliente = User::factory()->cliente()->create();
        $category = Category::factory()->create();

        $this->actingAs($cliente)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($cliente)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($cliente)->delete(route('admin.categories.destroy', $category))->assertForbidden();
    }

    public function test_la_ruta_show_de_categorias_no_existe(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.categories.show'));

        $this->actingAs($this->admin())
            ->get(route('admin.categories.index').'/'.Category::factory()->create()->id)
            ->assertStatus(405);
    }
}
