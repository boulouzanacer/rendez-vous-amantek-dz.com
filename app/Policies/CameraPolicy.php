<?php

namespace App\Policies;

use App\Models\Camera;
use App\Models\User;

class CameraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Camera $camera): bool
    {
        return $user->is_active;
    }

    /**
     * Le catalogue des caméras est réservé aux administrateurs.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Camera $camera): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Camera $camera): bool
    {
        return $user->isAdmin();
    }
}
