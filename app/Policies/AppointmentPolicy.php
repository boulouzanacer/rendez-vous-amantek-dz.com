<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Un technicien ne peut modifier que les rendez-vous qui lui sont assignés.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $appointment->user_id === null
            || $appointment->user_id === $user->id
            || $appointment->technicians()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }
}
