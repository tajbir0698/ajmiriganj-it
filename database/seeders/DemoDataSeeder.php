<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\RecordTransactionData;
use App\Enums\AdjustmentType;
use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Enums\ReturnSettlement;
use App\Enums\RoleName;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountService;
use App\Services\CustomerPaymentService;
use App\Services\FifoStockService;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\StockAdjustmentService;
use App\Services\VendorPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DemoDataSeeder
 *
 * Local/testing environment only seeder built strictly through existing domain services.
 * NO DIRECT INSERTS are made to transactions, stock batches, or payment allocations.
 *
 * =========================================================================================
 * HAND-CALCULATED FINANCIAL EXPECTATIONS (AS OF SEED COMPLETION):
 * =========================================================================================
 * 1. CASH & BANK ASSETS:
 *    - Cash:                        ৳  64,100.00
 *    - Bank Account:                ৳ 106,800.00
 *    - bKash:                       ৳  10,000.00
 *    -------------------------------------------
 *    Total Cash & Bank:             ৳ 180,900.00
 *
 * 2. RECEIVABLES & ADVANCES:
 *    - Customer Dues (Receivables): ৳   9,600.00 (Al-Amin Enterprise)
 *    - Customer Advances:           ৳       0.00
 *    - Vendor Advances:             ৳   1,500.00 (Global Brand Distro)
 *
 * 3. INVENTORY (FIFO VALUATION):
 *    - Product 1 (Router C6):       8 pcs  = ৳ 16,770.00 (3@2040 + 5@2130)
 *    - Product 2 (Cat6 Cable):      2 pcs  = ৳  9,000.00 (2@4500)
 *    - Product 3 (Kingston SSD):    13 pcs = ৳ 35,900.00 (8@2800 + 5@2700)
 *    - Product 4 (Corsair RAM):     7 pcs  = ৳ 12,600.00 (7@1800)
 *    - Product 5 (Logitech Mouse):  18 pcs = ৳  4,500.00 (18@250)
 *    - Product 6 (A4Tech Keyboard): 10 pcs = ৳  4,500.00 (10@450)
 *    - Product 7 (HDMI Cable 3M):   28 pcs = ৳  4,200.00 (28@150)
 *    - Product 8 (TP-Link Switch):  5 pcs  = ৳  5,000.00 (5@1000)
 *    -------------------------------------------
 *    Total FIFO Inventory:          ৳  92,470.00
 *
 * 4. TOTAL ASSETS:
 *    180,900.00 (Cash/Bank) + 9,600.00 (Receivables) + 1,500.00 (Vendor Adv) + 92,470.00 (Stock)
 *    = ৳ 284,470.00
 *
 * 5. LIABILITIES:
 *    - Accounts Payable (Star Tech):৳  10,650.00
 *    - Customer Advances:           ৳       0.00
 *    -------------------------------------------
 *    Total Liabilities:             ৳  10,650.00
 *
 * 6. OPERATING PROFIT & EQUITY:
 *    - Gross Profit from Sales:     ৳   9,920.00 (Sale 1: 5,120 + Sale 2: 5,400 - Return: 600)
 *    - Operating Expenses:          ৳   7,500.00 (Rent: 6,000 + Electricity: 1,500)
 *    - Stock Losses:                ৳     500.00 (2 damaged mice @ 250)
 *    -------------------------------------------
 *    Net Profit:                    ৳   1,920.00
 *    - Profit Withdrawal:           ৳   2,000.00
 *    - Retained Profit:             -৳     80.00
 *    - Owner Capital:               ৳  45,920.00 (50,000 Investment - 4,000 Drawing - 80)
 *    - Initial & Opening Equity:    ৳ 227,900.00 (135,000 Cash + 95,900 Stock + 2,000 Cust - 5,000 Vend)
 *    -------------------------------------------
 *    Total Equity:                  ৳ 273,820.00
 *
 * 7. BALANCE SHEET RECONCILIATION:
 *    Total Assets:                  ৳ 284,470.00
 *    Total Liabilities & Equity:    ৳  10,650.00 + ৳ 273,820.00 = ৳ 284,470.00
 *    Exact Difference:              ৳       0.00
 * =========================================================================================
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoDataSeeder is only allowed in local or testing environments.');
        }

        // 1. Settings & Super Admin User
        Setting::set('credit_sales_enabled', true);
        Setting::set('shop_name', 'Ajmiriganj IT Solution');
        Setting::set('dead_stock_days', 60);

        $admin = User::first();
        if (! $admin) {
            $admin = User::create([
                'name' => 'Demo Super Admin',
                'email' => 'admin@ajmiriganj.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
            $admin->assignRole(RoleName::SUPER_ADMIN->value);
        }

        // 2. Services
        $accountService = app(AccountService::class);
        $fifoStockService = app(FifoStockService::class);
        $purchaseService = app(PurchaseService::class);
        $vendorPaymentService = app(VendorPaymentService::class);
        $saleService = app(SaleService::class);
        $customerPaymentService = app(CustomerPaymentService::class);
        $saleReturnService = app(SaleReturnService::class);
        $purchaseReturnService = app(PurchaseReturnService::class);
        $stockAdjustmentService = app(StockAdjustmentService::class);

        // 3. Accounts & Initial Opening Balances
        $cashAcc = Account::firstOrCreate(
            ['name' => 'Cash'],
            ['type' => \App\Enums\AccountKind::CASH, 'opening_balance' => '25000.00', 'is_active' => true]
        );
        $cashAcc->update(['opening_balance' => '25000.00']);

        $bankAcc = Account::firstOrCreate(
            ['name' => 'Bank Account'],
            ['type' => \App\Enums\AccountKind::BANK, 'opening_balance' => '100000.00', 'is_active' => true]
        );
        $bankAcc->update(['opening_balance' => '100000.00']);

        $bkashAcc = Account::firstOrCreate(
            ['name' => 'bKash'],
            ['type' => \App\Enums\AccountKind::MOBILE_WALLET, 'opening_balance' => '10000.00', 'is_active' => true]
        );
        $bkashAcc->update(['opening_balance' => '10000.00']);

        // 4. Categories for Transactions
        $ownerInvestmentCat = AccountCategory::firstOrCreate(
            ['name' => 'Owner Investment'],
            ['type' => \App\Enums\AccountCategoryType::EQUITY, 'affects_profit' => false, 'is_active' => true]
        );
        $ownerDrawingCat = AccountCategory::firstOrCreate(
            ['name' => 'Owner Drawing'],
            ['type' => \App\Enums\AccountCategoryType::EQUITY, 'affects_profit' => false, 'is_active' => true]
        );
        $profitWithdrawalCat = AccountCategory::firstOrCreate(
            ['name' => 'Profit Withdrawal'],
            ['type' => \App\Enums\AccountCategoryType::EQUITY, 'affects_profit' => false, 'is_active' => true]
        );
        $rentCat = AccountCategory::firstOrCreate(
            ['name' => 'Rent'],
            ['type' => \App\Enums\AccountCategoryType::EXPENSE, 'affects_profit' => true, 'is_active' => true]
        );
        $electricityCat = AccountCategory::firstOrCreate(
            ['name' => 'Electricity'],
            ['type' => \App\Enums\AccountCategoryType::EXPENSE, 'affects_profit' => true, 'is_active' => true]
        );

        // 5. Owner Investment (55 days ago)
        $accountService->record(new RecordTransactionData(
            accountId: $bankAcc->id,
            categoryId: $ownerInvestmentCat->id,
            type: TransactionType::IN,
            amount: '50000.00',
            date: now()->subDays(55)->toDateString(),
            createdBy: $admin->id,
            description: 'Owner Initial Capital Infusion'
        ));

        // 6. Vendors (3 Vendors)
        $vendorStarTech = Vendor::firstOrCreate(
            ['name' => 'Star Tech Distribution'],
            ['phone' => '01711111111', 'opening_balance' => '0.00', 'is_active' => true]
        );
        $vendorGlobalBrand = Vendor::firstOrCreate(
            ['name' => 'Global Brand Distro'],
            ['phone' => '01811111111', 'opening_balance' => '5000.00', 'is_active' => true]
        );
        $vendorExcelTelecom = Vendor::firstOrCreate(
            ['name' => 'Excel Telecom'],
            ['phone' => '01911111111', 'opening_balance' => '0.00', 'is_active' => true]
        );

        // 7. Customers (2 Customers)
        $custAlAmin = Customer::firstOrCreate(
            ['name' => 'Al-Amin Enterprise'],
            ['phone' => '01722222222', 'opening_balance' => '2000.00', 'is_active' => true]
        );
        $custBismillah = Customer::firstOrCreate(
            ['name' => 'Bismillah Computer'],
            ['phone' => '01822222222', 'opening_balance' => '0.00', 'is_active' => true]
        );

        // 8. Units & Product Categories
        $unitPcs = Unit::firstOrCreate(['name' => 'Pcs'], ['short_name' => 'pcs', 'allow_fractional' => false]);
        $prodCat = Category::firstOrCreate(['name' => 'Computer Hardware']);

        // 9. 8 Products & Opening Stock Batches (60 days ago)
        $createProdWithOpening = function (string $name, string $sku, string $salePrice, string $cost, string $openingQty) use ($unitPcs, $prodCat, $fifoStockService, $admin): Product {
            $prod = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category_id' => $prodCat->id,
                    'unit_id' => $unitPcs->id,
                    'sale_price' => $salePrice,
                    'last_cost' => $cost,
                    'stock_qty' => '0.000',
                    'alert_qty' => '2.000',
                    'is_active' => true,
                ]
            );

            // Add opening batch via FifoStockService within transaction
            \Illuminate\Support\Facades\DB::transaction(function () use ($fifoStockService, $prod, $openingQty, $cost, $admin) {
                $fifoStockService->addBatch(
                    product: $prod,
                    qty: $openingQty,
                    unitCost: $cost,
                    source: BatchSource::OPENING,
                    batchDate: now()->subDays(60),
                    user: $admin
                );
            });

            return $prod->fresh();
        };

        $prod1 = $createProdWithOpening('Demo Router Archer C6', 'DEMO-ROUTER-C6', '2500.00', '2000.00', '10.000');
        $prod2 = $createProdWithOpening('Demo Cat6 Cable Box', 'DEMO-CAT6-BOX', '6000.00', '4500.00', '4.000');
        $prod3 = $createProdWithOpening('Demo Kingston SSD 480GB', 'DEMO-SSD-480', '3500.00', '2800.00', '8.000');
        $prod4 = $createProdWithOpening('Demo Corsair RAM 8GB', 'DEMO-RAM-8GB', '2400.00', '1800.00', '10.000');
        $prod5 = $createProdWithOpening('Demo Logitech Mouse M90', 'DEMO-MOUSE-M90', '400.00', '250.00', '20.000');
        $prod6 = $createProdWithOpening('Demo A4Tech Keyboard', 'DEMO-KBD-A4', '700.00', '450.00', '10.000');
        $prod7 = $createProdWithOpening('Demo HDMI Cable 3M', 'DEMO-HDMI-3M', '300.00', '150.00', '20.000');
        $prod8 = $createProdWithOpening('Demo TP-Link Switch 8P', 'DEMO-SW-8P', '1500.00', '1000.00', '5.000');

        // 10. Purchases at two costs with shipping (Star Tech)
        // Purchase 1 (50 days ago) - Cost: 2000.00 + shipping 200 = landed 2040.00
        $purchase1 = $purchaseService->createPurchase([
            'vendor_id' => $vendorStarTech->id,
            'purchase_date' => now()->subDays(50)->toDateString(),
            'shipping_cost' => '200.00',
            'discount' => '0.00',
            'paid_amount' => '10200.00',
            'payment_method' => PaymentMethod::BANK,
            'account_id' => $bankAcc->id,
            'items' => [
                [
                    'product_id' => $prod1->id,
                    'qty' => '5.000',
                    'unit_cost' => '2000.00',
                ],
            ],
        ], [], $admin);

        // Purchase 2 (40 days ago) - SECOND COST: 2100.00 + shipping 150 = landed 2130.00 (Credit bill)
        $purchase2 = $purchaseService->createPurchase([
            'vendor_id' => $vendorStarTech->id,
            'purchase_date' => now()->subDays(40)->toDateString(),
            'shipping_cost' => '150.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'payment_method' => PaymentMethod::CASH,
            'items' => [
                [
                    'product_id' => $prod1->id,
                    'qty' => '5.000',
                    'unit_cost' => '2100.00',
                ],
            ],
        ], [], $admin);

        // 11. Vendor Credit Bill with Lump-Sum Payment & Advance (Global Brand)
        // Purchase 3 (45 days ago) - 5 SSDs @ 2700 = 13,500.00 on credit
        $purchase3 = $purchaseService->createPurchase([
            'vendor_id' => $vendorGlobalBrand->id,
            'purchase_date' => now()->subDays(45)->toDateString(),
            'shipping_cost' => '0.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'payment_method' => PaymentMethod::BANK,
            'items' => [
                [
                    'product_id' => $prod3->id,
                    'qty' => '5.000',
                    'unit_cost' => '2700.00',
                ],
            ],
        ], [], $admin);

        // Lump-sum payment (35 days ago): 20,000.00 paid towards 5,000 opening + 13,500 bill -> 1,500 ADVANCE
        $vendorPaymentService->pay(
            vendor: $vendorGlobalBrand,
            amount: '20000.00',
            method: PaymentMethod::BANK,
            account: $bankAcc,
            date: now()->subDays(35),
            reference: 'TXN-LUMP-GLOBAL',
            note: 'Lump-sum payment clearing dues and providing advance',
            allocation: 'auto',
            userId: $admin->id
        );

        // 12. Purchase and Purchase Return (Excel Telecom)
        // Purchase 4 (30 days ago) - 10 HDMI cables @ 150 = 1500.00 paid cash
        $purchase4 = $purchaseService->createPurchase([
            'vendor_id' => $vendorExcelTelecom->id,
            'purchase_date' => now()->subDays(30)->toDateString(),
            'shipping_cost' => '0.00',
            'discount' => '0.00',
            'paid_amount' => '1500.00',
            'payment_method' => PaymentMethod::CASH,
            'account_id' => $cashAcc->id,
            'items' => [
                [
                    'product_id' => $prod7->id,
                    'qty' => '10.000',
                    'unit_cost' => '150.00',
                ],
            ],
        ], [], $admin);

        // Purchase Return (25 days ago) - Return 2 HDMI cables for cash refund
        $purchase4Item = $purchase4->items->first();
        $purchaseReturnService->createReturn(
            purchase: $purchase4,
            items: [
                [
                    'purchase_item_id' => $purchase4Item->id,
                    'qty' => '2.000',
                    'unit_price' => '150.00',
                ],
            ],
            settlement: ReturnSettlement::REFUND_RECEIVED,
            refundAccount: $cashAcc,
            refundMethod: PaymentMethod::CASH,
            reason: 'Excess units returned to vendor',
            returnDate: now()->subDays(25),
            userId: $admin->id
        );

        // 13. FIFO Sales Across Batches with Line & Bill Discounts
        // Sale 1 (20 days ago) - 12 pcs of Product 1 (10 from opening @ 2000 + 2 from Purchase 1 @ 2040)
        $sale1 = $saleService->createSale([
            'customer_id' => $custBismillah->id,
            'sale_date' => now()->subDays(20)->toDateString(),
            'items' => [
                [
                    'product_id' => $prod1->id,
                    'qty' => '12.000',
                    'discount' => '500.00', // Line discount
                ],
            ],
            'discount' => '300.00', // Bill discount
            'paid_amount' => '29200.00',
            'payment_method' => PaymentMethod::CASH,
            'account_id' => $cashAcc->id,
        ], $admin);

        // 14. Credit Sale with Partial Collection & Sale Return
        // Sale 2 (15 days ago) - Product 2 (2 pcs @ 6000) & Product 4 (4 pcs @ 2400) = 21,600.00
        $sale2 = $saleService->createSale([
            'customer_id' => $custAlAmin->id,
            'sale_date' => now()->subDays(15)->toDateString(),
            'items' => [
                [
                    'product_id' => $prod2->id,
                    'qty' => '2.000',
                    'discount' => '0.00',
                ],
                [
                    'product_id' => $prod4->id,
                    'qty' => '4.000',
                    'discount' => '0.00',
                ],
            ],
            'discount' => '0.00',
            'paid_amount' => '6600.00',
            'payment_method' => PaymentMethod::CASH,
            'account_id' => $cashAcc->id,
        ], $admin);

        // Customer Collection (10 days ago) - 5,000.00 collected via Bank
        $customerPaymentService->collect(
            customer: $custAlAmin,
            amount: '5000.00',
            method: PaymentMethod::BANK,
            account: $bankAcc,
            date: now()->subDays(10),
            reference: 'COLL-BANK-ALAMIN',
            note: 'Collection covering opening balance and partial sale 2',
            allocation: 'auto',
            userId: $admin->id
        );

        // Sale Return (8 days ago) - 1 pc of Product 4 returned
        $sale2RamItem = $sale2->items->where('product_id', $prod4->id)->first();
        $saleReturnService->createReturn(
            sale: $sale2,
            items: [
                [
                    'sale_item_id' => $sale2RamItem->id,
                    'qty' => '1.000',
                ],
            ],
            reason: 'Customer ordered 1 extra RAM by mistake',
            returnDate: now()->subDays(8),
            userId: $admin->id
        );

        // 15. Damage Adjustment (6 days ago)
        // 2 Logitech mice damaged in warehouse
        $stockAdjustmentService->adjust([
            'product_id' => $prod5->id,
            'type' => AdjustmentType::DAMAGE,
            'qty' => '2.000',
            'reason' => 'Water damage during warehouse cleaning',
            'adjusted_at' => now()->subDays(6)->toDateTimeString(),
        ], $admin);

        // 16. Expenses with Receipts (5 & 4 days ago)
        // Rent: 6,000.00 from Bank
        $accountService->record(new RecordTransactionData(
            accountId: $bankAcc->id,
            categoryId: $rentCat->id,
            type: TransactionType::OUT,
            amount: '6000.00',
            date: now()->subDays(5)->toDateString(),
            createdBy: $admin->id,
            description: 'Monthly Shop Rent Payment (Receipt #RN-1029)'
        ));

        // Electricity: 1,500.00 from Cash
        $accountService->record(new RecordTransactionData(
            accountId: $cashAcc->id,
            categoryId: $electricityCat->id,
            type: TransactionType::OUT,
            amount: '1500.00',
            date: now()->subDays(4)->toDateString(),
            createdBy: $admin->id,
            description: 'Monthly DESCO Electricity Bill'
        ));

        // 17. Owner Drawing and Profit Withdrawal (3 & 2 days ago)
        // Drawing: 4,000.00 from Cash
        $accountService->record(new RecordTransactionData(
            accountId: $cashAcc->id,
            categoryId: $ownerDrawingCat->id,
            type: TransactionType::OUT,
            amount: '4000.00',
            date: now()->subDays(3)->toDateString(),
            createdBy: $admin->id,
            description: 'Owner Personal Drawing'
        ));

        // Profit Withdrawal: 2,000.00 from Bank
        $accountService->record(new RecordTransactionData(
            accountId: $bankAcc->id,
            categoryId: $profitWithdrawalCat->id,
            type: TransactionType::OUT,
            amount: '2000.00',
            date: now()->subDays(2)->toDateString(),
            createdBy: $admin->id,
            description: 'Interim Profit Distribution'
        ));

        // 18. Transfer between Accounts (1 day ago)
        // Transfer 10,000.00 from Bank to Cash
        $accountService->transfer(
            fromAccountId: $bankAcc->id,
            toAccountId: $cashAcc->id,
            amount: '10000.00',
            date: now()->subDays(1)->toDateString(),
            userId: $admin->id,
            note: 'Cash replenishment for shop drawer'
        );
    }
}
