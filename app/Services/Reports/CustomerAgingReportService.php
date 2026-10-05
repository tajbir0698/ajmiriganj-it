<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\CustomerAccountService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerAgingReportService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService
    ) {}

    /**
     * @return array{
     *     as_of_date: string,
     *     rows: Collection<int, array{
     *         customer_id: int,
     *         customer_name: string,
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

        $customers = Customer::query()->orderBy('name')->get();

        $rows = collect();
        $totalOpening = '0.00';
        $total0To30 = '0.00';
        $total31To60 = '0.00';
        $total61To90 = '0.00';
        $total90Plus = '0.00';
        $totalOutstanding = '0.00';
        $totalAdvance = '0.00';
        $totalNetDue = '0.00';

        foreach ($customers as $customer) {
            $openingOutstanding = $this->customerAccountService->getOutstandingOpening($customer);
            $advance = $this->customerAccountService->getTotalAdvance($customer);
            $globalDue = $this->customerAccountService->getCurrentDue($customer);

            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', SaleStatus::COMPLETED)
                ->where('outstanding_due', '>', 0)
                ->get();

            $b0to30 = '0.00';
            $b31to60 = '0.00';
            $b61to90 = '0.00';
            $b90plus = '0.00';
            $customerOutstandingSales = '0.00';

            foreach ($sales as $s) {
                $due = (string) $s->outstanding_due;
                if (bccomp($due, '0.00', 2) > 0) {
                    $customerOutstandingSales = bcadd($customerOutstandingSales, $due, 2);

                    $sDate = Carbon::parse($s->sale_date)->startOfDay();
                    $days = $sDate->diffInDays($referenceDate, false);
                    if ($days < 0) {
                        $days = 0;
                    }

                    if ($days <= 30) {
                        $b0to30 = bcadd($b0to30, $due, 2);
                    } elseif ($days <= 60) {
                        $b31to60 = bcadd($b31to60, $due, 2);
                    } elseif ($days <= 90) {
                        $b61to90 = bcadd($b61to90, $due, 2);
                    } else {
                        $b90plus = bcadd($b90plus, $due, 2);
                    }
                }
            }

            $cTotalOut = bcadd($openingOutstanding, $customerOutstandingSales, 2);

            // Skip customers with no balance or due
            if (
                bccomp($openingOutstanding, '0.00', 2) === 0 &&
                bccomp($customerOutstandingSales, '0.00', 2) === 0 &&
                bccomp($advance, '0.00', 2) === 0 &&
                bccomp($globalDue, '0.00', 2) === 0
            ) {
                continue;
            }

            $rows->push([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'opening_balance' => $openingOutstanding,
                'bucket_0_30' => $b0to30,
                'bucket_31_60' => $b31to60,
                'bucket_61_90' => $b61to90,
                'bucket_90_plus' => $b90plus,
                'total_outstanding' => $cTotalOut,
                'advance' => $advance,
                'net_due' => $globalDue,
            ]);

            $totalOpening = bcadd($totalOpening, $openingOutstanding, 2);
            $total0To30 = bcadd($total0To30, $b0to30, 2);
            $total31To60 = bcadd($total31To60, $b31to60, 2);
            $total61To90 = bcadd($total61To90, $b61to90, 2);
            $total90Plus = bcadd($total90Plus, $b90plus, 2);
            $totalOutstanding = bcadd($totalOutstanding, $cTotalOut, 2);
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
