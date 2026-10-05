<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ManagerAddProduct extends Page
{
    protected static ?string $title = 'Add Product';

    protected static ?string $navigationLabel = 'Add Product';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'manager-add-product';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_add_products', false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_add_products', false);
    }

    public function mount(): void
    {
        $this->form->fill([
            'sale_price' => '0.00',
            'alert_qty' => '5.000',
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Product::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->description('Submit a new product for Super Admin review. Stock quantity starts at 0 and will be replenished via purchases or restock requests.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Product Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(Product::class, 'sku')
                            ->maxLength(100),

                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->unique(Product::class, 'barcode')
                            ->maxLength(100),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('unit_id')
                            ->label('Unit')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('brand')
                            ->label('Brand')
                            ->maxLength(100),

                        TextInput::make('sale_price')
                            ->label('Sale Price')
                            ->numeric()
                            ->prefix('৳')
                            ->required()
                            ->minValue(0.01),

                        TextInput::make('alert_qty')
                            ->label('Low Stock Alert Threshold')
                            ->numeric()
                            ->default('5.000')
                            ->required()
                            ->minValue(0),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Product Image')
                            ->image()
                            ->directory('products')
                            ->disk('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('create')
                    ->footer([
                        Actions::make([
                            Action::make('create')
                                ->label('Submit Product for Review')
                                ->submit('create'),
                        ]),
                    ]),
            ]);
    }

    public function create(): void
    {
        $rawState = $this->form->getState();

        // 1. Strict Server-side field allow-list: ignore any injected field (last_cost, stock_qty, needs_review, created_by, etc.)
        $allowedFields = [
            'name',
            'sku',
            'barcode',
            'category_id',
            'unit_id',
            'brand',
            'sale_price',
            'alert_qty',
            'description',
            'image',
        ];

        $data = Arr::only($rawState, $allowedFields);

        // 2. Warn (do not block) on similar existing name
        $name = trim((string) ($data['name'] ?? ''));
        $existingSimilar = Product::where('name', 'like', '%' . $name . '%')
            ->orWhereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($existingSimilar) {
            Notification::make()
                ->title('Duplicate Name Warning')
                ->body("A product with a similar name already exists in the system. The product was still submitted for review.")
                ->warning()
                ->send();
        }

        // 3. Create Product with server-enforced review flags & stock = 0
        $product = DB::transaction(function () use ($data) {
            $product = Product::create([
                ...$data,
                'last_cost' => '0.0000',
                'stock_qty' => '0.000',
                'is_active' => true,
                'needs_review' => true,
                'created_by' => auth()->id(),
            ]);

            // Write price history for initial sale price
            PriceHistory::create([
                'product_id' => $product->id,
                'old_cost' => null,
                'new_cost' => '0.0000',
                'old_sale_price' => null,
                'new_sale_price' => $product->sale_price,
                'reason' => 'Initial sale price on product creation by manager',
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->log("submitted product '{$product->name}' (SKU: {$product->sku}) for review");

            return $product;
        });

        Notification::make()
            ->title('Product Created')
            ->body("Product '{$product->name}' has been created and marked for Super Admin review.")
            ->success()
            ->send();

        // Reset form for next entry
        $this->form->fill([
            'sale_price' => '0.00',
            'alert_qty' => '5.000',
        ]);
    }
}
