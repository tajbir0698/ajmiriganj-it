<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\RestockRequest;
use App\Models\Setting;
use App\Models\User;

class RestockRequestPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false);
    }

    public function view(User $user, RestockRequest $request): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false)) {
            return $request->requested_by === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false);
    }

    public function update(User $user, RestockRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, RestockRequest $request): bool
    {
        return false;
    }

    public function cancel(User $user, RestockRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value)
            && (bool) Setting::get('manager_can_request_restock', false)
            && $request->requested_by === $user->id;
    }

    public function approve(User $user, RestockRequest $request): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value) && $request->isPending();
    }

    public function reject(User $user, RestockRequest $request): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value) && $request->isPending();
    }
}
