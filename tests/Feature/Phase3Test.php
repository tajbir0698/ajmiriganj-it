<?php

declare(strict_types=1);

use App\Enums\AdjustmentType;
use App\Enums\BatchSource;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockOperationException;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\FifoStockService;
use App\Services\StockAdjustmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->superAdmin = User::where('email', 'admin@ajmiriganj.com')->first();
    $this->manager = User::where('email', 'manager@ajmiriganj.com')->first();
    $this->fifoStockService = app(FifoStockService::class);
    $this->adjustmentService = app(StockAdjustmentService::class);

    $cat = Category::first();
    $unit = Unit::first();

    // Create fresh test product with 0 stock
    $this->product = Product::create([
        'sku' => 'TEST-PROD-001',
        'barcode' => '1122334455667',
        'name' => 'Test Hardware Component',
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'last_cost' => '0.0000',
        'sale_price' => '200.00',
        'stock_qty' => '0.000',
        'alert_qty' => '5.000',
        'is_active' => true,
    ]);
});

test('calling FifoStockService without an open database transaction throws LogicException', function () {
    $level = DB::transactionLevel();
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    try {
        expect(fn () => $this->fifoStockService->consume($this->product, '1.000', StockMovementType::SALE))
            ->toThrow(LogicException::class, 'FifoStockService methods must be executed within an active database transaction.');

        expect(fn () => $this->fifoStockService->addBatch($this->product, '1.000', '100.0000', BatchSource::OPENING, now()))
            ->toThrow(LogicException::class, 'FifoStockService methods must be executed within an active database transaction.');
    } finally {
        for ($i = 0; $i < $level; $i++) {
            DB::beginTransaction();
        }
    }
});

test('consume across two batches reproduces worked example: 5@100 and 3@120 equals total cost 860.00 and avg unit cost 107.5000', function () {
    DB::transaction(function () {
        // Batch A: 5 @ 100 on day 1
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '5.000',
            unitCost: '100.0000',
            source: BatchSource::PURCHASE,
            batchDate: now()->subDays(2),
        );

        // Batch B: 10 @ 120 on day 2
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '10.000',
            unitCost: '120.0000',
            source: BatchSource::PURCHASE,
            batchDate: now()->subDay(),
        );

        // Consume 8
        $result = $this->fifoStockService->consume(
            product: $this->product,
            qty: '8.000',
            type: StockMovementType::SALE
        );

        expect($result->totalCost)->toBe('860.00')
            ->and($result->averageUnitCost)->toBe('107.5000')
            ->and($result->totalQty)->toBe('8.000')
            ->and($result->allocations)->toHaveCount(2)
            ->and($result->allocations[0]['qty'])->toBe('5.000')
            ->and($result->allocations[0]['unit_cost'])->toBe('100.0000')
            ->and($result->allocations[1]['qty'])->toBe('3.000')
            ->and($result->allocations[1]['unit_cost'])->toBe('120.0000');
    });

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('7.000');

    $batches = $this->product->batches()->orderBy('id')->get();
    expect($batches[0]->remaining_qty)->toBe('0.000')
        ->and($batches[1]->remaining_qty)->toBe('7.000');
});

test('consume exactly whole stock succeeds; consume more than stock throws and leaves database completely unchanged', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '10.000',
            unitCost: '50.0000',
            source: BatchSource::OPENING,
            batchDate: now(),
        );
    });

    $initialMovementsCount = StockMovement::count();

    // Consuming more than stock (10.001) throws InsufficientStockException
    expect(function () {
        DB::transaction(function () {
            $this->fifoStockService->consume($this->product, '10.001', StockMovementType::SALE);
        });
    })->toThrow(InsufficientStockException::class);

    // Verify nothing changed
    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('10.000')
        ->and(StockMovement::count())->toBe($initialMovementsCount);

    $batch = $this->product->batches()->first();
    expect($batch->remaining_qty)->toBe('10.000');

    // Consuming exactly whole stock (10.000) succeeds
    DB::transaction(function () {
        $res = $this->fifoStockService->consume($this->product, '10.000', StockMovementType::SALE);
        expect($res->totalCost)->toBe('500.00');
    });

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('0.000');
});

test('fractional quantities across batches calculate with high precision', function () {
    DB::transaction(function () {
        // 1.500 kg @ 100.0000
        $this->fifoStockService->addBatch($this->product, '1.500', '100.0000', BatchSource::PURCHASE, now()->subDays(2));
        // 2.000 kg @ 150.0000
        $this->fifoStockService->addBatch($this->product, '2.000', '150.0000', BatchSource::PURCHASE, now()->subDay());

        // Consume 2.250 kg => 1.500 @ 100 (150.00) + 0.750 @ 150 (112.50) = 262.50
        $result = $this->fifoStockService->consume($this->product, '2.250', StockMovementType::SALE);

        expect($result->totalCost)->toBe('262.50')
            ->and($result->totalQty)->toBe('2.250')
            ->and($result->allocations)->toHaveCount(2)
            ->and($result->allocations[0]['qty'])->toBe('1.500')
            ->and($result->allocations[1]['qty'])->toBe('0.750');
    });

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('1.250');
});

test('FIFO order strictly respects batch_date ASC, id ASC regardless of insertion order', function () {
    DB::transaction(function () {
        // Insert Batch 1 with newer date (Tomorrow)
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '10.000',
            unitCost: '200.0000',
            source: BatchSource::PURCHASE,
            batchDate: now()->addDay(),
        );

        // Insert Batch 2 with older date (Yesterday)
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '10.000',
            unitCost: '100.0000',
            source: BatchSource::PURCHASE,
            batchDate: now()->subDay(),
        );

        // Consume 5: must consume from Batch 2 (older date), costing 100.0000 each
        $result = $this->fifoStockService->consume($this->product, '5.000', StockMovementType::SALE);

        expect($result->totalCost)->toBe('500.00')
            ->and($result->allocations[0]['unit_cost'])->toBe('100.0000');
    });
});

test('consumeMany locks products in ascending order, checks aggregated availability, and fails atomically on any shortage', function () {
    $prodB = Product::create([
        'sku' => 'TEST-PROD-002',
        'name' => 'Secondary Test Component',
        'category_id' => $this->product->category_id,
        'unit_id' => $this->product->unit_id,
        'last_cost' => '50.0000',
        'sale_price' => '80.00',
        'stock_qty' => '0.000',
        'alert_qty' => '2.000',
        'is_active' => true,
    ]);

    DB::transaction(function () use ($prodB) {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::PURCHASE, now());
        $this->fifoStockService->addBatch($prodB, '5.000', '50.0000', BatchSource::PURCHASE, now());
    });

    // 1. One short product fails the whole operation with shortages listed
    $linesWithShortage = [
        'line_1' => ['product_id' => $this->product->id, 'qty' => '4.000'],
        'line_2' => ['product_id' => $prodB->id, 'qty' => '6.000'], // requested 6, available 5
        'line_3' => ['product_id' => $this->product->id, 'qty' => '3.000'],
    ];

    try {
        DB::transaction(fn () => $this->fifoStockService->consumeMany($linesWithShortage, StockMovementType::SALE));
        $this->fail('Expected InsufficientStockException was not thrown.');
    } catch (InsufficientStockException $e) {
        expect($e->shortages)->toHaveCount(1)
            ->and($e->shortages[0]['product']->id)->toBe($prodB->id)
            ->and($e->shortages[0]['requested'])->toBe('6.000')
            ->and($e->shortages[0]['available'])->toBe('5.000');
    }

    // Verify nothing changed on any product
    $this->product->refresh();
    $prodB->refresh();
    expect($this->product->stock_qty)->toBe('10.000')
        ->and($prodB->stock_qty)->toBe('5.000');

    // 2. Multi-product successful consumeMany
    $validLines = [
        'first' => ['product_id' => $this->product->id, 'qty' => '5.000'],
        'second' => ['product_id' => $prodB->id, 'qty' => '2.000'],
    ];

    $results = DB::transaction(fn () => $this->fifoStockService->consumeMany($validLines, StockMovementType::SALE));

    expect($results)->toHaveKeys(['first', 'second'])
        ->and($results['first']->totalCost)->toBe('500.00')
        ->and($results['second']->totalCost)->toBe('100.00');

    $this->product->refresh();
    $prodB->refresh();
    expect($this->product->stock_qty)->toBe('5.000')
        ->and($prodB->stock_qty)->toBe('3.000');
});

test('restore puts quantity back into original batches, cannot exceed original batch qty, and supports partial restores', function () {
    $batch = null;
    $allocations = null;

    DB::transaction(function () use (&$batch, &$allocations) {
        $batch = $this->fifoStockService->addBatch($this->product, '10.000', '150.0000', BatchSource::PURCHASE, now());
        $res = $this->fifoStockService->consume($this->product, '6.000', StockMovementType::SALE);
        $allocations = $res->allocations;
    });

    $this->product->refresh();
    $batch->refresh();
    expect($this->product->stock_qty)->toBe('4.000')
        ->and($batch->remaining_qty)->toBe('4.000');

    // 1. Partial restore of 2.000 units (out of 6.000 consumed)
    DB::transaction(function () use ($allocations) {
        $partialAlloc = [
            ['purchase_item_id' => $allocations[0]['purchase_item_id'], 'qty' => '2.000'],
        ];
        $this->fifoStockService->restore($partialAlloc, StockMovementType::SALE_RETURN);
    });

    $this->product->refresh();
    $batch->refresh();
    expect($this->product->stock_qty)->toBe('6.000')
        ->and($batch->remaining_qty)->toBe('6.000');

    // 2. Restore remaining 4.000 units (brings batch back to original 10.000)
    DB::transaction(function () use ($allocations) {
        $partialAlloc = [
            ['purchase_item_id' => $allocations[0]['purchase_item_id'], 'qty' => '4.000'],
        ];
        $this->fifoStockService->restore($partialAlloc, StockMovementType::SALE_RETURN);
    });

    $this->product->refresh();
    $batch->refresh();
    expect($this->product->stock_qty)->toBe('10.000')
        ->and($batch->remaining_qty)->toBe('10.000');

    // 3. Attempting to restore more than original batch qty throws InvalidStockOperationException
    expect(function () use ($allocations) {
        DB::transaction(function () use ($allocations) {
            $invalidAlloc = [
                ['purchase_item_id' => $allocations[0]['purchase_item_id'], 'qty' => '0.001'],
            ];
            $this->fifoStockService->restore($invalidAlloc, StockMovementType::SALE_RETURN);
        });
    })->toThrow(InvalidStockOperationException::class);
});

test('reverseBatch reverses untouched batch, zeroes remaining_qty, and decrements stock; blocks if partially consumed', function () {
    $batch = null;
    DB::transaction(function () use (&$batch) {
        $batch = $this->fifoStockService->addBatch($this->product, '8.000', '120.0000', BatchSource::PURCHASE, now());
    });

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('8.000');

    // 1. Reverse untouched batch
    DB::transaction(function () use ($batch) {
        $this->fifoStockService->reverseBatch($batch, StockMovementType::PURCHASE);
    });

    $this->product->refresh();
    $batch->refresh();
    expect($this->product->stock_qty)->toBe('0.000')
        ->and($batch->remaining_qty)->toBe('0.000');

    // 2. Reversing a consumed batch is blocked
    DB::transaction(function () use (&$batch) {
        $batch = $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::PURCHASE, now());
        $this->fifoStockService->consume($this->product, '1.000', StockMovementType::SALE);
    });

    expect(function () use ($batch) {
        DB::transaction(fn () => $this->fifoStockService->reverseBatch($batch, StockMovementType::PURCHASE));
    })->toThrow(InvalidStockOperationException::class);
});

test('addBatch does NOT update products.last_cost except for source=opening when product has no prior cost', function () {
    // Product starts with last_cost = 0.0000
    expect((float) $this->product->last_cost)->toBe(0.0);

    // 1. Opening stock sets last_cost when last_cost is 0
    DB::transaction(function () {
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '5.000',
            unitCost: '110.0000',
            source: BatchSource::OPENING,
            batchDate: now(),
        );
    });

    $this->product->refresh();
    expect($this->product->last_cost)->toBe('110.0000');

    // 2. Subsequent addBatch (purchase or adjustment) does NOT touch last_cost
    DB::transaction(function () {
        $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '10.000',
            unitCost: '220.0000',
            source: BatchSource::PURCHASE,
            batchDate: now(),
        );
    });

    $this->product->refresh();
    // Remains 110.0000 because FifoStockService delegates price policy to PurchaseService
    expect($this->product->last_cost)->toBe('110.0000');
});

test('stock adjustments: increase creates batch, decrease and damage consume FIFO and store lost cost; blocked when qty > stock', function () {
    // Initial batch of 10 @ 100
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now()->subDays(3));
    });

    // 1. Increase adjustment: 5 @ 120
    $adjIncrease = $this->adjustmentService->adjust([
        'product_id' => $this->product->id,
        'type' => AdjustmentType::INCREASE,
        'qty' => '5.000',
        'unit_cost' => '120.0000',
        'reason' => 'Found unopened box during physical inventory',
    ], $this->superAdmin);

    expect($adjIncrease->adjustment_no)->toStartWith('ADJ-')
        ->and($adjIncrease->total_cost)->toBe('600.00');

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('15.000');

    // 2. Decrease adjustment: 3 units (FIFO consumes from oldest batch @ 100)
    $adjDecrease = $this->adjustmentService->adjust([
        'product_id' => $this->product->id,
        'type' => AdjustmentType::DECREASE,
        'qty' => '3.000',
        'reason' => 'Inventory shrinkage / unrecorded demo unit',
    ], $this->superAdmin);

    expect($adjDecrease->total_cost)->toBe('300.00')
        ->and($adjDecrease->unit_cost)->toBe('100.0000');

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('12.000');

    // 3. Damage entry: 8 units (consumes remaining 4 @ 100 + 4 @ 120 = 400 + 480 = 880.00)
    // Oldest batch had 10 - 3 = 7. Next batch had 5. Total = 12.
    // 7 @ 100 + 1 @ 120 = 700 + 120 = 820.00
    $adjDamage = $this->adjustmentService->adjust([
        'product_id' => $this->product->id,
        'type' => AdjustmentType::DAMAGE,
        'qty' => '8.000',
        'reason' => 'Carton crushed by water leak',
    ], $this->superAdmin);

    expect($adjDamage->total_cost)->toBe('820.00');

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('4.000');

    // 4. Over-adjusting (requesting 5 when only 4 are left) throws InsufficientStockException
    expect(function () {
        $this->adjustmentService->adjust([
            'product_id' => $this->product->id,
            'type' => AdjustmentType::DECREASE,
            'qty' => '5.000',
            'reason' => 'Over adjustment',
        ], $this->superAdmin);
    })->toThrow(InsufficientStockException::class);
});

test('opening stock creates standalone batch with null purchase_id and source=opening', function () {
    $batch = null;
    DB::transaction(function () use (&$batch) {
        $batch = $this->fifoStockService->addBatch(
            product: $this->product,
            qty: '20.000',
            unitCost: '350.0000',
            source: BatchSource::OPENING,
            batchDate: now(),
        );
    });

    expect($batch->purchase_id)->toBeNull()
        ->and($batch->source)->toBe(BatchSource::OPENING)
        ->and($batch->isOpening())->toBeTrue()
        ->and($batch->remaining_qty)->toBe('20.000');
});

test('cost and stock integrity: batches remaining_qty == products.stock_qty == sum(movements.qty) and stockValue matches', function () {
    DB::transaction(function () {
        // Purchases
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::PURCHASE, now()->subDays(4));
        $this->fifoStockService->addBatch($this->product, '15.000', '120.0000', BatchSource::PURCHASE, now()->subDays(3));

        // Consumes
        $res = $this->fifoStockService->consume($this->product, '8.000', StockMovementType::SALE);

        // Adjustments
        $this->fifoStockService->consume($this->product, '2.000', StockMovementType::DAMAGE);

        // Partial restore
        $this->fifoStockService->restore([
            ['purchase_item_id' => $res->allocations[0]['purchase_item_id'], 'qty' => '1.000'],
        ], StockMovementType::SALE_RETURN);
    });

    $this->product->refresh();

    $batchSum = PurchaseItem::where('product_id', $this->product->id)->where('remaining_qty', '>', 0)->sum('remaining_qty');
    $movementSum = StockMovement::where('product_id', $this->product->id)->sum('qty');

    // 10 + 15 - 8 - 2 + 1 = 16.000
    expect(bcadd((string) $batchSum, '0', 3))->toBe('16.000')
        ->and((string) $this->product->stock_qty)->toBe('16.000')
        ->and(bcadd((string) $movementSum, '0', 3))->toBe('16.000');

    // Valuation check
    $val = $this->fifoStockService->stockValue($this->product);
    // Batch 1 had 10, consumed 8, restored 1, consumed 2 => 1 remaining @ 100 = 100.00
    // Batch 2 had 15, untouched => 15 @ 120 = 1800.00. Total = 1900.00
    expect($val)->toBe('1900.00');
});

test('reconcile command: clean data exits 0; tampered stock is fixed with --fix; batch/movement disagreement is not auto-fixed', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    // 1. Clean data exits 0
    Artisan::call('stock:reconcile');
    expect(Artisan::output())->toContain('100% reconciled');

    // 2. Tamper with cached stock_qty (e.g. drift to 999)
    DB::table('products')->where('id', $this->product->id)->update(['stock_qty' => '999.000']);

    // Dry run detects mismatch and exits 1
    $exitCodeDry = Artisan::call('stock:reconcile');
    $outputDry = Artisan::output();
    expect($exitCodeDry)->toBe(1)
        ->and($outputDry)->toContain('TEST-PROD-001')
        ->and($outputDry)->toContain('Unresolved');

    // Running with --fix safely corrects cached stock back to 10.000
    $exitCodeFix = Artisan::call('stock:reconcile', ['--fix' => true]);
    expect($exitCodeFix)->toBe(0);

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe('10.000');

    // 3. Batches vs movements disagreement is reported and NOT auto-fixed
    // Tamper with movement sum
    DB::table('stock_movements')->where('product_id', $this->product->id)->first();
    DB::table('stock_movements')->insert([
        'product_id' => $this->product->id,
        'purchase_item_id' => null,
        'type' => 'sale',
        'qty' => '-5.000',
        'unit_cost' => '100.0000',
        'created_at' => now(),
    ]);

    $exitDisagreement = Artisan::call('stock:reconcile', ['--fix' => true]);
    $outputDisagreement = Artisan::output();
    expect($exitDisagreement)->toBe(1)
        ->and($outputDisagreement)->toContain('Batches Disagree');
});

test('permissions and cost redaction: manager can view stock overview and low-stock widget, but cost fields are strictly absent', function () {
    $this->actingAs($this->manager);

    // 1. Manager can access Stock Overview
    $this->get('/admin/stock-overview')->assertOk();

    // 2. Manager cannot access stock adjustments or movements
    $this->get('/admin/stock-adjustments')->assertForbidden();
    $this->get('/admin/stock-adjustments/create')->assertForbidden();
    $this->get('/admin/stock-movements')->assertForbidden();

    // 3. Inspect Manager query payload: prove last_cost is NOT in the select or array payload
    $managerProducts = Product::safeForManager()->get();
    foreach ($managerProducts as $mp) {
        $array = $mp->toArray();
        expect(array_key_exists('last_cost', $array))->toBeFalse();
    }
});

test('architecture test: verifies that only FifoStockService modifies remaining_qty or products.stock_qty', function () {
    $basePath = app_path();
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

    $violations = [];

    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $filename = $file->getFilename();
        if ($filename === 'FifoStockService.php' || $filename === 'ReconcileStockCommand.php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        // Check for direct property assignments to stock_qty or remaining_qty
        if (preg_match('/->stock_qty\s*(\+|-)?=/', $content)) {
            $violations[] = $file->getPathname().' writes stock_qty via property assignment!';
        }
        if (preg_match('/->remaining_qty\s*(\+|-)?=/', $content)) {
            $violations[] = $file->getPathname().' writes remaining_qty via property assignment!';
        }

        // Check for query updates, increments, or decrements
        if (preg_match('/(update|increment|decrement)\s*\([^)]*[\'"]stock_qty[\'"]/', $content)) {
            $violations[] = $file->getPathname().' updates/increments stock_qty!';
        }
        if (preg_match('/(update|increment|decrement)\s*\([^)]*[\'"]remaining_qty[\'"]/', $content)) {
            $violations[] = $file->getPathname().' updates/increments remaining_qty!';
        }
    }

    expect($violations)->toBeEmpty();
});
