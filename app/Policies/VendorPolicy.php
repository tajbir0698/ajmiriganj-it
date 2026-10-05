<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function restore(User $user, Vendor $vendor): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function forceDelete(User $user, Vendor $vendor): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
