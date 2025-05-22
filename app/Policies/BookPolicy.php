<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Book $book): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (!$book->patient) {
            return false;
        }
        if ($user->isLab() && $book->patient->lab_id === $user->id) {
            return true;
        }
        if ($user->isDoctor() && $book->patient->doctor_id === $user->id) {
            return true;
        }
        if ($user->isPatient() && $book->patient_id === $user->id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Book $book): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (!$book->patient) {
            return false;
        }
        if ($user->isLab() && $book->patient->lab_id === $user->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Book $book): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (!$book->patient) {
            return false;
        }
        if ($user->isLab() && $book->patient->lab_id === $user->id) {
            return true;
        }
        return false;
    }

    public function restore(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }
}
