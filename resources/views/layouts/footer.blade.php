<footer class="mt-12 border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div class="flex items-center gap-2">
                <x-application-logo class="block h-7 w-auto text-indigo-600" />
                <p class="text-sm font-semibold text-gray-900">ImportaChina</p>
            </div>

            <p class="text-center text-sm text-gray-500 sm:text-right">
                {{ __('Proyecto de comercio electronico e importacion. Prices in bolivianos (Bs).') }}
            </p>

            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('catalog.index') }}"
                   class="text-gray-600 transition hover:text-indigo-700 hover:underline">
                    {{ __('Catalogo') }}
                </a>
                <a href="{{ route('login') }}"
                   class="text-gray-600 transition hover:text-indigo-700 hover:underline">
                    {{ __('Ingresar') }}
                </a>
            </nav>
        </div>
    </div>
</footer>