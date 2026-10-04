<section>
    <header>
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Información del perfil') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __("Update your account's profile information, email address, phone and shipping address.") }}
            </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- HU-04: teléfono y dirección de envío --}}
        <div>
            <x-input-label for="phone" value="Teléfono" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" placeholder="+591 70000000" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            <p class="mt-1 text-sm text-gray-500">
                Lo usamos para avisarte cuando tu pedido salga a entrega.
            </p>
        </div>

        <div>
            <x-input-label for="address" value="Dirección de envío" />
            <textarea id="address" name="address" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Av. Siempre Viva 742, La Paz" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('address')" />
            <p class="mt-1 text-sm text-gray-500">
                Se usa como valor por defecto al confirmar tu compra, pero puedes cambiarla en el carrito.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Guardado.') }}</p>
            @endif
        </div>
    </form>
</section>
