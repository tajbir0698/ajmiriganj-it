<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Vendor;
use App\Services\AccountService;
use App\Services\BusinessFinanceService;
use App\Services\CustomerAccountService;
use App\Services\FifoStockService;
use App\Services\Reports\BalanceSheetReportService;
use App\Services\VendorAccountService;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('DemoDataSeeder runs cleanly and verifies all exact hand-calculated financial expectations', function () {
    // 1. Run prerequisite seeders
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(AccountCategorySeeder::class);
    $this->seed(UnitSeeder::class);

    // 2. Run DemoDataSeeder
    $this->seed(DemoDataSeeder::class);

    $accountService = app(AccountService::class);
    $customerAccountService = app(CustomerAccountService::class);
    $vendorAccountService = app(VendorAccountService::class);
    $fifoStockService = app(FifoStockService::class);
    $financeService = app(BusinessFinanceService::class);
    $bsService = app(BalanceSheetReportService::class);

    // 3. Verify Account Balances
    $cash = Account::where('name', 'Cash')->firstOrFail();
    $bank = Account::where('name', 'Bank Account')->firstOrFail();
    $bkash = Account::where('name', 'bKash')->firstOrFail();

    expect($accountService->balance($cash))->toBe('64100.00')
        ->and($accountService->balance($bank))->toBe('106800.00')
        ->and($accountService->balance($bkash))->toBe('10000.00');

    // Total Cash & Bank = 180,900.00
    $totalCash = bcadd(bcadd($accountService->balance($cash), $accountService->balance($bank), 2), $accountService->balance($bkash), 2);
    expect($totalCash)->toBe('180900.00');

    // 4. Verify Customer Dues
    $alAmin = Customer::where('name', 'Al-Amin Enterprise')->firstOrFail();
    $bismillah = Customer::where('name', 'Bismillah Computer')->firstOrFail();

    expect($customerAccountService->getCurrentDue($alAmin))->toBe('9600.00')
        ->and($customerAccountService->getCurrentDue($bismillah))->toBe('0.00');

    // 5. Verify Vendor Dues and Advances
    $starTech = Vendor::where('name', 'Star Tech Distribution')->firstOrFail();
    $globalBrand = Vendor::where('name', 'Global Brand Distro')->firstOrFail();
    $excelTelecom = Vendor::where('name', 'Excel Telecom')->firstOrFail();

    expect($vendorAccountService->getCurrentDue($starTech))->toBe('10650.00')
        ->and($vendorAccountService->getCurrentDue($globalBrand))->toBe('-1500.00')
        ->and($vendorAccountService->getTotalAdvance($globalBrand))->toBe('1500.00')
        ->and($vendorAccountService->getCurrentDue($excelTelecom))->toBe('0.00');

    // 6. Verify FIFO Stock Value
    $stockValue = $fifoStockService->stockValue();
    expect($stockValue)->toBe('92470.00');

    // 7. Verify Net Profit
    $netProfit = $financeService->netProfit();
    expect($netProfit)->toBe('1920.00');

    // 8. Verify Balance Sheet
    $bs = $bsService->generate();

    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['difference'])->toBe('0.00')
        ->and($bs['assets']['total_assets'])->toBe('284470.00')
        ->and($bs['liabilities']['total_liabilities'])->toBe('10650.00')
        ->and($bs['equity']['total_equity'])->toBe('273820.00')
        ->and($bs['total_liabilities_and_equity'])->toBe('284470.00');
});
