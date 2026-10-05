<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
