<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\RecordTransactionData;
use App\Enums\AccountCategoryType;
use App\Enums\AccountKind;
use App\Enums\AdjustmentType;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\SaleStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Filament\Pages\CashBook;
use App\Filament\Pages\OwnerStatement;
use App\Filament\Resources\AccountCategories\AccountCategoryResource;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Widgets\AccountBalancesWidget;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountService;
use App\Services\AttachmentService;
use App\Services\BusinessFinanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles
    $adminRole = Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    $managerRole = Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@ajmiriganj.test',
    ]);
    $this->admin->assignRole($adminRole);

    $this->manager = User::factory()->create([
        'name' => 'Manager User',
        'email' => 'manager@ajmiriganj.test',
    ]);
    $this->manager->assignRole($managerRole);

    // Call seeders for accounts & settings
    $this->seed(\Database\Seeders\AccountCategorySeeder::class);
    $this->seed(\Database\Seeders\AccountSeeder::class);
    $this->seed(\Database\Seeders\SettingSeeder::class);

    $this->accountService = app(AccountService::class);
    $this->financeService = app(BusinessFinanceService::class);
});

/*
|--------------------------------------------------------------------------
| 1. AccountService Tests
|--------------------------------------------------------------------------
*/

test('record transaction updates balance correctly and balances() matches balance()', function () {
    $account = Account::query()->where('name', 'Cash')->first();
    $account->update(['opening_balance' => '500.00']);

    $category = AccountCategory::query()->where('name', 'Owner Investment')->first();

    // Inflow of 1000
    $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $category->id,
        type: TransactionType::IN,
        amount: '1000.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Initial deposit',
        createdBy: $this->admin->id,
    ));

    // Outflow of 200
    $expenseCat = AccountCategory::query()->where('name', 'Rent')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $expenseCat->id,
        type: TransactionType::OUT,
        amount: '200.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Office rent',
        createdBy: $this->admin->id,
    ));

    // 500 opening + 1000 in - 200 out = 1300.00
    $expected = '1300.00';
    expect($this->accountService->balance($account))->toBe($expected);
    expect($this->accountService->balance($account->id))->toBe($expected);

    $balances = $this->accountService->balances([$account->id]);
    expect($balances[$account->id])->toBe($expected);
});

test('outflow beyond balance throws InsufficientFundsException when negative balance disallowed', function () {
    Setting::set('allow_negative_balance', false);

    $account = Account::query()->where('name', 'Cash')->first();
    $account->update(['opening_balance' => '100.00']);

    $expenseCat = AccountCategory::query()->where('name', 'Rent')->first();

    expect(fn () => $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $expenseCat->id,
        type: TransactionType::OUT,
        amount: '150.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    )))->toThrow(InsufficientFundsException::class);
});

test('outflow beyond balance is allowed when allow_negative_balance is true', function () {
    Setting::set('allow_negative_balance', true);

    $account = Account::query()->where('name', 'Cash')->first();
    $account->update(['opening_balance' => '100.00']);

    $expenseCat = AccountCategory::query()->where('name', 'Rent')->first();

    $tx = $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $expenseCat->id,
        type: TransactionType::OUT,
        amount: '150.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    ));

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($this->accountService->balance($account->id))->toBe('-50.00');
});

test('entry rejected with validation error if date in future or amount <= 0; entry dated today succeeds in Asia/Dhaka', function () {
    $account = Account::query()->where('name', 'Cash')->first();
    $category = AccountCategory::query()->where('name', 'Other Income')->first();

    // 1. Future date rejected
    $futureDate = Carbon::now('Asia/Dhaka')->addDays(2)->toDateString();
    expect(fn () => $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $category->id,
        type: TransactionType::IN,
        amount: '100.00',
        date: $futureDate,
        createdBy: $this->admin->id,
    )))->toThrow(InvalidArgumentException::class, 'cannot be in the future');

    // 2. Zero amount rejected
    expect(fn () => $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $category->id,
        type: TransactionType::IN,
        amount: '0.00',
        date: Carbon::now('Asia/Dhaka')->toDateString(),
        createdBy: $this->admin->id,
    )))->toThrow(InvalidArgumentException::class, 'strictly greater than 0');

    // 3. Negative amount rejected
    expect(fn () => $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $category->id,
        type: TransactionType::IN,
        amount: '-10.00',
        date: Carbon::now('Asia/Dhaka')->toDateString(),
        createdBy: $this->admin->id,
    )))->toThrow(InvalidArgumentException::class, 'strictly greater than 0');

    // 4. Entry dated today in Asia/Dhaka succeeds
    $todayDhaka = Carbon::now('Asia/Dhaka')->toDateString();
    $tx = $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $category->id,
        type: TransactionType::IN,
        amount: '100.00',
        date: $todayDhaka,
        createdBy: $this->admin->id,
    ));
    expect($tx)->toBeInstanceOf(Transaction::class);
});

test('transfer creates paired transactions with matching transfer_group_id', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();

    $cash->update(['opening_balance' => '1000.00']);
    $bank->update(['opening_balance' => '0.00']);

    [$outTx, $inTx] = $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '400.00',
        date: today()->toDateString(),
        note: 'Deposit cash into bank',
        userId: $this->admin->id,
    );

    expect($outTx->transfer_group_id)->not->toBeNull();
    expect($inTx->transfer_group_id)->toBe($outTx->transfer_group_id);
    expect($outTx->type->value)->toBe('out');
    expect($inTx->type->value)->toBe('in');
    expect($outTx->amount)->toBe('400.00');
    expect($inTx->amount)->toBe('400.00');

    expect($this->accountService->balance($cash->id))->toBe('600.00');
    expect($this->accountService->balance($bank->id))->toBe('400.00');
});

test('same account transfer and transfer with insufficient funds are rejected', function () {
    Setting::set('allow_negative_balance', false);

    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();
    $cash->update(['opening_balance' => '100.00']);

    // Same account
    expect(fn () => $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $cash->id,
        amount: '50.00',
        date: today()->toDateString(),
        userId: $this->admin->id,
    ))->toThrow(InvalidArgumentException::class, 'must be distinct');

    // Insufficient funds
    expect(fn () => $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '200.00',
        date: today()->toDateString(),
        userId: $this->admin->id,
    ))->toThrow(InsufficientFundsException::class);

    // Verify nothing created
    expect(Transaction::where('source', TransactionSource::TRANSFER->value)->count())->toBe(0);
});

test('manual reversal creates opposite row, links references and restores balances', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '500.00']);
    $cat = AccountCategory::query()->where('name', 'Rent')->first();

    $orig = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::OUT,
        amount: '200.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Accidental rent payment',
        createdBy: $this->admin->id,
    ));

    expect($this->accountService->balance($cash->id))->toBe('300.00');

    $reversal = $this->accountService->reverse($orig, 'Wrong amount entered', $this->admin->id);

    expect($reversal->type->value)->toBe('in');
    expect($reversal->amount)->toBe('200.00');
    expect($reversal->reversal_of_id)->toBe($orig->id);
    expect($reversal->reversal_reason)->toBe('Wrong amount entered');

    $orig->refresh();
    expect($orig->reversed_at)->not->toBeNull();
    expect($orig->reversed_by_id)->toBe($reversal->id);
    expect($orig->reversal_reason)->toBe('Wrong amount entered');

    // Balance restored
    expect($this->accountService->balance($cash->id))->toBe('500.00');
});

test('cannot reverse an already-reversed transaction or a reversal transaction', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '500.00']);
    $cat = AccountCategory::query()->where('name', 'Rent')->first();

    $orig = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::OUT,
        amount: '100.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    ));

    $reversal = $this->accountService->reverse($orig, 'Error', $this->admin->id);

    // Cannot reverse again
    expect(fn () => $this->accountService->reverse($orig, 'Second attempt', $this->admin->id))
        ->toThrow(InvalidArgumentException::class, 'already been reversed');

    // Cannot reverse a reversal
    expect(fn () => $this->accountService->reverse($reversal, 'Reverse reversal', $this->admin->id))
        ->toThrow(InvalidArgumentException::class, 'already a reversal');
});

test('cannot reverse system transaction without override and reversal of a transfer cascades', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();
    $cash->update(['opening_balance' => '1000.00']);

    // 1. System transaction reversal forbidden
    $sysCat = AccountCategory::query()->where('name', 'Sales Income')->first();
    $sysTx = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $sysCat->id,
        type: TransactionType::IN,
        amount: '300.00',
        date: today()->toDateString(),
        source: TransactionSource::SYSTEM,
        createdBy: $this->admin->id,
    ));

    expect(fn () => $this->accountService->reverse($sysTx, 'Try reversing system', $this->admin->id))
        ->toThrow(InvalidArgumentException::class, 'System-generated transactions cannot be reversed');

    // 2. Cascade transfer reversal
    [$outTx, $inTx] = $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '250.00',
        date: today()->toDateString(),
        note: 'Test transfer',
        userId: $this->admin->id,
    );

    // Reverse the out leg
    $this->accountService->reverse($outTx, 'Cancel transfer', $this->admin->id);

    $outTx->refresh();
    $inTx->refresh();

    expect($outTx->reversed_at)->not->toBeNull();
    expect($inTx->reversed_at)->not->toBeNull();

    expect($this->accountService->balance($cash->id))->toBe('1300.00'); // 1000 + 300 sys
    expect($this->accountService->balance($bank->id))->toBe('0.00');
});

test('ledger calculates opening balance, running balances, and closing balance correctly with reversals', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '100.00']);

    $incCat = AccountCategory::query()->where('name', 'Other Income')->first();
    $expCat = AccountCategory::query()->where('name', 'Rent')->first();

    // Day 1: deposit 50
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $incCat->id,
        type: TransactionType::IN,
        amount: '50.00',
        date: '2026-09-01',
        createdBy: $this->admin->id,
    ));

    // Today: expense 30
    $txToReverse = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $expCat->id,
        type: TransactionType::OUT,
        amount: '30.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    ));

    // Today: reverse expense 30
    $this->accountService->reverse($txToReverse, 'Refund rent', $this->admin->id);

    $ledger = $this->accountService->ledger($cash->id, today()->toDateString(), today()->toDateString());

    // Opening today should be 100 + 50 = 150.00
    expect($ledger->openingBalance)->toBe('150.00');
    expect($ledger->totalIn)->toBe('30.00');  // The reversal row
    expect($ledger->totalOut)->toBe('30.00'); // The original expense row
    expect($ledger->closingBalance)->toBe('150.00');
    expect($ledger->rows)->toHaveCount(2);
});

test('voucher numbers are unique and sequential without gaps', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cat = AccountCategory::query()->where('name', 'Other Income')->first();

    $tx1 = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::IN,
        amount: '10.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    $tx2 = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::IN,
        amount: '20.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    expect($tx1->voucher_no)->toBe('VCH-000001');
    expect($tx2->voucher_no)->toBe('VCH-000002');
});

/*
|--------------------------------------------------------------------------
| 2. BusinessFinanceService Tests
|--------------------------------------------------------------------------
*/

test('worked profit example matches expected numbers exactly: gross 340 + other 20 - op_exp 100 - stock_loss 50 = net 210', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '5000.00']);

    // 1. Gross Profit 340 from a Sale
    $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'allow_decimal' => false]);
    $cat = Category::create(['name' => 'General', 'is_active' => true]);
    $product = Product::create([
        'name' => 'Widget A',
        'sku' => 'WID-001',
        'unit_id' => $unit->id,
        'category_id' => $cat->id,
        'cost_price' => '100.00',
        'sale_price' => '150.00',
        'stock_qty' => '10.000',
        'is_active' => true,
    ]);

    // Create a sale with net_profit = 340.00
    Sale::create([
        'invoice_no' => 'INV-TEST-001',
        'sale_date' => today()->toDateString(),
        'subtotal' => '1000.00',
        'discount' => '0.00',
        'total' => '1000.00',
        'paid_amount' => '1000.00',
        'due_amount' => '0.00',
        'gross_profit' => '340.00',
        'net_profit' => '340.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    // 2. Pay rent 100 (Operating expense with affects_profit = true)
    $rentCat = AccountCategory::query()->where('name', 'Rent')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $rentCat->id,
        type: TransactionType::OUT,
        amount: '100.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Monthly Rent',
        createdBy: $this->admin->id,
    ));

    // 3. Damage loss 50
    StockAdjustment::create([
        'adjustment_no' => 'ADJ-001',
        'product_id' => $product->id,
        'type' => AdjustmentType::DAMAGE,
        'qty' => '1.000',
        'unit_cost' => '50.0000',
        'total_cost' => '50.00',
        'reason' => 'Damaged during handling',
        'adjusted_at' => today()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    // 4. Other income 20 (Income with affects_profit = true)
    $otherIncCat = AccountCategory::query()->where('name', 'Other Income')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $otherIncCat->id,
        type: TransactionType::IN,
        amount: '20.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Scrap sales',
        createdBy: $this->admin->id,
    ));

    // Check calculations
    expect($this->financeService->grossProfit())->toBe('340.00');
    expect($this->financeService->otherIncome())->toBe('20.00');
    expect($this->financeService->operatingExpenses())->toBe('100.00');
    expect($this->financeService->stockLosses())->toBe('50.00');

    // 340 + 20 - 100 - 50 = 210.00
    expect($this->financeService->netProfit())->toBe('210.00');
});

test('expense 100 then reversed results in operating_expenses = 0 without double counting', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '500.00']);
    $rentCat = AccountCategory::query()->where('name', 'Rent')->first();

    $orig = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $rentCat->id,
        type: TransactionType::OUT,
        amount: '100.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Test expense',
        createdBy: $this->admin->id,
    ));

    expect($this->financeService->operatingExpenses())->toBe('100.00');

    // Reverse the expense
    $this->accountService->reverse($orig, 'Refunded by landlord', $this->admin->id);

    // Sum of ALL rows (100 out, 100 in) results in 0.00
    expect($this->financeService->operatingExpenses())->toBe('0.00');
});

test('vendor payment, owner investment, drawings, profit withdrawals, and transfers do not affect net profit', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();
    $cash->update(['opening_balance' => '10000.00']);

    $initialNetProfit = $this->financeService->netProfit();

    // 1. Vendor Payment (affects_profit = false)
    $vpCat = AccountCategory::query()->where('name', 'Vendor Payment')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $vpCat->id,
        type: TransactionType::OUT,
        amount: '1000.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // 2. Owner Investment
    $invCat = AccountCategory::query()->where('name', 'Owner Investment')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $invCat->id,
        type: TransactionType::IN,
        amount: '2000.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // 3. Owner Drawing
    $drawCat = AccountCategory::query()->where('name', 'Owner Drawing')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $drawCat->id,
        type: TransactionType::OUT,
        amount: '500.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // 4. Profit Withdrawal
    $pwCat = AccountCategory::query()->where('name', 'Profit Withdrawal')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $pwCat->id,
        type: TransactionType::OUT,
        amount: '300.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // 5. Transfer
    $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '1000.00',
        date: today()->toDateString(),
        userId: $this->admin->id,
    );

    // None of these should affect net profit
    expect($this->financeService->netProfit())->toBe($initialNetProfit);
});

test('owner capital = investment - drawings + (net profit - profit withdrawals)', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '10000.00']);

    // Investment: 5000
    $invCat = AccountCategory::query()->where('name', 'Owner Investment')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $invCat->id,
        type: TransactionType::IN,
        amount: '5000.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // Drawing: 1000
    $drawCat = AccountCategory::query()->where('name', 'Owner Drawing')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $drawCat->id,
        type: TransactionType::OUT,
        amount: '1000.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // Net profit from a sale: 800
    Sale::create([
        'invoice_no' => 'INV-TEST-002',
        'sale_date' => today()->toDateString(),
        'subtotal' => '2000.00',
        'discount' => '0.00',
        'total' => '2000.00',
        'paid_amount' => '2000.00',
        'due_amount' => '0.00',
        'gross_profit' => '800.00',
        'net_profit' => '800.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    // Profit withdrawal: 300
    $pwCat = AccountCategory::query()->where('name', 'Profit Withdrawal')->first();
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $pwCat->id,
        type: TransactionType::OUT,
        amount: '300.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    // Retained profit = 800 - 300 = 500.00
    expect($this->financeService->retainedProfit())->toBe('500.00');

    // Owner capital = 5000 - 1000 + 500 = 4500.00
    expect($this->financeService->ownerCapital())->toBe('4500.00');

    // Available profit to withdraw = 500.00
    expect($this->financeService->availableProfitToWithdraw())->toBe('500.00');
});

test('credit unpaid sales count in profit on accrual basis', function () {
    // A sale with paid_amount = 0 and due_amount = 500, net_profit = 200
    Sale::create([
        'invoice_no' => 'INV-CREDIT-001',
        'sale_date' => today()->toDateString(),
        'subtotal' => '500.00',
        'discount' => '0.00',
        'total' => '500.00',
        'paid_amount' => '0.00',
        'due_amount' => '500.00',
        'gross_profit' => '200.00',
        'net_profit' => '200.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    expect($this->financeService->grossProfit())->toBe('200.00');
});

/*
|--------------------------------------------------------------------------
| 3. Filament UI & Livewire Tests
|--------------------------------------------------------------------------
*/

test('add expense stores receipt attachment in private storage attached to Transaction', function () {
    Storage::fake('private');

    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '1000.00']);
    $rentCat = AccountCategory::query()->where('name', 'Rent')->first();

    $tx = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $rentCat->id,
        type: TransactionType::OUT,
        amount: '250.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        description: 'Rent with receipt',
        createdBy: $this->admin->id,
    ));

    $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
    $attachment = app(AttachmentService::class)->store($file, $tx, $this->admin);

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->attachable_type)->toBe(Transaction::class);
    expect($attachment->attachable_id)->toBe($tx->id);
    Storage::disk('private')->assertExists($attachment->file_path);
});

test('system categories cannot be edited or deleted', function () {
    $sysCat = AccountCategory::query()->where('is_system', true)->first();
    expect($sysCat)->not->toBeNull();

    // Gate checks
    expect(Gate::forUser($this->admin)->allows('delete', $sysCat))->toBeFalse();
    expect(Gate::forUser($this->admin)->allows('update', $sysCat))->toBeFalse();

    // Non-system category can be updated/deleted
    $customCat = AccountCategory::create([
        'name' => 'Custom Office Supplies',
        'type' => AccountCategoryType::EXPENSE,
        'affects_profit' => true,
        'is_system' => false,
        'is_active' => true,
    ]);

    expect(Gate::forUser($this->admin)->allows('update', $customCat))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('delete', $customCat))->toBeTrue();
});

test('account with transactions cannot be deleted', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '500.00']);
    $cat = AccountCategory::query()->where('name', 'Other Income')->first();

    expect(Gate::forUser($this->admin)->allows('delete', $cash))->toBeTrue();

    // Create a transaction
    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::IN,
        amount: '50.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    expect(Gate::forUser($this->admin)->allows('delete', $cash))->toBeFalse();
});

test('changing opening balance after account has transactions requires explicit confirmation step', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '500.00']);
    $cat = AccountCategory::query()->where('name', 'Other Income')->first();

    $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::IN,
        amount: '50.00',
        date: today()->toDateString(),
        createdBy: $this->admin->id,
    ));

    $this->actingAs($this->admin);

    // Attempting edit without confirmation
    $component = \Livewire\Livewire::test(
        \App\Filament\Resources\Accounts\Pages\EditAccount::class,
        ['record' => $cash->getRouteKey()]
    )
        ->fillForm([
            'name' => $cash->name,
            'type' => $cash->type->value,
            'opening_balance' => '600.00',
            'confirm_opening_balance_change' => false,
        ])
        ->call('save');

    $component->assertHasFormErrors(['opening_balance']);
    $cash->refresh();
    expect($cash->opening_balance)->toBe('500.00');

    // Attempting edit with confirmation
    $component->fillForm([
        'name' => $cash->name,
        'type' => $cash->type->value,
        'opening_balance' => '600.00',
        'confirm_opening_balance_change' => true,
    ])->call('save');

    $component->assertHasNoFormErrors();
    $cash->refresh();
    expect($cash->opening_balance)->toBe('600.00');

    // Check activity log
    $logged = Activity::query()
        ->where('log_name', 'accounts')
        ->where('subject_id', $cash->id)
        ->latest()
        ->first();

    expect($logged)->not->toBeNull();
    expect($logged->description)->toContain('Updated opening balance');
});

test('purchase create form catches InsufficientFundsException and notifies user with available balance', function () {
    $this->actingAs($this->admin);

    $cash = Account::query()->where('name', 'Cash')->first();
    $cash->update(['opening_balance' => '100.00']);
    Setting::set('allow_negative_balance', false);

    $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'allow_decimal' => false]);
    $cat = Category::create(['name' => 'Hardware', 'is_active' => true]);
    $product = Product::create([
        'name' => 'SSD 500GB',
        'sku' => 'SSD-500',
        'unit_id' => $unit->id,
        'category_id' => $cat->id,
        'cost_price' => '200.00',
        'sale_price' => '300.00',
        'stock_qty' => '0.000',
        'is_active' => true,
    ]);
    $vendor = Vendor::create([
        'name' => 'Tech Supply Co',
        'is_active' => true,
    ]);

    \Livewire\Livewire::test(\App\Filament\Resources\Purchases\Pages\CreatePurchase::class)
        ->fillForm([
            'vendor_id' => $vendor->id,
            'purchase_date' => today()->toDateString(),
            'vendor_invoice_no' => 'INV-OVERDRAFT-01',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 5,
                    'unit_cost' => 200,
                    'new_sale_price' => 300,
                ],
            ],
            'shipping_cost' => 0,
            'discount' => 0,
            'paid_amount' => 500.00, // Available balance is only 100.00
            'payment_method' => PaymentMethod::CASH->value,
            'account_id' => $cash->id,
        ])
        ->call('create')
        ->assertNotified('Insufficient Funds');

    // Confirm purchase was NOT created
    expect(Purchase::where('vendor_invoice_no', 'INV-OVERDRAFT-01')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 4. Permissions Tests
|--------------------------------------------------------------------------
*/

test('manager role gets 403 on accounts, categories, transactions, owner statement, cash book, and attachments', function () {
    $this->actingAs($this->manager);

    $account = Account::query()->first();
    $account->update(['opening_balance' => '100.00']);
    $cat = AccountCategory::query()->first();

    $tx = $this->accountService->record(new RecordTransactionData(
        accountId: $account->id,
        categoryId: $cat->id,
        type: TransactionType::IN,
        amount: '10.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    ));

    // Attachment
    $attachment = Attachment::create([
        'attachable_type' => Transaction::class,
        'attachable_id' => $tx->id,
        'file_path' => 'attachments/transaction/test.pdf',
        'original_name' => 'test.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'uploaded_by' => $this->admin->id,
    ]);

    // 1. Accounts
    $this->get(AccountResource::getUrl('index'))->assertForbidden();
    $this->get(AccountResource::getUrl('create'))->assertForbidden();
    $this->get(AccountResource::getUrl('edit', ['record' => $account]))->assertForbidden();

    // 2. Account Categories
    $this->get(AccountCategoryResource::getUrl('index'))->assertForbidden();

    // 3. Transactions
    $this->get(TransactionResource::getUrl('index'))->assertForbidden();

    // 4. Custom Pages
    $this->get(OwnerStatement::getUrl())->assertForbidden();
    $this->get(CashBook::getUrl())->assertForbidden();

    // 5. Private Attachment Download
    $this->get(route('admin.attachments.download', ['attachment' => $attachment]))->assertForbidden();
});

test('manager cannot see the account balances widget', function () {
    $this->actingAs($this->manager);
    expect(AccountBalancesWidget::canView())->toBeFalse();

    $this->actingAs($this->admin);
    expect(AccountBalancesWidget::canView())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 5. Integrity Command Tests (php artisan accounts:check)
|--------------------------------------------------------------------------
*/

test('accounts:check command passes on clean data', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();
    $cash->update(['opening_balance' => '1000.00']);

    // Valid transfer pair
    $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '200.00',
        date: today()->toDateString(),
        userId: $this->admin->id,
    );

    $this->artisan('accounts:check')
        ->expectsOutputToContain('integrity checks PASSED')
        ->assertExitCode(0);
});

test('accounts:check flags tampered transfer pair, orphan reversal, and missing sale transactions', function () {
    $cash = Account::query()->where('name', 'Cash')->first();
    $bank = Account::query()->where('name', 'Bank Account')->first();
    $cash->update(['opening_balance' => '1000.00']);

    // 1. Create a transfer pair then tamper with it (change transfer_group_id so outTx has no match)
    [$outTx, $inTx] = $this->accountService->transfer(
        fromAccountId: $cash->id,
        toAccountId: $bank->id,
        amount: '200.00',
        date: today()->toDateString(),
        userId: $this->admin->id,
    );
    $inTx->update(['transfer_group_id' => (string) Str::uuid()]);

    // 2. Create orphan reversal
    $cat = AccountCategory::query()->where('name', 'Other Income')->first();
    // 2. Create reversal then tamper with its amount to trigger Reversal Amount Mismatch
    $cat = AccountCategory::query()->where('name', 'Rent')->first();
    $origTx = $this->accountService->record(new RecordTransactionData(
        accountId: $cash->id,
        categoryId: $cat->id,
        type: TransactionType::OUT,
        amount: '50.00',
        date: today()->toDateString(),
        source: TransactionSource::MANUAL,
        createdBy: $this->admin->id,
    ));
    $revTx = $this->accountService->reverse($origTx, 'Tamper test', $this->admin->id);
    $revTx->update(['amount' => '999.00']);

    // 3. Create completed sale with paid amount > 0 without transaction
    Sale::create([
        'invoice_no' => 'INV-UNPAID-CHECK',
        'sale_date' => today()->toDateString(),
        'subtotal' => '500.00',
        'discount' => '0.00',
        'total' => '500.00',
        'paid_amount' => '500.00',
        'due_amount' => '0.00',
        'gross_profit' => '200.00',
        'net_profit' => '200.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    $this->artisan('accounts:check')
        ->expectsOutputToContain('Transfer Pairing')
        ->expectsOutputToContain('Reversal Amount Mismatch')
        ->expectsOutputToContain('Sale Payment Reconciliation')
        ->assertExitCode(1);
});

/*
|--------------------------------------------------------------------------
| 6. Architecture Test
|--------------------------------------------------------------------------
*/

test('no direct Transaction::create or insert calls outside AccountService and tests/factories', function () {
    $appDir = app_path();
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appDir));

    $violations = [];

    /** @var \SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getRealPath();

        // AccountService itself is the sole authorized writer
        if (str_ends_with($path, 'AccountService.php')) {
            continue;
        }

        $content = file_get_contents($path);

        if (
            preg_match('/Transaction::create\s*\(/', $content) ||
            preg_match('/Transaction::insert\s*\(/', $content) ||
            preg_match('/Transaction::forceCreate\s*\(/', $content) ||
            preg_match('/\$account->transactions\(\)->create\s*\(/', $content)
        ) {
            $violations[] = $path;
        }
    }

    expect($violations)->toBeEmpty('Direct writes to Transaction found outside AccountService: '.implode(', ', $violations));
});
