<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HeldCart;
use App\Models\User;

class HeldCartPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HeldCart $heldCart): bool
    {
        return $heldCart->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HeldCart $heldCart): bool
    {
        return $heldCart->user_id === $user->id;
    }

    public function delete(User $user, HeldCart $heldCart): bool
    {
        return $heldCart->user_id === $user->id;
    }
}
