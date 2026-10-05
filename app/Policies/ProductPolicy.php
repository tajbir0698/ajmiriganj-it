<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole([RoleName::SUPER_ADMIN->value, RoleName::MANAGER->value]);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasRole([RoleName::SUPER_ADMIN->value, RoleName::MANAGER->value]);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::MANAGER->value) && (bool) \App\Models\Setting::get('manager_can_add_products', false)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function viewCost(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
