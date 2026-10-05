<?php

declare(strict_types=1);

namespace App\Filament\Resources\RestockRequests;

use App\Enums\RestockRequestStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\RestockRequests\Pages\CreateRestockRequest;
use App\Filament\Resources\RestockRequests\Pages\ListRestockRequests;
use App\Filament\Resources\RestockRequests\Pages\ViewRestockRequest;
use App\Models\Attachment;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\RestockRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestockRequestResource extends Resource
{
    protected static ?string $model = RestockRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false);
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false);
    }

    public static function getNavigationLabel(): string
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin()) {
            return 'My Restock Requests';
        }

        return 'Restock Requests';
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            return null;
        }

        $count = RestockRequest::where('status', RestockRequestStatus::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['requestedBy', 'reviewedBy', 'purchase', 'items.product', 'attachments']);

        /** @var User|null $user */
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin()) {
            return $query->where('requested_by', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Restock Request Information')
                    ->description('Specify products and quantities needed. Super Admin will review and process the purchase.')
                    ->schema([
                        Textarea::make('note')
                            ->label('Request Note / Reason')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('attachments')
                            ->label('Attachments (Photos, Invoices, Notes)')
                            ->multiple()
                            ->disk('private')
                            ->directory('attachments/restock_requests')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make('Requested Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('Products')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('qty_requested')
                                    ->label('Requested Quantity')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->required(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('request_no')
                            ->label('Request #')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (RestockRequestStatus $state): string => match ($state) {
                                RestockRequestStatus::PENDING => 'warning',
                                RestockRequestStatus::APPROVED => 'success',
                                RestockRequestStatus::REJECTED => 'danger',
                                RestockRequestStatus::CANCELLED => 'gray',
                            }),

                        TextEntry::make('requestedBy.name')
                            ->label('Requested By')
                            ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),

                        TextEntry::make('created_at')
                            ->label('Date Submitted')
                            ->dateTime(),

                        TextEntry::make('reviewedBy.name')
                            ->label('Reviewed By')
                            ->placeholder('-')
                            ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),

                        TextEntry::make('reviewed_at')
                            ->label('Reviewed At')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('purchase.invoice_no')
                            ->label('Linked Purchase')
                            ->placeholder('-')
                            ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),

                        TextEntry::make('review_note')
                            ->label('Review / Rejection Note')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (RestockRequest $record): bool => ! empty($record->review_note)),

                        TextEntry::make('note')
                            ->label('Requester Note')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Requested Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product'),
                                TextEntry::make('product.sku')
                                    ->label('SKU'),
                                TextEntry::make('qty_requested')
                                    ->label('Qty Requested'),
                                TextEntry::make('qty_approved')
                                    ->label('Qty Approved')
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Attachments (Super Admin Only)')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label('Filename')
                                    ->url(fn (Attachment $record): string => route('admin.attachments.download', $record->id))
                                    ->openUrlInNewTab(),
                                TextEntry::make('created_at')
                                    ->label('Uploaded At')
                                    ->dateTime(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_no')
                    ->label('Request #')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (RestockRequestStatus $state): string => match ($state) {
                        RestockRequestStatus::PENDING => 'warning',
                        RestockRequestStatus::APPROVED => 'success',
                        RestockRequestStatus::REJECTED => 'danger',
                        RestockRequestStatus::CANCELLED => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items Count'),

                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase.invoice_no')
                    ->label('Purchase Invoice')
                    ->placeholder('-')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RestockRequestStatus::PENDING->value => 'Pending',
                        RestockRequestStatus::APPROVED->value => 'Approved',
                        RestockRequestStatus::REJECTED->value => 'Rejected',
                        RestockRequestStatus::CANCELLED->value => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('Approve & Purchase')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (RestockRequest $record): bool => (bool) auth()->user()?->isSuperAdmin() && $record->isPending())
                    ->url(fn (RestockRequest $record): string => PurchaseResource::getUrl('create', ['restock_request_id' => $record->id])),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (RestockRequest $record): bool => (bool) auth()->user()?->isSuperAdmin() && $record->isPending())
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (RestockRequest $record, array $data): void {
                        app(RestockRequestService::class)->rejectRequest($record, $data['reason'], auth()->user());
                        Notification::make()
                            ->title('Restock Request Rejected')
                            ->warning()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (RestockRequest $record): bool => $record->isPending() && (auth()->user()?->isSuperAdmin() || $record->requested_by === auth()->id()))
                    ->action(function (RestockRequest $record): void {
                        app(RestockRequestService::class)->cancelRequest($record, auth()->user());
                        Notification::make()
                            ->title('Restock Request Cancelled')
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestockRequests::route('/'),
            'create' => CreateRestockRequest::route('/create'),
            'view' => ViewRestockRequest::route('/{record}'),
        ];
    }
}
