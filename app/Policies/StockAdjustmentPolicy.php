<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return false; // Adjustments are immutable
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return false; // Adjustments are immutable
    }
}
