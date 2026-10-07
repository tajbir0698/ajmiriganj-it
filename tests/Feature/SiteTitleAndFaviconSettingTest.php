<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Setting;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->superAdmin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'admin@ajmiriganj.test',
    ]);
    $this->superAdmin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->manager = User::factory()->create([
        'name' => 'Store Manager',
        'email' => 'manager@ajmiriganj.test',
    ]);
    $this->manager->assignRole(RoleName::MANAGER->value);
});

test('settings list displays site_title and favicon', function () {
    Setting::set('site_title', 'Ajmiriganj IT Hub', 'string');
    Setting::set('favicon', null, 'string');

    $this->actingAs($this->superAdmin);

    Livewire::test(ListSettings::class)
        ->assertSuccessful()
        ->assertSee('site_title')
        ->assertSee('favicon')
        ->assertSee('Ajmiriganj IT Hub')
        ->assertSee('Default Favicon');
});

test('super admin can update site title via edit setting form', function () {
    $setting = Setting::firstOrCreate(
        ['key' => 'site_title'],
        ['value' => 'Old Title', 'type' => 'string', 'description' => 'Site Title']
    );

    $this->actingAs($this->superAdmin);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm([
            'value' => 'Brand New IT Store',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('site_title'))->toBe('Brand New IT Store');
});

test('super admin can upload and save favicon via edit setting form', function () {
    Storage::fake('public');

    $setting = Setting::firstOrCreate(
        ['key' => 'favicon'],
        ['value' => null, 'type' => 'string', 'description' => 'Favicon Icon']
    );

    $this->actingAs($this->superAdmin);

    $file = UploadedFile::fake()->image('favicon.png', 32, 32);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm([
            'value' => $file,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $savedFavicon = Setting::get('favicon');
    expect($savedFavicon)->not->toBeNull();
    Storage::disk('public')->assertExists($savedFavicon);
});

test('filament panel dynamically evaluates brand title based on settings', function () {
    $provider = new AdminPanelProvider(app());
    $panel = $provider->panel(new Panel);

    Setting::set('site_title', 'Custom Tab Brand');
    expect($panel->getBrandName())->toBe('Custom Tab Brand');

    Setting::set('site_title', '');
    Setting::set('shop_name', 'Ajmiriganj Shop');
    expect($panel->getBrandName())->toBe('Ajmiriganj Shop');
});

test('filament panel dynamically evaluates favicon based on settings', function () {
    Storage::fake('public');

    $provider = new AdminPanelProvider(app());
    $panel = $provider->panel(new Panel);

    // When no favicon setting is set, default favicon asset is returned
    Setting::set('favicon', null);
    expect($panel->getFavicon())->toBe(asset('favicon.ico'));

    // When a custom uploaded favicon is set
    Setting::set('favicon', 'settings/custom-icon.png');
    expect($panel->getFavicon())->toBe(Storage::disk('public')->url('settings/custom-icon.png'));

    // When an external URL is used
    Setting::set('favicon', 'https://example.com/logo.ico');
    expect($panel->getFavicon())->toBe('https://example.com/logo.ico');
});

test('settings list displays custom favicon uploaded when favicon is set', function () {
    Setting::set('favicon', 'settings/my-logo.png', 'string');

    $this->actingAs($this->superAdmin);

    Livewire::test(ListSettings::class)
        ->assertSuccessful()
        ->assertSee('Custom Favicon Uploaded');
});

test('super admin can clear favicon via edit setting form', function () {
    $setting = Setting::firstOrCreate(
        ['key' => 'favicon'],
        ['value' => 'settings/old-favicon.png', 'type' => 'string']
    );

    $this->actingAs($this->superAdmin);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm([
            'value' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('favicon'))->toBeNull();
});

test('critical settings cannot be deleted from edit setting page', function () {
    $titleSetting = Setting::firstOrCreate(
        ['key' => 'site_title'],
        ['value' => 'Ajmiriganj IT', 'type' => 'string']
    );

    $this->actingAs($this->superAdmin);

    Livewire::test(EditSetting::class, ['record' => $titleSetting->id])
        ->assertActionHidden('delete');
});

test('non-super admins cannot access settings resource', function () {
    $this->actingAs($this->manager);

    Livewire::test(ListSettings::class)
        ->assertForbidden();
});

test('pos page renders configured site title and favicon', function () {
    Setting::set('site_title', 'Ajmiriganj Superstore');
    Setting::set('favicon', 'settings/store-icon.png');

    $this->actingAs($this->superAdmin);

    $response = $this->get('/pos');
    $response->assertSuccessful();
    $response->assertSee('Ajmiriganj Superstore - Point of Sale');
    $response->assertSee('/storage/settings/store-icon.png');
});
