<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login', absolute: false), navigate: true);
    }
}; ?>

<button
    type="button"
    wire:click="logout"
    wire:loading.attr="disabled"
    class="btn-icon shrink-0 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
    title="Se déconnecter"
>
    <x-icon name="logout" class="h-5 w-5" />
    <span class="sr-only">Se déconnecter</span>
</button>
