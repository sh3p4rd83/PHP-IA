<?php

namespace App\Policies;

use App\Models\Missile;
use App\Models\Partie;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class MissilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Missile $missile): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Partie $partie): Response
    {
        return $user->id === $partie->user_id
            ? Response::allow()
            : Response::deny("Cette action n’est pas autorisée.");;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Partie $partie): Response
    {
        return $user->id === $partie->user_id
            ? Response::allow()
            : Response::deny("Cette action n’est pas autorisée.");
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Missile $missile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Missile $missile): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Missile $missile): bool
    {
        //
    }
}
