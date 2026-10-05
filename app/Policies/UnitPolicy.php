<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
