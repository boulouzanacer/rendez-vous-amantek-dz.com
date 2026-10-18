<?php

namespace App\Policies;

use App\Models\Authorization;
use App\Models\User;

class AuthorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Authorization $authorization): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Un technicien ne peut faire évoluer que les dossiers qu'il a ouverts.
     */
    public function update(User $user, Authorization $authorization): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $authorization->created_by === null || $authorization->created_by === $user->id;
    }

    public function delete(User $user, Authorization $authorization): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Authorization $authorization): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Authorization $authorization): bool
    {
        return $user->isAdmin();
    }
}
