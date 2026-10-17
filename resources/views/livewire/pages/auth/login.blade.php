<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Connexion</h2>
        <p class="mt-1 text-sm text-slate-500">Accédez à votre espace de gestion.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4" x-data="{ showPassword: false }">
        <div>
            <label for="email" class="label">Adresse e-mail</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                    <x-icon name="mail" class="h-4 w-4" />
                </span>
                <input
                    wire:model="form.email"
                    id="email"
                    type="email"
                    name="email"
                    class="input ps-9"
                    placeholder="vous@exemple.com"
                    required
                    autofocus
                    autocomplete="username"
                    @error('form.email') aria-invalid="true" @enderror
                >
            </div>
            @error('form.email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="label">Mot de passe</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                    <x-icon name="shield" class="h-4 w-4" />
                </span>
                <input
                    wire:model="form.password"
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    type="password"
                    name="password"
                    class="input ps-9 pe-11"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                    @error('form.password') aria-invalid="true" @enderror
                >
                <button
                    type="button"
                    class="absolute inset-y-0 end-0 flex items-center px-3 text-slate-400 transition hover:text-slate-600"
                    @click="showPassword = ! showPassword"
                    :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                >
                    <x-icon name="eye" class="h-4 w-4" x-show="! showPassword" />
                    <x-icon name="x" class="h-4 w-4" x-show="showPassword" x-cloak />
                </button>
            </div>
            @error('form.password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <label for="remember" class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
            <input
                wire:model="form.remember"
                id="remember"
                type="checkbox"
                name="remember"
                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            >
            Se souvenir de moi
        </label>

        <button type="submit" class="btn-primary w-full py-2.5" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                <x-icon name="logout" class="h-4 w-4" />
                Se connecter
            </span>
            <span wire:loading wire:target="login" class="flex items-center gap-2">
                <x-icon name="refresh" class="h-4 w-4 animate-spin" />
                Connexion…
            </span>
        </button>
    </form>

    @if (Route::has('password.request'))
        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('password.request') }}" wire:navigate class="font-medium text-brand-600 transition hover:text-brand-700">
                Mot de passe oublié ?
            </a>
        </p>
    @endif
</div>
