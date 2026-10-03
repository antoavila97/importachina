<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'integer', 'exists:roles,id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', User::STATUSES)],
        ]);

        $users = User::query()
            ->with('role')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['status' => User::STATUS_ACTIVE]),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->userData();
        $data['email_verified_at'] = now();

        $user = User::create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuario {$user->email} creado con el rol {$user->role->name}");
    }

    public function edit(User $user): View
    {
        $user->load('role');

        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        // Evita que el administrador se quede sin acceso a sí mismo.
        if ($user->is($request->user()) && $request->userData()['status'] === User::STATUS_INACTIVE) {
            return redirect()
                ->route('admin.users.edit', $user)
                ->with('error', 'No podés desactivar tu propia cuenta.');
        }

        $user->update($request->userData());

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuario {$user->email} actualizado");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'No podés eliminar tu propia cuenta.');
        }

        // El usuario tiene pedidos: se conserva el historial y se desactiva.
        if ($user->orders()->exists()) {
            $user->update(['status' => User::STATUS_INACTIVE]);

            return redirect()
                ->route('admin.users.index')
                ->with(
                    'warning',
                    "{$user->email} tiene pedidos registrados: se desactivó en vez de eliminarse."
                );
        }

        $email = $user->email;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuario {$email} eliminado");
    }
}
