@extends('layouts.app')

@use('App\Models\User')

@section('title', 'Usuarios')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Usuarios</h1>
                <p class="text-sm text-gray-600">{{ $users->total() }} usuario(s) registrados</p>
            </div>

            <a href="{{ route('admin.users.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded text-sm">
                Nuevo usuario
            </a>
        </div>

        <x-flash-messages />

        <form method="GET" action="{{ route('admin.users.index') }}" class="bg-white rounded shadow p-4 mb-6">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Buscar</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Nombre o email" class="w-full border-gray-300 rounded">
                </label>

                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Rol</span>
                    <select name="role" class="w-full border-gray-300 rounded">
                        <option value="">Todos</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(($filters['role'] ?? null) == $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Estado</span>
                    <select name="status" class="w-full border-gray-300 rounded">
                        <option value="">Todos</option>
                        @foreach (User::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>
                                {{ $status === User::STATUS_ACTIVE ? 'Activo' : 'Desactivado' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Filtrar</button>
                    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm">
                        Limpiar
                    </a>
                </div>
            </div>

            @error('role') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </form>

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Usuario</th>
                        <th class="px-4 py-2 text-left">Rol</th>
                        <th class="px-4 py-2 text-left">Estado</th>
                        <th class="px-4 py-2 text-left">Registrado</th>
                        <th class="px-4 py-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-t">
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center shrink-0">
                                        {{ $user->initials() }}
                                    </span>
                                    <div>
                                        <p class="font-medium">
                                            {{ $user->name }}
                                            @if ($user->is(auth()->user()))
                                                <span class="text-xs text-gray-500">(vos)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2">{{ $user->role?->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $user->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $user->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm">Editar</a>

                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm('¿Eliminar a {{ $user->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-gray-600">
                                No hay usuarios que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
@endsection
