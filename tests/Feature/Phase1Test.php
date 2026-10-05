<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\CategoryAndProductSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        UserSeeder::class,
        UnitSeeder::class,
        SettingSeeder::class,
        CategoryAndProductSeeder::class,
    ]);

    $this->superAdmin = User::where('email', 'admin@ajmiriganj.com')->first();
    $this->manager = User::where('email', 'manager@ajmiriganj.com')->first();
});

test('money helper formats english bangladeshi taka strictly as ৳ 1,250.00', function () {
    expect(Money::format('1250.00'))->toBe('৳ 1,250.00')
        ->and(Money::format(1250))->toBe('৳ 1,250.00')
        ->and(Money::format(0))->toBe('৳ 0.00')
        ->and(Money::format('1250000.50'))->toBe('৳ 1,250,000.50');
});

test('money helper formats quantities without redundant trailing zeros', function () {
    expect(Money::formatQty('5.000'))->toBe('5')
        ->and(Money::formatQty('5.250'))->toBe('5.25')
        ->and(Money::formatQty('5.125'))->toBe('5.125')
        ->and(Money::formatQty(10))->toBe('10');
});

test('money helper performs safe bcmath arithmetic', function () {
    expect(Money::add('10.25', '5.75'))->toBe('16.00')
        ->and(Money::sub('10.00', '3.25'))->toBe('6.75')
        ->and(Money::mul('10.50', '3'))->toBe('31.50')
        ->and(Money::div('100.00', '3', 2))->toBe('33.33');
});

test('settings model stores and typecasts values correctly', function () {
    expect(is_bool(Setting::get('credit_sales_enabled')))->toBeTrue()
        ->and(Setting::get('shop_name'))->toContain('Ajmiriganj IT')
        ->and(Setting::get('low_stock_threshold'))->toBe(5);

    Setting::set('credit_sales_enabled', false, 'boolean');
    expect(Setting::get('credit_sales_enabled'))->toBeFalse();

    Setting::set('target_margin', 25.5, 'float');
    expect(Setting::get('target_margin'))->toBe(25.5);
});

test('categories and units support relationships and hierarchy', function () {
    $parent = Category::create(['name' => 'Computers']);
    $child = Category::create(['name' => 'Laptops', 'parent_id' => $parent->id]);

    expect($child->parent->name)->toBe('Computers')
        ->and($parent->children->first()->name)->toBe('Laptops');

    $unit = Unit::where('short_name', 'Pcs')->first();
    expect($unit)->not->toBeNull()
        ->and($unit->products()->count())->toBeGreaterThan(0);
});

test('products retain decimal precision and support soft deletes', function () {
    $unit = Unit::first();
    $product = Product::create([
        'sku' => 'TEST-SKU-001',
        'barcode' => '1234567890123',
        'name' => 'Test Product',
        'unit_id' => $unit->id,
        'last_cost' => '123.45',
        'sale_price' => '199.99',
        'stock_qty' => '10.500',
        'alert_qty' => '2.000',
        'is_active' => true,
    ]);

    expect($product->last_cost)->toBe('123.4500')
        ->and($product->sale_price)->toBe('199.99')
        ->and($product->stock_qty)->toBe('10.500')
        ->and($product->alert_qty)->toBe('2.000')
        ->and($product->isLowStock())->toBeFalse();

    // Soft delete
    $product->delete();
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();

    // Restore
    $product->restore();
    expect(Product::find($product->id))->not->toBeNull();
});

test('server-side cost redaction hides last_cost from manager in serialization and accessors', function () {
    $product = Product::first();

    // As Manager
    $this->actingAs($this->manager);
    $managerArray = $product->toArray();
    expect(array_key_exists('last_cost', $managerArray))->toBeFalse()
        ->and($product->formatted_last_cost)->toBeNull();

    // As Super Admin
    $this->actingAs($this->superAdmin);
    $adminArray = $product->toArray();
    expect(array_key_exists('last_cost', $adminArray))->toBeTrue()
        ->and($adminArray['last_cost'])->toBe($product->last_cost)
        ->and($product->formatted_last_cost)->toBe(Money::format($product->last_cost));
});

test('policies permit manager to view stock and price only and block cost or modifications', function () {
    $product = Product::first();

    // Super Admin permissions
    expect(Gate::forUser($this->superAdmin)->allows('viewAny', Product::class))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('view', $product))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('create', Product::class))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('update', $product))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('delete', $product))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('viewCost', $product))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('viewAny', Category::class))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('viewAny', Unit::class))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('viewAny', Setting::class))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->allows('viewAny', User::class))->toBeTrue();

    // Manager permissions
    expect(Gate::forUser($this->manager)->allows('viewAny', Product::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->allows('view', $product))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('create', Product::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('update', $product))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('delete', $product))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('viewCost', $product))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('viewAny', Category::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('viewAny', Unit::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('viewAny', Setting::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->denies('viewAny', User::class))->toBeTrue();
});

test('super admin can access all management routes in filament panel', function () {
    $this->actingAs($this->superAdmin);

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/products/create')->assertOk();
    $this->get('/admin/categories')->assertOk();
    $this->get('/admin/units')->assertOk();
    $this->get('/admin/settings')->assertOk();
    $this->get('/admin/users')->assertOk();
});

test('manager can only view products list and is forbidden from management routes', function () {
    $this->actingAs($this->manager);

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/products/create')->assertForbidden();
    $this->get('/admin/categories')->assertForbidden();
    $this->get('/admin/units')->assertForbidden();
    $this->get('/admin/settings')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
});

test('filament product table renders last_cost only for super admin and hides it from manager', function () {
    // Super Admin sees last_cost column
    $this->actingAs($this->superAdmin);
    Livewire::test(ListProducts::class)
        ->assertTableColumnVisible('last_cost')
        ->assertTableColumnVisible('sale_price')
        ->assertTableColumnVisible('stock_qty');

    // Manager does NOT see last_cost column
    $this->actingAs($this->manager);
    Livewire::test(ListProducts::class)
        ->assertTableColumnHidden('last_cost')
        ->assertTableColumnVisible('sale_price')
        ->assertTableColumnVisible('stock_qty');
});
