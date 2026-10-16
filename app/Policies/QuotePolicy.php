<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Un devis accepté, refusé ou expiré n'est plus modifiable.
     */
    public function update(User $user, Quote $quote): bool
    {
        return $user->is_active && $quote->status->isEditable();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }
}
