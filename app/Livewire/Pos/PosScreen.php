<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldCart;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\CustomerAccountService;
use App\Services\SaleService;
use App\Support\Money;
use Exception;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class PosScreen extends Component
{
    // Search & Filter
    public string $search = '';

    public ?int $selectedCategoryId = null;

    // Preloaded safe product catalog (strictly safe fields: NO cost, purchase_price, cogs, or batches)
    public array $productsSafe = [];

    // Cart items for server-side state & backward compatibility with tests
    public array $cart = [];

    public string $overallDiscount = '0.00';

    public string $discountType = 'fixed'; // 'fixed' or 'percent'

    public string $discountInput = '0';

    public bool $autoPrint = true;

    // Payment details
    public string $paymentMethod = 'cash';

    public string $receivedAmount = '0.00';

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public string $note = '';

    // Quick add customer modal state
    public bool $showQuickAddCustomer = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerAddress = '';

    public string $newCustomerOpeningBalance = '0.00';

    // Held carts modal state
    public bool $showHeldCartsModal = false;

    // Success dialog
    public bool $showSuccessModal = false;

    public ?int $completedSaleId = null;

    public string $completedInvoiceNo = '';

    public string $completedTotal = '0.00';

    public string $completedChange = '0.00';

    // Error message banner
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->paymentMethod = PaymentMethod::CASH->value;
        $this->autoPrint = (bool) Setting::get('auto_print_receipt', true);
        $this->loadSafeProducts();
    }

    /**
     * Preload active products safely without exposing cost columns, regardless of user role.
     */
    public function loadSafeProducts(): void
    {
        $this->productsSafe = Product::query()
            ->safeForManager()
            ->with(['unit:id,name,short_name,allow_fractional', 'category:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) $product->sku,
                    'barcode' => (string) ($product->barcode ?? ''),
                    'category' => (string) ($product->category?->name ?? 'All Items'),
                    'category_id' => $product->category_id,
                    'unit' => (string) ($product->unit?->short_name ?? ''),
                    'allow_fractional' => (bool) ($product->unit?->allow_fractional ?? false),
                    'sale_price' => bcadd((string) $product->sale_price, '0.00', 2),
                    'stock_qty' => bcadd((string) $product->stock_qty, '0.000', 3),
                    'image' => $product->image ? asset('storage/'.$product->image) : null,
                ];
            })
            ->all();
    }

    /**
     * Get list of categories for filter chips.
     */
    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Check if credit sales setting is active (cached).
     */
    public function getCreditEnabledProperty(): bool
    {
        return (bool) Setting::get('credit_sales_enabled', false);
    }

    /**
     * Search customers for autocomplete dropdown.
     */
    public function getFilteredCustomersProperty()
    {
        if (empty($this->customerSearch)) {
            return Customer::where('is_active', true)->orderBy('name')->limit(10)->get();
        }

        return Customer::where('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->customerSearch}%")
                    ->orWhere('phone', 'like', "%{$this->customerSearch}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Get the currently selected customer with due amount.
     */
    public function getSelectedCustomerProperty(): ?Customer
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        return Customer::find($this->selectedCustomerId);
    }

    /**
     * Selected customer's live due amount.
     */
    public function getCustomerDueProperty(): string
    {
        if (! $this->selectedCustomer) {
            return '0.00';
        }

        return app(CustomerAccountService::class)->getCurrentDue($this->selectedCustomer);
    }

    /**
     * Get products collection for backward compatibility.
     */
    public function getProductsProperty()
    {
        return Product::query()
            ->safeForManager()
            ->with(['unit:id,name,short_name,allow_fractional', 'category:id,name'])
            ->where('is_active', true)
            ->when($this->selectedCategoryId, fn ($q) => $q->where('category_id', $this->selectedCategoryId))
            ->when(! empty($this->search), function ($q) {
                $term = trim($this->search);
                $q->where(function ($sq) use ($term) {
                    $sq->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit(48)
            ->get();
    }

    /**
     * Handle barcode scanner or exact match Enter key (for backwards compatibility).
     */
    public function searchEntered(): void
    {
        $term = trim($this->search);
        if (empty($term)) {
            return;
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->safeForManager()
            ->with(['unit:id,name,short_name,allow_fractional'])
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('barcode', $term)
                    ->orWhere('sku', $term);
            })
            ->first();

        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        }
    }

    /**
     * Add a product to the cart (server-side for tests / fallback).
     */
    public function addToCart(int $productId): void
    {
        $this->errorMessage = null;

        /** @var Product|null $product */
        $product = Product::query()
            ->safeForManager()
            ->with('unit')
            ->find($productId);

        if (! $product) {
            $this->errorMessage = 'Product not found.';

            return;
        }

        $stockQty = (string) $product->stock_qty;
        if (bccomp($stockQty, '0.000', 3) <= 0) {
            $this->errorMessage = "Cannot add {$product->name}: Out of stock.";

            return;
        }

        $existingIndex = null;
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $newQty = bcadd($this->cart[$existingIndex]['qty'], '1.000', 3);
            if (bccomp($newQty, $stockQty, 3) > 0) {
                $this->errorMessage = "Cannot exceed available stock of {$stockQty} for {$product->name}.";

                return;
            }
            $this->cart[$existingIndex]['qty'] = $newQty;
            $this->recalculateLine($existingIndex);
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => bcadd((string) $product->sale_price, '0.00', 2),
                'qty' => '1.000',
                'discount' => '0.00',
                'line_total' => bcadd((string) $product->sale_price, '0.00', 2),
                'allow_fractional' => (bool) ($product->unit?->allow_fractional ?? false),
                'unit_name' => $product->unit?->short_name ?? '',
                'max_stock' => $stockQty,
            ];
        }

        $this->recalculateTotals();
    }

    /**
     * Update quantity for a line item (for tests / server fallback).
     */
    public function updateQty(int $index, string $qty): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $this->errorMessage = null;
        $cleanQty = bcadd($qty, '0.000', 3);

        if (bccomp($cleanQty, '0.000', 3) <= 0) {
            $this->removeFromCart($index);

            return;
        }

        if (! $this->cart[$index]['allow_fractional'] && bccomp($cleanQty, bcadd($cleanQty, '0', 0), 3) !== 0) {
            $this->errorMessage = "{$this->cart[$index]['name']} does not allow fractional quantities.";
            $cleanQty = bcadd($cleanQty, '0.000', 0);
        }

        if (bccomp($cleanQty, $this->cart[$index]['max_stock'], 3) > 0) {
            $this->errorMessage = "Maximum available stock is {$this->cart[$index]['max_stock']} for {$this->cart[$index]['name']}.";
            $cleanQty = $this->cart[$index]['max_stock'];
        }

        $this->cart[$index]['qty'] = $cleanQty;
        $this->recalculateLine($index);
        $this->recalculateTotals();
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $step = '1.000';
        $newQty = bcadd($this->cart[$index]['qty'], $step, 3);
        $this->updateQty($index, $newQty);
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $step = '1.000';
        $newQty = bcsub($this->cart[$index]['qty'], $step, 3);
        $this->updateQty($index, $newQty);
    }

    public function updateLineDiscount(int $index, string $discount): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $lineGross = bcmul($this->cart[$index]['qty'], $this->cart[$index]['unit_price'], 2);
        $cleanDiscount = bcadd($discount, '0.00', 2);

        if (bccomp($cleanDiscount, '0.00', 2) < 0) {
            $cleanDiscount = '0.00';
        } elseif (bccomp($cleanDiscount, $lineGross, 2) > 0) {
            $cleanDiscount = $lineGross;
        }

        $this->cart[$index]['discount'] = $cleanDiscount;
        $this->recalculateLine($index);
        $this->recalculateTotals();
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->recalculateTotals();
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->overallDiscount = '0.00';
        $this->discountInput = '0';
        $this->receivedAmount = '0.00';
        $this->errorMessage = null;
        $this->recalculateTotals();
    }

    protected function recalculateLine(int $index): void
    {
        $item = &$this->cart[$index];
        $gross = bcmul($item['qty'], $item['unit_price'], 2);
        $item['line_total'] = bcsub($gross, $item['discount'], 2);
    }

    public function recalculateTotals(): void
    {
        $subtotal = '0.00';
        foreach ($this->cart as $item) {
            $subtotal = bcadd($subtotal, $item['line_total'], 2);
        }

        $overall = '0.00';
        if ($this->discountType === 'percent') {
            $percent = (float) $this->discountInput;
            if ($percent > 0) {
                $pctVal = min($percent, 100);
                $overall = bcmul($subtotal, (string) ($pctVal / 100), 2);
            }
        } else {
            $overall = bcadd($this->discountInput ?: '0', '0.00', 2);
        }

        if (bccomp($overall, '0.00', 2) < 0) {
            $overall = '0.00';
        } elseif (bccomp($overall, $subtotal, 2) > 0) {
            $overall = $subtotal;
        }

        $this->overallDiscount = $overall;

        $netTotal = bcsub($subtotal, $this->overallDiscount, 2);
        if (bccomp($this->receivedAmount, '0.00', 2) === 0 || bccomp($this->receivedAmount, $netTotal, 2) < 0) {
            $this->receivedAmount = $netTotal;
        }
    }

    public function updatedDiscountInput(): void
    {
        $this->recalculateTotals();
    }

    public function updatedDiscountType(): void
    {
        $this->recalculateTotals();
    }

    public function getSubtotalProperty(): string
    {
        $subtotal = '0.00';
        foreach ($this->cart as $item) {
            $subtotal = bcadd($subtotal, $item['line_total'], 2);
        }

        return $subtotal;
    }

    public function getTotalProperty(): string
    {
        $total = bcsub($this->subtotal, $this->overallDiscount, 2);

        return bccomp($total, '0.00', 2) < 0 ? '0.00' : $total;
    }

    public function getChangeAmountProperty(): string
    {
        $cleanReceived = bcadd($this->receivedAmount ?: '0', '0.00', 2);
        $total = $this->total;

        if (bccomp($cleanReceived, $total, 2) > 0) {
            return bcsub($cleanReceived, $total, 2);
        }

        return '0.00';
    }

    public function getDueAmountProperty(): string
    {
        if (! $this->creditEnabled) {
            return '0.00';
        }

        $cleanReceived = bcadd($this->receivedAmount ?: '0', '0.00', 2);
        $total = $this->total;

        if (bccomp($cleanReceived, $total, 2) < 0) {
            return bcsub($total, $cleanReceived, 2);
        }

        return '0.00';
    }

    public function setReceivedExact(): void
    {
        $this->receivedAmount = $this->total;
    }

    public function addReceived(int $amount): void
    {
        $current = bcadd($this->receivedAmount ?: '0', '0.00', 2);
        $this->receivedAmount = bcadd($current, (string) $amount, 2);
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->customerSearch = '';
    }

    public function removeCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
    }

    public function createCustomer(): void
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:255',
            'newCustomerPhone' => 'nullable|string|max:50',
            'newCustomerAddress' => 'nullable|string',
            'newCustomerOpeningBalance' => 'nullable|numeric',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone ?: null,
            'address' => $this->newCustomerAddress ?: null,
            'opening_balance' => bcadd($this->newCustomerOpeningBalance ?: '0', '0.00', 2),
            'is_active' => true,
        ]);

        $this->selectedCustomerId = $customer->id;
        $this->showQuickAddCustomer = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerAddress = '';
        $this->newCustomerOpeningBalance = '0.00';
    }

    /**
     * Hold cart (accepts client payload from Alpine or uses server cart).
     */
    public function holdCart(?array $clientPayload = null): array
    {
        $this->errorMessage = null;

        $items = $clientPayload['items'] ?? $this->cart;

        if (empty($items)) {
            $this->errorMessage = 'Cannot hold an empty cart.';

            return ['success' => false, 'error' => $this->errorMessage];
        }

        $customerId = $clientPayload['customer_id'] ?? $this->selectedCustomerId;
        $discount = $clientPayload['discount'] ?? $this->overallDiscount;
        $discountInput = $clientPayload['discount_input'] ?? $this->discountInput;
        $discountType = $clientPayload['discount_type'] ?? $this->discountType;
        $note = $clientPayload['note'] ?? $this->note;

        $heldCart = HeldCart::create([
            'user_id' => auth()->id(),
            'name' => 'Cart '.now()->format('h:i A').' ('.count($items).' items)',
            'cart' => [
                'items' => $items,
                'customer_id' => $customerId,
                'discount' => $discount,
                'discount_input' => $discountInput,
                'discount_type' => $discountType,
                'note' => $note,
            ],
        ]);

        $this->clearCart();
        $this->selectedCustomerId = null;
        $this->note = '';

        return ['success' => true, 'held_cart_id' => $heldCart->id];
    }

    public function getHeldCartsProperty()
    {
        return HeldCart::where('user_id', auth()->id())->latest()->get();
    }

    /**
     * Resume a held cart with fresh database validation (stock and current prices).
     */
    public function resumeCart(int $heldCartId): array
    {
        $this->errorMessage = null;

        /** @var HeldCart|null $held */
        $held = HeldCart::where('id', $heldCartId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $held) {
            $this->errorMessage = 'Held cart not found.';

            return ['success' => false, 'error' => $this->errorMessage];
        }

        $payload = $held->cart;
        $savedItems = $payload['items'] ?? [];

        // Single bounded query for all products in the held cart
        $productIds = array_column($savedItems, 'product_id');
        $products = Product::whereIn('id', $productIds)->with('unit')->get()->keyBy('id');

        $freshCart = [];
        foreach ($savedItems as $item) {
            $pid = (int) $item['product_id'];
            /** @var Product|null $product */
            $product = $products->get($pid);

            if (! $product || ! $product->is_active) {
                continue; // Product deleted or deactivated
            }

            $currentStock = (string) $product->stock_qty;
            if (bccomp($currentStock, '0.000', 3) <= 0) {
                continue; // Item now out of stock
            }

            $qty = min($item['qty'], $currentStock);
            $freshPrice = bcadd((string) $product->sale_price, '0.00', 2);
            $gross = bcmul((string) $qty, $freshPrice, 2);
            $discount = min($item['discount'] ?? '0.00', $gross);
            $lineTotal = bcsub($gross, $discount, 2);

            $freshCart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => (string) ($product->barcode ?? ''),
                'unit_price' => $freshPrice,
                'qty' => bcadd((string) $qty, '0.000', 3),
                'discount' => bcadd((string) $discount, '0.00', 2),
                'line_total' => $lineTotal,
                'allow_fractional' => (bool) ($product->unit?->allow_fractional ?? false),
                'unit_name' => $product->unit?->short_name ?? '',
                'max_stock' => $currentStock,
            ];
        }

        $this->cart = $freshCart;
        $this->selectedCustomerId = $payload['customer_id'] ?? null;
        $this->discountInput = $payload['discount_input'] ?? '0';
        $this->discountType = $payload['discount_type'] ?? 'fixed';
        $this->note = $payload['note'] ?? '';

        $held->delete();
        $this->showHeldCartsModal = false;
        $this->recalculateTotals();

        // Dispatch browser event to synchronize Alpine local cart
        $this->dispatch('held-cart-resumed', cartData: [
            'items' => $freshCart,
            'customer_id' => $this->selectedCustomerId,
            'discount_input' => $this->discountInput,
            'discount_type' => $this->discountType,
            'note' => $this->note,
        ]);

        return [
            'success' => true,
            'items' => $freshCart,
            'customer_id' => $this->selectedCustomerId,
            'discount_input' => $this->discountInput,
            'discount_type' => $this->discountType,
            'note' => $this->note,
        ];
    }

    public function deleteHeldCart(int $heldCartId): void
    {
        HeldCart::where('id', $heldCartId)
            ->where('user_id', auth()->id())
            ->delete();
    }

    /**
     * Checkout & Complete Sale.
     * Server is final authority: re-reads prices & stock, verifies bounds, enforces idempotency.
     */
    public function completeSale(?array $clientPayload = null, ?SaleService $saleService = null): array
    {
        $this->errorMessage = null;
        $saleService = $saleService ?? app(SaleService::class);

        // Accept payload from client Alpine or fallback to server state
        $items = $clientPayload['items'] ?? $this->cart;
        $customerId = $clientPayload['customer_id'] ?? $this->selectedCustomerId;
        $overallDiscountInput = $clientPayload['overall_discount'] ?? $this->overallDiscount;
        $receivedAmountInput = $clientPayload['received_amount'] ?? $this->receivedAmount;
        $paymentMethod = $clientPayload['payment_method'] ?? $this->paymentMethod;
        $idempotencyKey = $clientPayload['idempotency_key'] ?? (string) Str::uuid();
        $note = $clientPayload['note'] ?? ($this->note ?: null);

        if (empty($items)) {
            $this->errorMessage = 'Cart is empty. Please add products to complete a sale.';

            return ['success' => false, 'error' => $this->errorMessage, 'code' => 'cart_empty'];
        }

        // Check idempotency first: if already processed, return existing sale safely without duplicate deduction!
        if ($idempotencyKey) {
            $existingSale = Sale::where('idempotency_key', $idempotencyKey)->first();
            if ($existingSale) {
                $this->completedSaleId = $existingSale->id;
                $this->completedInvoiceNo = $existingSale->invoice_no;
                $this->completedTotal = Money::format((string) $existingSale->total);
                $this->completedChange = Money::format((string) $existingSale->change_amount);
                $this->showSuccessModal = true;
                $this->clearCart();

                return [
                    'success' => true,
                    'sale_id' => $existingSale->id,
                    'invoice_no' => $existingSale->invoice_no,
                    'total' => $this->completedTotal,
                    'change_amount' => $this->completedChange,
                ];
            }
        }

        // Bounded single query for products (query count guard)
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)
            ->with(['unit:id,name,short_name,allow_fractional'])
            ->get()
            ->keyBy('id');

        $validatedItems = [];
        $serverSubtotal = '0.00';

        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $reqQty = bcadd((string) ($item['qty'] ?? '0'), '0.000', 3);
            $clientDisc = bcadd((string) ($item['discount'] ?? '0'), '0.00', 2);
            $clientPrice = isset($item['unit_price']) ? bcadd((string) $item['unit_price'], '0.00', 2) : null;

            /** @var Product|null $product */
            $product = $products->get($pid);

            if (! $product || ! $product->is_active) {
                $pName = $item['name'] ?? "Product #{$pid}";
                $this->errorMessage = "Product '{$pName}' is no longer available.";

                return ['success' => false, 'error' => $this->errorMessage, 'code' => 'product_unavailable'];
            }

            $currentPrice = bcadd((string) $product->sale_price, '0.00', 2);

            // Server authority: verify price has not changed
            if ($clientPrice !== null && bccomp($clientPrice, $currentPrice, 2) !== 0) {
                $this->errorMessage = "Price changed for '{$product->name}' (was ৳{$clientPrice}, now ৳{$currentPrice}). Please review your cart.";

                return [
                    'success' => false,
                    'error' => $this->errorMessage,
                    'code' => 'price_changed',
                    'product_id' => $product->id,
                    'new_price' => $currentPrice,
                ];
            }

            // Server authority: verify stock has not been exhausted
            $currentStock = (string) $product->stock_qty;
            if (bccomp($reqQty, $currentStock, 3) > 0) {
                $this->errorMessage = "Insufficient stock for '{$product->name}'. Available: ".Money::formatQty($currentStock).', Requested: '.Money::formatQty($reqQty).'.';

                return [
                    'success' => false,
                    'error' => $this->errorMessage,
                    'code' => 'insufficient_stock',
                    'product_id' => $product->id,
                    'available_stock' => $currentStock,
                ];
            }

            // Re-calculate with server price
            $lineGross = bcmul($reqQty, $currentPrice, 2);
            $cleanLineDiscount = bccomp($clientDisc, $lineGross, 2) > 0 ? $lineGross : $clientDisc;
            $lineTotal = bcsub($lineGross, $cleanLineDiscount, 2);
            $serverSubtotal = bcadd($serverSubtotal, $lineTotal, 2);

            $validatedItems[] = [
                'product_id' => $product->id,
                'qty' => $reqQty,
                'discount' => $cleanLineDiscount,
            ];
        }

        // Overall discount capping
        $overallDiscount = bcadd((string) $overallDiscountInput, '0.00', 2);
        if (bccomp($overallDiscount, $serverSubtotal, 2) > 0) {
            $overallDiscount = $serverSubtotal;
        }

        $serverTotal = bcsub($serverSubtotal, $overallDiscount, 2);
        if (bccomp($serverTotal, '0.00', 2) < 0) {
            $serverTotal = '0.00';
        }

        $cleanReceived = bcadd((string) $receivedAmountInput, '0.00', 2);
        $creditEnabled = $this->creditEnabled;

        // Payment validation
        if (! $creditEnabled) {
            if (bccomp($cleanReceived, $serverTotal, 2) < 0) {
                $this->errorMessage = "Received amount ({$cleanReceived}) cannot be less than total ({$serverTotal}).";

                return ['success' => false, 'error' => $this->errorMessage, 'code' => 'insufficient_payment'];
            }
            $paidAmount = $serverTotal;
        } else {
            if (bccomp($cleanReceived, $serverTotal, 2) < 0 && ! $customerId) {
                $this->errorMessage = 'A registered customer is required when the sale is not fully paid.';

                return ['success' => false, 'error' => $this->errorMessage, 'code' => 'customer_required'];
            }
            $paidAmount = min($cleanReceived, $serverTotal);
        }

        try {
            /** @var Sale $sale */
            $sale = $saleService->createSale([
                'customer_id' => $customerId,
                'items' => $validatedItems,
                'discount' => $overallDiscount,
                'paid_amount' => $paidAmount,
                'received_amount' => $cleanReceived,
                'payment_method' => $paymentMethod,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
            ], auth()->user());

            // Prepare success state
            $this->completedSaleId = $sale->id;
            $this->completedInvoiceNo = $sale->invoice_no;
            $this->completedTotal = Money::format((string) $sale->total);
            $this->completedChange = Money::format((string) $sale->change_amount);
            $this->showSuccessModal = true;

            $this->clearCart();
            $this->selectedCustomerId = null;
            $this->note = '';

            // Auto-print receipt if setting enabled
            if ((bool) Setting::get('auto_print_receipt', true)) {
                $this->dispatch('print-receipt', saleId: $sale->id);
            }

            return [
                'success' => true,
                'sale_id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'total' => $this->completedTotal,
                'change_amount' => $this->completedChange,
            ];
        } catch (InsufficientStockException $e) {
            $shortages = $e->getShortages();
            $shortItems = [];
            foreach ($shortages as $s) {
                $shortItems[] = "{$s['product']->name} (Req: {$s['requested']}, Avail: {$s['available']})";
            }
            $this->errorMessage = 'Insufficient stock for: '.implode(', ', $shortItems);

            return ['success' => false, 'error' => $this->errorMessage, 'code' => 'insufficient_stock'];
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return ['success' => false, 'error' => $this->errorMessage, 'code' => 'exception'];
        }
    }

    public function newSale(): void
    {
        $this->showSuccessModal = false;
        $this->completedSaleId = null;
        $this->clearCart();
    }

    public function render(): View
    {
        return view('livewire.pos.pos-screen');
    }
}
