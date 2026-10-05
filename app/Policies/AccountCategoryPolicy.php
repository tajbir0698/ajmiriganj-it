<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccountCategory;
use App\Models\User;

class AccountCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, AccountCategory $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, AccountCategory $category): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        // System categories cannot be edited/renamed
        return ! $category->isSystem();
    }

    public function delete(User $user, AccountCategory $category): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        // System categories or categories in use cannot be deleted
        return ! $category->isSystem() && ! $category->transactions()->exists();
    }
}
