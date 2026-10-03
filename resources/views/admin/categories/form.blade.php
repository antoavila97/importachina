@extends('layouts.app')

@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')

@section('content')
    @php
        $isEdit = $category->exists;
        $action = $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store');
    @endphp

    <div class="max-w-2xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ $isEdit ? 'Editar categoría' : 'Nueva categoría' }}</h1>
            <a href="{{ route('admin.categories.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                &larr; Volver al listado
            </a>
        </div>

        <x-flash-messages />

        <form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 space-y-4">
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

            <label class="block text-sm">
                <span class="block text-gray-600 mb-1">Nombre <span class="text-red-600">*</span></span>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                       maxlength="255" class="w-full border-gray-300 rounded" autofocus>
                <span class="text-xs text-gray-500">
                    El nombre debe ser único: es lo que el cliente ve en el filtro del catálogo.
                </span>
                @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="block text-gray-600 mb-1">Identificador externo</span>
                <input type="text" name="external_category_id"
                       value="{{ old('external_category_id', $category->external_category_id) }}"
                       maxlength="255" class="w-full border-gray-300 rounded">
                <span class="text-xs text-gray-500">
                    Lo completa la sincronización con la API de AliExpress.
                </span>
                @error('external_category_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            @if ($isEdit)
                <p class="text-sm text-gray-600">
                    Slug actual: <strong>{{ $category->slug }}</strong> (se recalcula al guardar).
                </p>
                <p class="text-sm text-gray-600">
                    Productos asignados: <strong>{{ $category->productsCount() }}</strong>.
                    Con productos asignados la categoría no se puede eliminar.
                </p>
            @endif

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded">
                    {{ $isEdit ? 'Guardar cambios' : 'Crear categoría' }}
                </button>
                <a href="{{ route('admin.categories.index') }}" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection
