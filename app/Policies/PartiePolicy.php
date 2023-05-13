<?php

namespace App\Policies;

use App\Models\Partie;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class PartiePolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Partie $partie): Response
    {
        return $user->id === $partie->user_id
            ? Response::allow()
            : Response::deny("Cette action n’est pas autorisée.");
    }
}
