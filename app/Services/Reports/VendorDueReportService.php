<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Vendor;
use App\Services\VendorAccountService;
use Illuminate\Support\Collection;

class VendorDueReportService
{
    public function __construct(
        protected VendorAccountService $vendorAccountService
    ) {}

    /**
     * Generate vendor due summary report.
     *
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     summary: array<string, string>,
     * }
     */
    public function generate(): array
    {
        $vendors = Vendor::where('is_active', true)->orderBy('name', 'asc')->get();

        $rows = new Collection();
        $totalPayable = '0.00';
        $totalAdvances = '0.00';
        $totalPurchases = '0.00';
        $totalPaid = '0.00';

        foreach ($vendors as $vendor) {
            $purchased = $this->vendorAccountService->getTotalPurchased($vendor);
            $paid = $this->vendorAccountService->getTotalPaid($vendor);
            $globalDue = $this->vendorAccountService->getCurrentDue($vendor);
            $advance = $this->vendorAccountService->getTotalAdvance($vendor);

            $payable = bccomp($globalDue, '0.00', 2) > 0 ? $globalDue : '0.00';

            $rows->push([
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'phone' => $vendor->phone ?? '',
                'opening_balance' => (string) ($vendor->opening_balance ?? '0.00'),
                'total_purchased' => $purchased,
                'total_paid' => $paid,
                'current_due' => $payable,
                'advance' => $advance,
                'net_balance' => $globalDue,
            ]);

            $totalPurchases = bcadd($totalPurchases, $purchased, 2);
            $totalPaid = bcadd($totalPaid, $paid, 2);
            $totalPayable = bcadd($totalPayable, $payable, 2);
            $totalAdvances = bcadd($totalAdvances, $advance, 2);
        }

        $summary = [
            'total_purchases' => $totalPurchases,
            'total_paid' => $totalPaid,
            'total_payable' => $totalPayable,
            'total_advances' => $totalAdvances,
            'current_due' => $totalPayable,
            'net_due' => bcsub($totalPayable, $totalAdvances, 2),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'totals' => $summary,
        ];
    }
}
