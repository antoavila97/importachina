<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /* ------------------------------------------------------------------ */
    /* Acceso                                                              */
    /* ------------------------------------------------------------------ */

    public function test_un_visitante_no_puede_ver_ni_descargar_reportes(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.pdf', ['tipo' => 'pedidos']))->assertRedirect(route('login'));
    }

    public function test_el_cliente_no_puede_ver_ni_descargar_reportes(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($cliente)
            ->get(route('admin.reports.pdf', ['tipo' => 'usuarios']))
            ->assertForbidden();
    }

    public function test_el_vendedor_no_puede_ver_ni_descargar_reportes(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($vendedor)
            ->get(route('admin.reports.pdf', ['tipo' => 'pedidos']))
            ->assertForbidden();
    }

    public function test_el_enlace_de_reportes_solo_lo_ve_el_administrador(): void
    {
        $this->actingAs($this->admin())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Reportes');

        $this->actingAs(User::factory()->vendedor()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Reportes');

        $this->actingAs(User::factory()->cliente()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Reportes');
    }

    /* ------------------------------------------------------------------ */
    /* Pestañas                                                            */
    /* ------------------------------------------------------------------ */

    public function test_el_administrador_ve_las_cinco_pestanas(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Ventas')
            ->assertSee('Pedidos')
            ->assertSee('Productos')
            ->assertSee('Categorías')
            ->assertSee('Usuarios')
            ->assertSee('Descargar PDF');
    }

    public function test_cada_pestana_responde(): void
    {
        $admin = $this->admin();

        foreach (array_keys(\App\Http\Requests\ReportRequest::TYPES) as $tipo) {
            $this->actingAs($admin)
                ->get(route('admin.reports.index', ['tipo' => $tipo]))
                ->assertOk();
        }
    }

    /* ------------------------------------------------------------------ */
    /* PDF                                                                 */
    /* ------------------------------------------------------------------ */

    public function test_cada_reporte_se_descarga_en_pdf(): void
    {
        $admin = $this->admin();

        foreach (array_keys(\App\Http\Requests\ReportRequest::TYPES) as $tipo) {
            $response = $this->actingAs($admin)
                ->get(route('admin.reports.pdf', ['tipo' => $tipo]));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent(), "El PDF de {$tipo} no es válido");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Datos y filtros                                                     */
    /* ------------------------------------------------------------------ */

    public function test_el_reporte_de_pedidos_filtra_por_estado(): void
    {
        $admin = $this->admin();
        $cliente = User::factory()->cliente()->create();

        $pagado = Order::factory()->paid()->for($cliente)->create(['total' => 100]);
        $pendiente = Order::factory()->create([
            'user_id' => $cliente->id,
            'status' => Order::STATUS_PENDIENTE,
            'total' => 50,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tipo' => 'pedidos',
            'estado' => Order::STATUS_PENDIENTE,
        ]));

        $response->assertOk();
        $this->assertSame('1', $this->kpiValue($response->getContent(), 'Pedidos'));
        $response->assertSee('#'.$pendiente->id);
        $response->assertDontSee('#'.$pagado->id);
    }

    public function test_el_reporte_de_productos_filtra_por_categoria_y_estado(): void
    {
        $admin = $this->admin();
        $categoria = Category::factory()->create(['name' => 'Cat Uno']);
        $otra = Category::factory()->create(['name' => 'Cat Dos']);

        Product::factory()->create([
            'title' => 'Auriculares',
            'category_id' => $categoria->id,
            'active' => true,
        ]);
        Product::factory()->create([
            'title' => 'Reloj',
            'category_id' => $otra->id,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tipo' => 'productos',
            'categoria' => $categoria->id,
        ]));

        $response->assertOk();
        $response->assertSee('Auriculares');
        $response->assertDontSee('Reloj');
        $this->assertSame('1', $this->kpiValue($response->getContent(), 'Productos'));
    }

    public function test_el_reporte_de_usuarios_filtra_por_rol_y_estado(): void
    {
        $admin = $this->admin();
        User::factory()->cliente()->create(['name' => 'Carla Cliente']);
        User::factory()->vendedor()->create(['name' => 'Vero Vendedora']);
        User::factory()->cliente()->create(['name' => 'Inactivo Uno', 'status' => User::STATUS_INACTIVE]);

        $rolCliente = Role::where('name', 'Cliente')->firstOrFail()->id;

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tipo' => 'usuarios',
            'rol' => $rolCliente,
        ]));

        $response->assertOk();
        $response->assertSee('Carla Cliente');
        $response->assertDontSee('Vero Vendedora');

        $inactivos = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tipo' => 'usuarios',
            'situacion' => User::STATUS_INACTIVE,
        ]));

        $inactivos->assertOk();
        $inactivos->assertSee('Inactivo Uno');
        $inactivos->assertDontSee('Carla Cliente');
    }

    /* ------------------------------------------------------------------ */
    /* Validación                                                          */
    /* ------------------------------------------------------------------ */

    public function test_valida_el_tipo_de_reporte(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', ['tipo' => 'inventado']))
            ->assertSessionHasErrors('tipo');
    }

    public function test_valida_el_rango_de_fechas(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', [
                'tipo' => 'pedidos',
                'from' => '2026-05-10',
                'to' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    private function kpiValue(string $html, string $label): string
    {
        $this->assertSame(
            1,
            preg_match('/'.preg_quote($label, '/').'<\/p>\s*<p[^>]*>(.*?)<\/p>/s', $html, $matches),
            "No se encontro el KPI {$label}"
        );

        return trim($matches[1]);
    }
}
