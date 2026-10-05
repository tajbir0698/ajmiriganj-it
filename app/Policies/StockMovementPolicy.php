<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return false; // Movements are created only through service engine
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false; // Immutable audit log
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false; // Immutable audit log
    }
}
