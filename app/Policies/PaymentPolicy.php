<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
