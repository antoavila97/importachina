@extends('layouts.app')

@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    @php
        $isEdit = $user->exists;
        $action = $isEdit ? route('admin.users.update', $user) : route('admin.users.store');
    @endphp

    <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ $isEdit ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                &larr; Volver al listado
            </a>
        </div>

        <x-flash-messages />

        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Datos de la cuenta</h2>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Nombre <span class="text-red-600">*</span></span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           maxlength="255" class="w-full border-gray-300 rounded">
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Email <span class="text-red-600">*</span></span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           maxlength="255" class="w-full border-gray-300 rounded">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="block text-gray-600 mb-1">
                            Contrasena {{ $isEdit ? '(dejalo vacio para no cambiarla)' : '' }}
                        </span>
                        <input type="password" name="password" autocomplete="new-password"
                               @required(! $isEdit)
                               class="w-full border-gray-300 rounded">
                        @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="block text-gray-600 mb-1">Confirmar contrasena</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                               @required(! $isEdit)
                               class="w-full border-gray-300 rounded">
                    </label>
                </div>
            </div>

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Rol y estado</h2>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Rol <span class="text-red-600">*</span></span>
                    <select name="role_id" required class="w-full border-gray-300 rounded">
                        <option value="">Selecciona un rol</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Estado <span class="text-red-600">*</span></span>
                    <select name="status" required class="w-full border-gray-300 rounded">
                        <option value="active" @selected(old('status', $user->status) === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Desactivado</option>
                    </select>
                    <span class="text-xs text-gray-500">
                        Un usuario desactivado no puede iniciar sesion.
                    </span>
                    @error('status') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                @if ($isEdit && $user->is(auth()->user()))
                    <p class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-3 py-2">
                        Estas editando tu propia cuenta: no podes desactivarte.
                    </p>
                @endif
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded">
                    {{ $isEdit ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection
