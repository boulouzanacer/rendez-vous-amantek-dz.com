<?php

namespace App\Policies;

use App\Models\Installation;
use App\Models\User;

class InstallationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Installation $installation): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Un technicien ne peut faire évoluer que les chantiers dont il a la charge.
     */
    public function update(User $user, Installation $installation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $installation->user_id === null || $installation->user_id === $user->id;
    }

    public function delete(User $user, Installation $installation): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Installation $installation): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Installation $installation): bool
    {
        return $user->isAdmin();
    }
}
