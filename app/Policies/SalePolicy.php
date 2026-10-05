<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Cancelled sales stay hidden from the Manager whatever the setting
        if ($sale->status !== SaleStatus::COMPLETED) {
            return false;
        }

        $visibility = Setting::get('manager_sales_visibility', 'all');
        if ($visibility === 'all') {
            return true;
        }

        return $sale->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }
}
