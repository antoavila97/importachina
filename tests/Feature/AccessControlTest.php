<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public static function adminRoutes(): array
    {
        return [
            'reportes' => ['admin.reports.index'],
            'sincronizacion api' => ['admin.api-sync.index'],
            'categorias' => ['admin.categories.index'],
            'usuarios' => ['admin.users.index'],
            'productos' => ['admin.products.index'],
        ];
    }

    public static function vendedorRoutes(): array
    {
        return [
            'lista de pedidos' => ['vendedor.orders.index'],
        ];
    }

    #[DataProvider('adminRoutes')]
    #[DataProvider('vendedorRoutes')]
    public function test_un_visitante_es_enviado_al_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('adminRoutes')]
    #[DataProvider('vendedorRoutes')]
    public function test_un_cliente_recibe_403(string $routeName): void
    {
        $this->actingAs(User::factory()->cliente()->create())
            ->get(route($routeName))
            ->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    #[DataProvider('vendedorRoutes')]
    public function test_un_administrador_no_es_bloqueado_por_el_middleware_de_rol(string $routeName): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route($routeName));

        $this->assertNotSame(403, $response->status(), "El middleware de rol bloqueo al administrador en {$routeName}");
    }

    #[DataProvider('adminRoutes')]
    public function test_un_vendedor_no_puede_entrar_al_panel_de_administrador(string $routeName): void
    {
        $this->actingAs(User::factory()->vendedor()->create())
            ->get(route($routeName))
            ->assertForbidden();
    }

    public function test_el_visitante_puede_ver_el_catalogo_sin_iniciar_sesion(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Catálogo de productos');
    }

    public function test_el_visitante_no_puede_gestionar_su_carrito(): void
    {
        $this->get(route('cart.index'))->assertRedirect(route('login'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_los_enlaces_de_admin_solo_aparecen_para_administradores(): void
    {
        $this->actingAs(User::factory()->cliente()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Reportes')
            ->assertDontSee('Sincronización API');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Reportes')
            ->assertSee('Sincronización API');
    }
}
