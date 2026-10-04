@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Navegacion de paginas') }}" class="flex items-center justify-between gap-4">
        {{-- Info de resultados, hidden en movil --}}
        <p class="hidden text-sm text-gray-600 sm:block">
            {!! __('Mostrando :first a :last de :total productos', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) !!}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-300"
                      aria-disabled="true" aria-label="{{ __('Pagina anterior') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-sm text-gray-700 transition hover:bg-gray-50"
                   aria-label="{{ __('Pagina anterior') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                    </svg>
                </a>
            @endif

            {{-- Numeros de pagina --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-9 min-w-9 items-center justify-center px-2 text-sm text-gray-400">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-indigo-600 px-3 text-sm font-semibold text-white">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-sm text-gray-700 transition hover:bg-gray-50"
                   aria-label="{{ __('Pagina siguiente') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                    </svg>
                </a>
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-sm text-gray-300"
                      aria-disabled="true" aria-label="{{ __('Pagina siguiente') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif