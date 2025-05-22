<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLab();
    }

    public function view(User $user, User $patient): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isLab()) {
            return $user->id === $patient->lab_id;
        }
        if ($user->isDoctor()) {
            return $user->id === $patient->doctor_id;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLab();
    }

    public function update(User $user, User $patient): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isLab()) {
            return $user->id === $patient->lab_id;
        }
        return false;
    }

    public function delete(User $user, User $patient): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isLab()) {
            return $user->id === $patient->lab_id;
        }
        return false;
    }

    public function restore(User $user, User $patient): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, User $patient): bool
    {
        return $user->isAdmin();
    }
}
