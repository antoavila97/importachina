<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU-03: el administrador puede crear, editar, asignar el rol,
 * desactivar y eliminar usuarios.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Torres',
            'email' => 'ana@importachina.com',
            'role_id' => Role::firstOrCreate(['name' => 'Cliente'])->id,
            'status' => User::STATUS_ACTIVE,
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ], $overrides);
    }

    public function test_el_administrador_ve_el_listado_de_usuarios(): void
    {
        User::factory()->cliente()->create(['name' => 'Luis Perez']);

        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertSee('Luis Perez')
            ->assertSee('Cliente');
    }

    public function test_el_listado_se_filtra_por_nombre_rol_y_estado(): void
    {
        $this->actingAs($this->admin());

        $cliente = User::factory()->cliente()->create(['name' => 'Luis Perez']);
        $vendedor = User::factory()->vendedor()->create(['name' => 'Sara Gomez']);

        $this->get(route('admin.users.index', ['search' => 'Sara']))
            ->assertOk()
            ->assertSee('Sara Gomez')
            ->assertDontSee('Luis Perez');

        $this->get(route('admin.users.index', ['role' => $cliente->role_id]))
            ->assertOk()
            ->assertSee('Luis Perez')
            ->assertDontSee('Sara Gomez');

        $vendedor->update(['status' => User::STATUS_INACTIVE]);

        $this->get(route('admin.users.index', ['status' => User::STATUS_INACTIVE]))
            ->assertOk()
            ->assertSee('Sara Gomez')
            ->assertSee('Desactivado');
    }

    public function test_el_administrador_crea_un_usuario_con_rol(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['role_id' => Role::firstOrCreate(['name' => 'Vendedor'])->id]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'ana@importachina.com')->first();

        $this->assertNotNull($user, 'El usuario no se guardo.');
        $this->assertTrue($user->hasRole('Vendedor'));
        $this->assertTrue($user->isActive());
    }

    public function test_el_formulario_exige_rol_estado_y_password_confirmada(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'role_id', 'status', 'password']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['password_confirmation' => 'otra-cosa']))
            ->assertSessionHasErrors('password');
    }

    public function test_no_se_puede_crear_un_usuario_con_un_email_repetido(): void
    {
        User::factory()->cliente()->create(['email' => 'ana@importachina.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_el_administrador_editar_un_usuario_y_cambiar_su_rol(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cliente()->create(['name' => 'Nombre viejo']);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Editar usuario')
            ->assertSee('Nombre viejo');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->payload([
                'name' => 'Nombre nuevo',
                'email' => $user->email,
                'role_id' => Role::firstOrCreate(['name' => 'Administrador'])->id,
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSame('Nombre nuevo', $user->fresh()->name);
        $this->assertTrue($user->fresh()->hasRole('Administrador'));
    }

    public function test_la_edicion_no_exige_password_pero_sigue_cambiandola_si_se_envia(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cliente()->create();
        $original = $user->password;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->payload([
                'email' => $user->email,
                'password' => null,
                'password_confirmation' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($original, $user->fresh()->password);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->payload([
                'email' => $user->email,
                'password' => 'nuevaclave',
                'password_confirmation' => 'nuevaclave',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotSame($original, $user->fresh()->password);
    }

    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->payload([
                'email' => $admin->email,
                'status' => User::STATUS_INACTIVE,
            ]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_el_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($admin);
    }

    public function test_un_usuario_sin_pedidos_se_elimina(): void
    {
        $user = User::factory()->cliente()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($user);
    }

    public function test_un_usuario_con_pedidos_se_desactiva_en_vez_de_eliminarse(): void
    {
        $user = User::factory()->cliente()->create();
        Order::factory()->for($user)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('warning');

        $user->refresh();

        $this->assertTrue($user->exists);
        $this->assertFalse($user->isActive());
    }

    public function test_un_cliente_no_puede_llegar_al_crud_de_usuarios(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($cliente)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->actingAs($cliente)->delete(route('admin.users.destroy', $cliente))->assertForbidden();
    }

    public function test_la_ruta_show_de_usuarios_no_existe(): void
    {
        $user = User::factory()->cliente()->create();

        $this->assertNull(
            Route::getRoutes()->getByName('admin.users.show'),
            'La ruta admin.users.show no deberia existir: el resource se registro con except("show").'
        );

        $this->actingAs($this->admin())
            ->get(route('admin.users.index')."/{$user->id}")
            ->assertStatus(405);
    }

    public function test_la_edicion_carga_el_usuario_del_enlace(): void
    {
        $user = User::factory()->cliente()->create(['name' => 'Nombre desde el enlace']);

        $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Nombre desde el enlace');
    }
}
