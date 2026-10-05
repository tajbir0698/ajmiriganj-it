<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockOverview\Pages;

use App\Enums\BatchSource;
use App\Filament\Resources\StockOverview\StockOverviewResource;
use App\Models\Product;
use App\Services\FifoStockService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListStockOverview extends ListRecords
{
    protected static string $resource = StockOverviewResource::class;

    protected function getHeaderActions(): array
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return [
            Action::make('openingStock')
                ->label('Enter Opening Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible($isSuperAdmin)
                ->modalHeading('Enter Product Opening Stock')
                ->modalDescription('Creates an initial standalone inventory batch with source=opening.')
                ->modalSubmitActionLabel('Save Opening Stock')
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->options(fn () => Product::query()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Placeholder::make('movement_warning')
                        ->label('')
                        ->visible(function ($get): bool {
                            $pid = $get('product_id');
                            if (! $pid) {
                                return false;
                            }

                            return Product::find($pid)?->stockMovements()->exists() ?? false;
                        })
                        ->content(new HtmlString(
                            '<div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">'.
                            '<strong>Notice:</strong> This product already has stock movement history. Adding opening stock will create an additional standalone batch.'.
                            '</div>'
                        )),

                    TextInput::make('qty')
                        ->label('Opening Quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(0.001)
                        ->required(),

                    TextInput::make('unit_cost')
                        ->label('Per-Unit Cost')
                        ->numeric()
                        ->prefix('৳')
                        ->required()
                        ->default(fn ($get) => $get('product_id') ? Product::find($get('product_id'))?->last_cost ?? 0.00 : 0.00),

                    DatePicker::make('batch_date')
                        ->label('Opening Stock Date')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data, FifoStockService $fifoStockService): void {
                    DB::transaction(function () use ($data, $fifoStockService): void {
                        /** @var Product $product */
                        $product = Product::findOrFail((int) $data['product_id']);

                        $fifoStockService->addBatch(
                            product: $product,
                            qty: (string) $data['qty'],
                            unitCost: (string) $data['unit_cost'],
                            source: BatchSource::OPENING,
                            batchDate: Carbon::parse($data['batch_date']),
                            user: auth()->user()
                        );
                    });

                    Notification::make()
                        ->title('Opening stock batch added successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
