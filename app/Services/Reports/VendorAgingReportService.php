<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Services\VendorAccountService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VendorAgingReportService
{
    public function __construct(
        protected VendorAccountService $vendorAccountService
    ) {}

    /**
     * @return array{
     *     as_of_date: string,
     *     rows: Collection<int, array{
     *         vendor_id: int,
     *         vendor_name: string,
     *         phone: ?string,
     *         opening_balance: string,
     *         bucket_0_30: string,
     *         bucket_31_60: string,
     *         bucket_61_90: string,
     *         bucket_90_plus: string,
     *         total_outstanding: string,
     *         advance: string,
     *         net_due: string
     *     }>,
     *     totals: array{
     *         opening_balance: string,
     *         bucket_0_30: string,
     *         bucket_31_60: string,
     *         bucket_61_90: string,
     *         bucket_90_plus: string,
     *         total_outstanding: string,
     *         advance: string,
     *         net_due: string
     *     }
     * }
     */
    public function generate(?string $asOfDate = null): array
    {
        $referenceDate = $asOfDate ? Carbon::parse($asOfDate)->startOfDay() : now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->startOfDay();
        $dateStr = $referenceDate->format('Y-m-d');

        $vendors = Vendor::query()
            ->orderBy('name')
            ->get();

        $rows = collect();
        $totalOpening = '0.00';
        $total0To30 = '0.00';
        $total31To60 = '0.00';
        $total61To90 = '0.00';
        $total90Plus = '0.00';
        $totalOutstanding = '0.00';
        $totalAdvance = '0.00';
        $totalNetDue = '0.00';

        foreach ($vendors as $vendor) {
            $openingOutstanding = $this->vendorAccountService->getOutstandingOpening($vendor);
            $advance = $this->vendorAccountService->getTotalAdvance($vendor);
            $globalDue = $this->vendorAccountService->getCurrentDue($vendor);

            $purchases = Purchase::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', PurchaseStatus::ACTIVE->value)
                ->with(['returns', 'paymentAllocations' => function ($q): void {
                    $q->whereHas('vendorPayment', function ($vp): void {
                        $vp->whereNull('reversed_at');
                    });
                }])
                ->get();

            $b0to30 = '0.00';
            $b31to60 = '0.00';
            $b61to90 = '0.00';
            $b90plus = '0.00';
            $vendorOutstandingBills = '0.00';

            foreach ($purchases as $p) {
                $billTotal = (string) $p->total;

                $absorbedCredit = '0.00';
                foreach ($p->returns as $ret) {
                    $netCreditOnBill = bcsub((string) $ret->credit_amount, (string) $ret->refund_received_amount, 2);
                    $absorbedCredit = bcadd($absorbedCredit, $netCreditOnBill, 2);
                }

                $allocated = '0.00';
                foreach ($p->paymentAllocations as $alloc) {
                    $allocated = bcadd($allocated, (string) $alloc->amount, 2);
                }

                $netBill = bcsub($billTotal, $absorbedCredit, 2);
                $billDue = bcsub($netBill, $allocated, 2);
                $effectiveDue = bccomp($billDue, '0.00', 2) > 0 ? $billDue : '0.00';

                if (bccomp($effectiveDue, '0.00', 2) > 0) {
                    $vendorOutstandingBills = bcadd($vendorOutstandingBills, $effectiveDue, 2);

                    $pDate = Carbon::parse($p->purchase_date)->startOfDay();
                    $days = $pDate->diffInDays($referenceDate, false);
                    if ($days < 0) {
                        $days = 0;
                    }

                    if ($days <= 30) {
                        $b0to30 = bcadd($b0to30, $effectiveDue, 2);
                    } elseif ($days <= 60) {
                        $b31to60 = bcadd($b31to60, $effectiveDue, 2);
                    } elseif ($days <= 90) {
                        $b61to90 = bcadd($b61to90, $effectiveDue, 2);
                    } else {
                        $b90plus = bcadd($b90plus, $effectiveDue, 2);
                    }
                }
            }

            // Vendor total outstanding = opening + bills
            $vTotalOut = bcadd($openingOutstanding, $vendorOutstandingBills, 2);

            // Only show vendors that have activity or non-zero dues / balances
            if (
                bccomp($openingOutstanding, '0.00', 2) === 0 &&
                bccomp($vendorOutstandingBills, '0.00', 2) === 0 &&
                bccomp($advance, '0.00', 2) === 0 &&
                bccomp($globalDue, '0.00', 2) === 0
            ) {
                continue;
            }

            $rows->push([
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'phone' => $vendor->phone,
                'opening_balance' => $openingOutstanding,
                'bucket_0_30' => $b0to30,
                'bucket_31_60' => $b31to60,
                'bucket_61_90' => $b61to90,
                'bucket_90_plus' => $b90plus,
                'total_outstanding' => $vTotalOut,
                'advance' => $advance,
                'net_due' => $globalDue,
            ]);

            $totalOpening = bcadd($totalOpening, $openingOutstanding, 2);
            $total0To30 = bcadd($total0To30, $b0to30, 2);
            $total31To60 = bcadd($total31To60, $b31to60, 2);
            $total61To90 = bcadd($total61To90, $b61to90, 2);
            $total90Plus = bcadd($total90Plus, $b90plus, 2);
            $totalOutstanding = bcadd($totalOutstanding, $vTotalOut, 2);
            $totalAdvance = bcadd($totalAdvance, $advance, 2);
            $totalNetDue = bcadd($totalNetDue, $globalDue, 2);
        }

        return [
            'as_of_date' => $dateStr,
            'rows' => $rows,
            'totals' => [
                'opening_balance' => $totalOpening,
                'bucket_0_30' => $total0To30,
                'bucket_31_60' => $total31To60,
                'bucket_61_90' => $total61To90,
                'bucket_90_plus' => $total90Plus,
                'total_outstanding' => $totalOutstanding,
                'advance' => $totalAdvance,
                'net_due' => $totalNetDue,
            ],
        ];
    }
}
