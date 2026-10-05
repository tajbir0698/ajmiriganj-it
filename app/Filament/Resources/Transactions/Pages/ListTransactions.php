<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Pages;

use App\DTOs\RecordTransactionData;
use App\Enums\AccountCategoryType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Services\AccountService;
use App\Services\AttachmentService;
use App\Services\BusinessFinanceService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Owner Investment
            Action::make('owner_investment')
                ->label('Owner Investment')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->modalHeading('Record Owner Investment (+ Capital In)')
                ->form([
                    Select::make('account_id')
                        ->label('Deposit Into Account')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->required(),

                    TextInput::make('amount')
                        ->label('Investment Amount')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('date')
                        ->label('Transaction Date')
                        ->default(now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->required(),

                    Textarea::make('description')
                        ->label('Note / Source of Funds')
                        ->placeholder('e.g. Additional personal capital injection')
                        ->rows(2),

                    FileUpload::make('attachments')
                        ->label('Deposit Slip / Document')
                        ->disk('private')
                        ->directory('attachments/transaction')
                        ->multiple(),
                ])
                ->action(function (array $data, AccountService $accountService, AttachmentService $attachmentService): void {
                    $cat = AccountCategory::firstOrCreate(
                        ['name' => 'Owner Investment'],
                        ['type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
                    );

                    $trx = $accountService->record(new RecordTransactionData(
                        accountId: (int) $data['account_id'],
                        type: TransactionType::IN,
                        amount: (string) $data['amount'],
                        categoryId: $cat->id,
                        date: $data['date'],
                        source: TransactionSource::MANUAL,
                        description: $data['description'] ?: 'Owner investment'
                    ));

                    if (! empty($data['attachments'])) {
                        $attachmentService->storeMany($data['attachments'], $trx);
                    }

                    Notification::make()->title('Owner investment recorded successfully')->success()->send();
                }),

            // 2. Owner Drawing
            Action::make('owner_drawing')
                ->label('Owner Drawing')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->modalHeading('Record Owner Drawing (Personal Withdrawal)')
                ->form([
                    Select::make('account_id')
                        ->label('Withdraw From Account')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->required(),

                    TextInput::make('amount')
                        ->label('Drawing Amount')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('date')
                        ->label('Transaction Date')
                        ->default(now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->required(),

                    Textarea::make('description')
                        ->label('Purpose / Note')
                        ->placeholder('e.g. Personal family expense withdrawal')
                        ->rows(2),

                    FileUpload::make('attachments')
                        ->label('Optional Slip / Document')
                        ->disk('private')
                        ->directory('attachments/transaction')
                        ->multiple(),
                ])
                ->action(function (array $data, AccountService $accountService, AttachmentService $attachmentService): void {
                    $cat = AccountCategory::firstOrCreate(
                        ['name' => 'Owner Drawing'],
                        ['type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
                    );

                    try {
                        $trx = $accountService->record(new RecordTransactionData(
                            accountId: (int) $data['account_id'],
                            type: TransactionType::OUT,
                            amount: (string) $data['amount'],
                            categoryId: $cat->id,
                            date: $data['date'],
                            source: TransactionSource::MANUAL,
                            description: $data['description'] ?: 'Owner personal drawing'
                        ));

                        if (! empty($data['attachments'])) {
                            $attachmentService->storeMany($data['attachments'], $trx);
                        }

                        Notification::make()->title('Owner drawing recorded successfully')->success()->send();
                    } catch (InsufficientFundsException $e) {
                        Notification::make()
                            ->title('Insufficient Funds')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // 3. Profit Withdrawal
            Action::make('profit_withdrawal')
                ->label('Profit Withdrawal')
                ->icon('heroicon-o-banknotes')
                ->color('purple')
                ->modalHeading('Record Profit Withdrawal')
                ->form(function (BusinessFinanceService $financeService): array {
                    $available = $financeService->availableProfitToWithdraw();

                    return [
                        Placeholder::make('available_profit')
                            ->label('Available Retained Profit')
                            ->content(fn () => '৳ '.Money::format($available))
                            ->helperText('Based on all-time net profit minus prior profit withdrawals.'),

                        Select::make('account_id')
                            ->label('Withdraw From Account')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                            ->required(),

                        TextInput::make('amount')
                            ->label('Withdrawal Amount')
                            ->numeric()
                            ->prefix('৳')
                            ->required()
                            ->minValue(0.01)
                            ->live(),

                        DatePicker::make('date')
                            ->label('Transaction Date')
                            ->default(now()->toDateString())
                            ->maxDate(now()->toDateString())
                            ->required(),

                        Checkbox::make('understand_warning')
                            ->label('I understand that this withdrawal exceeds currently available retained profit')
                            ->visible(fn ($get) => bccomp((string) ($get('amount') ?: '0'), $available, 2) > 0)
                            ->required(fn ($get) => bccomp((string) ($get('amount') ?: '0'), $available, 2) > 0),

                        Textarea::make('description')
                            ->label('Note')
                            ->placeholder('e.g. Dividend / profit share withdrawal')
                            ->rows(2),
                    ];
                })
                ->action(function (array $data, AccountService $accountService): void {
                    $cat = AccountCategory::firstOrCreate(
                        ['name' => 'Profit Withdrawal'],
                        ['type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
                    );

                    try {
                        $accountService->record(new RecordTransactionData(
                            accountId: (int) $data['account_id'],
                            type: TransactionType::OUT,
                            amount: (string) $data['amount'],
                            categoryId: $cat->id,
                            date: $data['date'],
                            source: TransactionSource::MANUAL,
                            description: $data['description'] ?: 'Profit withdrawal'
                        ));

                        Notification::make()->title('Profit withdrawal recorded successfully')->success()->send();
                    } catch (InsufficientFundsException $e) {
                        Notification::make()
                            ->title('Insufficient Funds')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // 4. Add Expense
            Action::make('add_expense')
                ->label('Add Expense')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->modalHeading('Record Operating Expense')
                ->form([
                    Select::make('category_id')
                        ->label('Expense Category')
                        ->options(fn () => AccountCategory::where('type', AccountCategoryType::EXPENSE->value)->where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Select::make('account_id')
                        ->label('Paid From Account')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->required(),

                    TextInput::make('amount')
                        ->label('Expense Amount')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('date')
                        ->label('Expense Date')
                        ->default(now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->required(),

                    TextInput::make('party_name')
                        ->label('Payee / Vendor Name')
                        ->placeholder('e.g. DESCO, Landlord, Internet Provider'),

                    Textarea::make('description')
                        ->label('Description / Memo')
                        ->placeholder('e.g. Office electricity bill for September 2026')
                        ->rows(2)
                        ->required(),

                    FileUpload::make('attachments')
                        ->label('Receipt / Bill Upload')
                        ->disk('private')
                        ->directory('attachments/transaction')
                        ->multiple(),
                ])
                ->action(function (array $data, AccountService $accountService, AttachmentService $attachmentService): void {
                    $description = $data['description'];
                    if (! empty($data['party_name'])) {
                        $description .= " (Paid to: {$data['party_name']})";
                    }

                    try {
                        $trx = $accountService->record(new RecordTransactionData(
                            accountId: (int) $data['account_id'],
                            type: TransactionType::OUT,
                            amount: (string) $data['amount'],
                            categoryId: (int) $data['category_id'],
                            date: $data['date'],
                            source: TransactionSource::MANUAL,
                            description: $description
                        ));

                        if (! empty($data['attachments'])) {
                            $attachmentService->storeMany($data['attachments'], $trx);
                        }

                        Notification::make()->title('Expense recorded successfully')->success()->send();
                    } catch (InsufficientFundsException $e) {
                        Notification::make()
                            ->title('Insufficient Funds')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // 5. Other Income
            Action::make('other_income')
                ->label('Other Income')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->modalHeading('Record Other Operating Income')
                ->form([
                    Select::make('category_id')
                        ->label('Income Category')
                        ->options(fn () => AccountCategory::where('type', AccountCategoryType::INCOME->value)->where('affects_profit', true)->where('is_active', true)->pluck('name', 'id'))
                        ->default(fn () => AccountCategory::where('name', 'Other Income')->first()?->id)
                        ->required(),

                    Select::make('account_id')
                        ->label('Deposit Into Account')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->required(),

                    TextInput::make('amount')
                        ->label('Income Amount')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('date')
                        ->label('Income Date')
                        ->default(now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->required(),

                    TextInput::make('party_name')
                        ->label('Payer / Customer Name')
                        ->placeholder('e.g. Scrap Buyer, Service Client'),

                    Textarea::make('description')
                        ->label('Description / Memo')
                        ->placeholder('e.g. Sale of scrap packaging cartons')
                        ->rows(2)
                        ->required(),

                    FileUpload::make('attachments')
                        ->label('Document Upload')
                        ->disk('private')
                        ->directory('attachments/transaction')
                        ->multiple(),
                ])
                ->action(function (array $data, AccountService $accountService, AttachmentService $attachmentService): void {
                    $description = $data['description'];
                    if (! empty($data['party_name'])) {
                        $description .= " (Received from: {$data['party_name']})";
                    }

                    $trx = $accountService->record(new RecordTransactionData(
                        accountId: (int) $data['account_id'],
                        type: TransactionType::IN,
                        amount: (string) $data['amount'],
                        categoryId: (int) $data['category_id'],
                        date: $data['date'],
                        source: TransactionSource::MANUAL,
                        description: $description
                    ));

                    if (! empty($data['attachments'])) {
                        $attachmentService->storeMany($data['attachments'], $trx);
                    }

                    Notification::make()->title('Other income recorded successfully')->success()->send();
                }),

            // 6. Transfer between Accounts
            Action::make('transfer')
                ->label('Transfer')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->modalHeading('Transfer between Accounts')
                ->form([
                    Select::make('from_account_id')
                        ->label('Source Account (From)')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->required(),

                    Select::make('to_account_id')
                        ->label('Destination Account (To)')
                        ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                        ->different('from_account_id')
                        ->required(),

                    TextInput::make('amount')
                        ->label('Transfer Amount')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('date')
                        ->label('Transfer Date')
                        ->default(now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->required(),

                    Textarea::make('note')
                        ->label('Transfer Note')
                        ->placeholder('e.g. Cash deposited into bank account')
                        ->rows(2),
                ])
                ->action(function (array $data, AccountService $accountService): void {
                    try {
                        $accountService->transfer(
                            fromAccountId: (int) $data['from_account_id'],
                            toAccountId: (int) $data['to_account_id'],
                            amount: (string) $data['amount'],
                            date: $data['date'],
                            note: $data['note']
                        );

                        Notification::make()->title('Funds transferred successfully')->success()->send();
                    } catch (InsufficientFundsException $e) {
                        Notification::make()
                            ->title('Insufficient Funds')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
