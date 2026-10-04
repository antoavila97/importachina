<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HU-02 — Como Cliente, quiero iniciar y cerrar sesión
 * para acceder a mi cuenta de forma segura.
 *
 * Criterios de aceptación de la guía 4.3:
 *   1. "Con credenciales correctas entro a mi cuenta; con incorrectas veo un
 *       mensaje de error claro."
 *   2. "Al cerrar sesión no puedo volver a las páginas privadas."
 *
 * Los tests de Breeze solo comprueban assertAuthenticated/assertGuest. Eso no
 * prueba el mensaje de error ni que las páginas privadas realmente bloqueen.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Páginas privadas de los 7 módulos, con el rol que sí puede abrirlas.
     *
     * Sin sesión ninguna debe abrir: todas rebotan al login. Y con sesión solo
     * abren para el rol que corresponde — un Cliente recibe 403 en las de rol,
     * y eso tambien es "página cerrada".
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rutasPrivadas(): array
    {
        return [
            'dashboard' => ['/dashboard', 'cliente'],
            'carrito' => ['/carrito', 'cliente'],
            'mis pedidos' => ['/mis-pedidos', 'cliente'],
            'perfil' => ['/profile', 'cliente'],
            'pedidos del vendedor' => ['/vendedor/pedidos', 'vendedor'],
            'panel de admin' => ['/admin/usuarios', 'admin'],
            'reportes' => ['/admin/reportes', 'admin'],
        ];
    }

    // ------------------------------------------------------------------
    // Criterio 1: credenciales correctas / incorrectas con mensaje claro
    // ------------------------------------------------------------------

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->cliente()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHasNoErrors();
    }

    public function test_el_acceso_exitoso_lleva_a_una_pagina_que_responde(): void
    {
        $user = User::factory()->cliente()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // No basta con que redirected: la pagina de destino tiene que abrir de verdad.
        $this->get(route('dashboard'))->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->cliente()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_la_contrasena_incorrecta_muestra_un_mensaje_claro(): void
    {
        $user = User::factory()->cliente()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'esta-es-la-contrasena-equivocada',
        ]);

        // Criterio 1: "con incorrectas veo un mensaje de error claro".
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $follow = $this->followingRedirects()->post('/login', [
            'email' => $user->email,
            'password' => 'otra-contrasena-equivocada',
        ]);

        $follow->assertStatus(200);
        // Se asserta el mensaje de __() y no un literal: lo que el criterio exige
        // es que el usuario VE un error, no que diga una frase en concreto.
        $follow->assertSee(__('auth.failed'));
        $this->assertGuest();
    }

    public function test_un_correo_inexistente_muestra_el_mismo_mensaje(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nadie@importachina.com',
            'password' => 'importachina2026',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nadie@importachina.com']);
    }

    public function test_un_usuario_desactivado_no_puede_entrar(): void
    {
        // HU-03 permite desactivar usuarios; una cuenta desactivada no debe
        // poder iniciar sesion aunque la contrasena sea correcta.
        $user = User::factory()->cliente()->create(['status' => User::STATUS_INACTIVE]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Criterio 2: "Al cerrar sesión no puedo volver a las páginas privadas."
    // ------------------------------------------------------------------

    public function test_users_can_logout(): void
    {
        $user = User::factory()->cliente()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    #[DataProvider('rutasPrivadas')]
    public function test_las_paginas_privadas_responden_403_sin_sesion(string $ruta): void
    {
        // Un visitante sin sesion debe rebotar al login (302), no ver la pagina.
        $this->get($ruta)->assertRedirect(route('login'));
    }

    #[DataProvider('rutasPrivadas')]
    public function test_las_paginas_privadas_siguen_cerradas_despues_de_cerrar_sesion(string $ruta, string $rol): void
    {
        $user = $this->usuarioConRol($rol);

        // Con sesion y el rol correcto la pagina abre de verdad...
        $this->actingAs($user)->get($ruta)->assertStatus(200);

        // ...y al cerrar sesion vuelve a estar cerrada. Sin esta segunda mitad,
        // un logout mal hecho dejaria la sesion viva y el criterio 2 de HU-02
        // ("al cerrar sesion no puedo volver a las paginas privadas") no se
        // estaria comprobando.
        $this->actingAs($user)->post('/logout');

        $this->get($ruta)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function usuarioConRol(string $rol): User
    {
        return match ($rol) {
            'admin' => User::factory()->admin()->create(),
            'vendedor' => User::factory()->vendedor()->create(),
            default => User::factory()->cliente()->create(),
        };
    }
}
