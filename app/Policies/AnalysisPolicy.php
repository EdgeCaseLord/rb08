<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AnalysisPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Allow admins and lab users to view all analyses
        return $user->isAdmin() || $user->isLab();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Analysis $analysis): bool
    {
        // Lab users can view analyses with their lab_id
        if ($user->isLab()) {
            return $user->id === $analysis->lab_id;
        }

        // Doctors can view analyses with their doctor_id
        if ($user->isDoctor()) {
            return $user->id === $analysis->doctor_id;
        }

        // Patients can view analyses with their patient_id
        if ($user->isPatient()) {
            return $user->id === $analysis->patient_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only lab users and doctors can create analyses
        return $user->isAdmin() || $user->isLab();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Analysis $analysis): bool
    {
        // Allow admins to update any analysis
        if ($user->isAdmin()) {
            return true;
        }
        // Only lab users and doctors can update analyses
        if ($user->isLab()) {
            return $user->id === $analysis->lab_id;
        }
        if ($user->isDoctor()) {
            return $user->id === $analysis->doctor_id;
        }
        return false; // Patients cannot update
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Analysis $analysis): bool
    {
        // Only lab users can delete analyses
        if ($user->isLab()) {
            return $user->id === $analysis->lab_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Analysis $analysis): bool
    {
        // Only lab users can restore analyses
        if ($user->isLab()) {
            return $user->id === $analysis->lab_id;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Analysis $analysis): bool
    {
        // Only lab users can permanently delete analyses
        if ($user->isLab()) {
            return $user->id === $analysis->lab_id;
        }

        return false;
    }
}
