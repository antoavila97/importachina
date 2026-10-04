<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HU-01 — Como Cliente, quiero registrarme con mi correo y una contraseña
 * para poder comprar en la tienda.
 *
 * Criterios de aceptación de la guía 4.3:
 *   1. "El formulario valida correo único y contraseña de mínimo 8 caracteres."
 *   2. "Al registrarme, mi cuenta queda con el rol Cliente."
 *
 * Los tests de Breeze que trae Laravel solo comprueban que la pantalla
 * responde 200 y que el usuario queda autenticado: eso NO prueba ninguno de
 * los dos criterios. Estos assertan el efecto real sobre la base.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Con RefreshDatabase los seeders NO corren. Sin roles en la base el
        // registro no podría asignar ninguno, así que el escenario debe sembrarlos.
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kaleigh Salazar',
            'email' => 'kaleigh@importachina.com',
            'password' => 'importachina2026',
            'password_confirmation' => 'importachina2026',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'kaleigh@importachina.com']);
    }

    // ------------------------------------------------------------------
    // Criterio 2: "Al registrarme, mi cuenta queda con el rol Cliente."
    // ------------------------------------------------------------------

    public function test_el_registro_publico_asigna_el_rol_cliente(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'kaleigh@importachina.com')->sole();

        $this->assertNotNull($user->role_id, 'El usuario quedo sin rol: HU-01 no se cumple.');
        $this->assertSame('Cliente', $user->role->name);
        $this->assertTrue($user->hasRole('Cliente'));
        $this->assertFalse($user->hasRole('Administrador'));
        $this->assertFalse($user->hasRole('Vendedor'));
    }

    public function test_el_registro_publico_no_puede_escalar_a_otro_rol(): void
    {
        // Aunque el POST includa role_id a mano, el registro publico siempre
        // deja al usuario como Cliente: no hay escalamiento de privilegios.
        $this->post('/register', $this->payload([
            'role_id' => Role::where('name', 'Administrador')->value('id'),
        ]));

        $user = User::where('email', 'kaleigh@importachina.com')->sole();

        $this->assertTrue($user->hasRole('Cliente'));
        $this->assertFalse($user->hasRole('Administrador'));
    }

    public function test_el_usuario_registrado_queda_activo_y_puede_comprar(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'kaleigh@importachina.com')->sole();

        $this->assertTrue($user->isActive(), 'Un usuario recien registrado debe quedar activo.');

        // Auth::login() deja el modelo recien creado en el guard, todavia sin
        // recargar. Si create() no fija 'status', el objeto en memoria queda con
        // status = null aunque la fila tenga 'active' (ver UserFactory) y
        // RoleMiddleware aborta con 403 al primer pagina con filtro de rol.
        $this->assertTrue(
            Auth::user()->isActive(),
            'El usuario en memoria quedo sin status: el middleware de rol lo rechazaria.'
        );

        // El criterio dice "para poder comprar en la tienda": el carrito y el
        // historial son privados, asi que deben responder 200 y no 302 al login.
        $this->actingAs($user)->get('/carrito')->assertStatus(200);
        $this->actingAs($user)->get('/mis-pedidos')->assertStatus(200);
    }

    public function test_el_usuario_registrado_no_entra_al_panel_de_administracion(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'kaleigh@importachina.com')->sole();

        $this->actingAs($user)->get('/admin/usuarios')->assertStatus(403);
        $this->actingAs($user)->get('/vendedor/pedidos')->assertStatus(403);
    }

    public function test_el_registro_reutiliza_el_rol_cliente_sin_duplicarlo(): void
    {
        $this->post('/register', $this->payload());
        $this->post('/logout');

        $this->post('/register', $this->payload(['email' => 'otro@importachina.com']));

        $this->assertSame(1, Role::where('name', 'Cliente')->count());
        $this->assertTrue(
            User::where('email', 'otro@importachina.com')->sole()->hasRole('Cliente')
        );
    }

    // ------------------------------------------------------------------
    // Criterio 1: "El formulario valida correo único y contraseña de
    //             mínimo 8 caracteres."
    // ------------------------------------------------------------------

    public function test_el_correo_debe_ser_unico(): void
    {
        User::factory()->create(['email' => 'kaleigh@importachina.com']);

        $response = $this->from('/register')->post('/register', $this->payload());

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'kaleigh@importachina.com')->count());
    }

    public function test_la_contrasena_exige_minimo_8_caracteres(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            'password' => 'corta1',
            'password_confirmation' => 'corta1',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'kaleigh@importachina.com']);
    }

    public function test_la_contrasena_de_exactamente_8_caracteres_se_acepta(): void
    {
        $this->post('/register', $this->payload([
            'password' => 'ochopost',
            'password_confirmation' => 'ochopost',
        ]));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'kaleigh@importachina.com']);
    }

    public function test_la_confirmacion_de_contrasena_es_obligatoria(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Kaleigh Salazar',
            'email' => 'kaleigh@importachina.com',
            'password' => 'importachina2026',
            'password_confirmation' => 'otra-cosa-2026',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_correo_invalido_se_rechaza(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            'email' => 'esto-no-es-un-correo',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_contrasena_se_guarda_hasheada(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'kaleigh@importachina.com')->sole();

        $this->assertNotSame('importachina2026', $user->password);
        $this->assertTrue(Hash::check('importachina2026', $user->password));
    }
}
