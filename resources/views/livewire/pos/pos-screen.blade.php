<div 
    id="pos-app"
    class="h-full flex flex-col md:flex-row overflow-hidden bg-[#090d16]" 
    x-data="posTerminal({
        initialProducts: {{ Js::from($productsSafe) }},
        categories: {{ Js::from($this->categories) }},
        creditEnabled: {{ $this->creditEnabled ? 'true' : 'false' }},
        autoPrint: {{ $autoPrint ? 'true' : 'false' }}
    })"
    x-init="init()"
>
    <!-- Offline & Network Error Banner -->
    <template x-if="isOffline || connectionError">
        <div class="absolute top-2 left-1/2 -translate-x-1/2 z-50 max-w-xl w-[92%] sm:w-auto px-4 py-2.5 rounded-xl shadow-2xl flex items-center justify-between gap-3 text-xs font-semibold backdrop-blur-md transition"
             :class="isOffline ? 'bg-amber-600/95 text-white border border-amber-400' : 'bg-rose-600/95 text-white border border-rose-400'">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span x-text="isOffline ? 'You are currently offline. Local actions are preserved; reconnection is required to complete sales.' : connectionError"></span>
            </div>
            <div class="flex items-center gap-2">
                <template x-if="!isOffline && connectionError">
                    <button @click="completeSale()" class="px-2.5 py-1 bg-white text-rose-700 font-bold rounded-md hover:bg-rose-50 text-[11px] shadow-sm">
                        Retry Checkout
                    </button>
                </template>
                <button @click="connectionError = null" class="text-white hover:text-rose-200 font-bold ml-1 text-sm">×</button>
            </div>
        </div>
    </template>

    <!-- Success Message or Flash Banner -->
    @if ($errorMessage)
        <div class="absolute top-2 left-1/2 -translate-x-1/2 z-50 bg-rose-600/95 text-white px-5 py-2.5 rounded-lg shadow-xl border border-rose-400 flex items-center gap-3 backdrop-blur-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span class="text-xs font-semibold">{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-200 hover:text-white font-bold ml-2">×</button>
        </div>
    @endif

    <!-- MOBILE TOP BAR: Switch between Products & Cart -->
    <div class="md:hidden flex items-center bg-[#111827] border-b border-slate-800 p-2 shrink-0 gap-2">
        <button 
            type="button"
            @click="mobileTab = 'catalog'"
            :class="mobileTab === 'catalog' ? 'bg-brand-500 text-white shadow-md' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
            class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            Products
        </button>
        <button 
            type="button"
            @click="mobileTab = 'cart'"
            :class="mobileTab === 'cart' ? 'bg-brand-500 text-white shadow-md' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
            class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            Cart
            <template x-if="cart.length > 0">
                <span class="px-1.5 py-0.2 rounded-full bg-brand-200 text-brand-950 font-black text-[10px]" x-text="cart.length"></span>
            </template>
            <template x-if="cart.length > 0">
                <span class="font-mono text-brand-200 ml-0.5" x-text="formatMoney(total)"></span>
            </template>
        </button>
    </div>

    <!-- LEFT PANE: Product Catalog -->
    <div 
        :class="mobileTab === 'catalog' ? 'flex' : 'hidden md:flex'"
        class="flex-1 flex-col h-full border-r border-slate-800 bg-[#0d1322] relative overflow-hidden"
    >
        <!-- Search & Filter Bar -->
        <div class="p-3 border-b border-slate-800 bg-[#111827] flex flex-col gap-2 shrink-0">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 1114 0z"></path></svg>
                    </div>
                    <input 
                        type="text" 
                        x-model="search"
                        @keydown.enter.prevent="handleBarcodeScan()"
                        x-ref="productSearch"
                        autofocus
                        placeholder="Scan barcode or search name/SKU... (Press Enter to add / F2 to focus)" 
                        class="w-full bg-[#0b1120] text-sm text-white placeholder-slate-500 rounded-lg pl-9 pr-8 py-2.5 border border-slate-700 focus:outline-none focus:border-brand-400 focus:ring-1 focus:ring-brand-400 transition"
                    >
                    <template x-if="search">
                        <button @click="search = ''; $refs.productSearch.focus()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white">
                            ×
                        </button>
                    </template>
                </div>

                <!-- Held Carts Trigger Button -->
                <button 
                    @click="showHeldCartsModal = true; loadHeldCarts()" 
                    class="relative px-3 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 flex items-center gap-1.5 transition shrink-0"
                >
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    <span>Held Carts</span>
                    @if (count($this->heldCarts) > 0)
                        <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-bold rounded-full text-[10px]">
                            {{ count($this->heldCarts) }}
                        </span>
                    @endif
                </button>
            </div>

            <!-- Category Pills (Pure Local Filter) -->
            <div class="flex items-center gap-1.5 overflow-x-auto py-1 text-xs no-scrollbar">
                <button 
                    @click="selectedCategoryId = null" 
                    :class="selectedCategoryId === null ? 'bg-brand-500 text-white shadow-sm' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                    class="px-2.5 py-1 rounded-full whitespace-nowrap font-medium transition"
                >
                    All Items
                </button>
                <template x-for="cat in categories" :key="cat.id">
                    <button 
                        @click="selectedCategoryId = cat.id" 
                        :class="selectedCategoryId === cat.id ? 'bg-brand-500 text-white shadow-sm' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                        class="px-2.5 py-1 rounded-full whitespace-nowrap font-medium transition"
                        x-text="cat.name"
                    ></button>
                </template>
            </div>
        </div>

        <!-- Product Grid (Pure Local Render - Instant Client Filtering) -->
        <div class="flex-1 overflow-y-auto p-3">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 gap-2.5">
                <template x-for="product in filteredProducts" :key="product.id">
                    <div 
                        @click="addToCart(product)"
                        :class="parseFloat(product.stock_qty) <= 0 
                            ? 'bg-slate-900/40 border-slate-800/60 opacity-50 cursor-not-allowed' 
                            : 'bg-[#131b2e] hover:bg-[#1a233b] border-slate-800 hover:border-brand-500/50 shadow-sm hover:shadow-md cursor-pointer'"
                        class="pos-product-card group relative flex flex-col justify-between p-2.5 rounded-xl border transition select-none"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-1 mb-1">
                                <span class="text-[10px] font-mono text-slate-400 truncate" x-text="product.sku"></span>
                                <span 
                                    :class="parseFloat(product.stock_qty) <= 0 ? 'bg-rose-950/60 text-rose-300 border border-rose-800/40' : 'bg-slate-800 text-slate-300'"
                                    class="text-[10px] px-1.5 py-0.2 rounded font-semibold"
                                    x-text="formatQty(product.stock_qty) + ' ' + (product.unit || '')"
                                ></span>
                            </div>
                            <h3 class="text-xs font-semibold text-white line-clamp-2 leading-snug group-hover:text-brand-300 transition" x-text="product.name"></h3>
                        </div>

                        <div class="mt-2.5 flex items-center justify-between pt-2 border-t border-slate-800/80">
                            <span class="text-sm font-extrabold text-brand-400 font-mono" x-text="formatMoney(product.sale_price)"></span>
                            <template x-if="parseFloat(product.stock_qty) <= 0">
                                <span class="text-[10px] text-rose-400 font-bold">Out</span>
                            </template>
                            <template x-if="parseFloat(product.stock_qty) > 0">
                                <span class="text-[10px] text-slate-400 group-hover:text-white transition">+ Add</span>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="filteredProducts.length === 0">
                    <div class="col-span-full py-16 text-center text-slate-500">
                        <svg class="w-12 h-12 mx-auto text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        <p class="text-sm font-medium">No matching products found</p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Floating Mobile Cart Summary Bar -->
        <template x-if="cart.length > 0">
            <div class="md:hidden p-3 bg-[#111827] border-t border-brand-500/40 flex items-center justify-between shrink-0 shadow-2xl">
                <div>
                    <div class="text-[10px] text-slate-400 font-semibold uppercase" x-text="cart.length + ' item(s) selected'"></div>
                    <div class="text-base font-extrabold text-brand-300 font-mono" x-text="formatMoney(total)"></div>
                </div>
                <button 
                    type="button"
                    @click="mobileTab = 'cart'" 
                    class="px-4 py-2 bg-brand-500 hover:bg-brand-600 active:scale-95 text-white text-xs font-extrabold rounded-xl flex items-center gap-1.5 shadow-lg shadow-brand-500/25 transition"
                >
                    View Cart & Pay
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>
        </template>
    </div>

    <!-- RIGHT PANE: Cart & Checkout (Alpine-Driven Client State) -->
    <div 
        :class="mobileTab === 'cart' ? 'flex' : 'hidden md:flex'"
        class="w-full md:w-[480px] lg:w-[520px] flex-col h-full bg-[#0e1424] shrink-0 md:border-l border-slate-800 overflow-hidden"
    >
        <!-- Cart Header: Customer Section -->
        <div class="p-3 border-b border-slate-800 bg-[#111827] shrink-0">
            <!-- Mobile Return to Catalog Button -->
            <div class="md:hidden flex items-center justify-between pb-2 mb-2 border-b border-slate-800">
                <button 
                    type="button"
                    @click="mobileTab = 'catalog'"
                    class="text-xs font-bold text-brand-400 hover:text-brand-300 flex items-center gap-1 py-0.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    + Add More Products
                </button>
                <span class="text-xs font-bold text-slate-300 font-mono" x-text="cart.length + ' Items'"></span>
            </div>

            @if ($this->creditEnabled)
                <template x-if="selectedCustomer">
                    <div class="flex items-center justify-between p-2 bg-[#1a233b] border border-brand-500/30 rounded-lg">
                        <div class="flex items-center gap-2">
                            <div class="h-7 w-7 rounded-full bg-brand-500/20 text-brand-300 font-bold flex items-center justify-center text-xs" x-text="(selectedCustomer.name || '').substring(0, 1).toUpperCase()"></div>
                            <div>
                                <div class="text-xs font-bold text-white leading-none" x-text="selectedCustomer.name"></div>
                                <div class="text-[10px] text-slate-400" x-text="selectedCustomer.phone || 'No phone'"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <template x-if="customerDue && parseFloat(customerDue) > 0">
                                <div class="text-right">
                                    <div class="text-[9px] uppercase tracking-wider text-rose-300 font-semibold">Prev Due</div>
                                    <div class="text-xs font-bold text-rose-400" x-text="formatMoney(customerDue)"></div>
                                </div>
                            </template>
                            <button @click="removeCustomer()" class="p-1 text-slate-400 hover:text-rose-400 transition" title="Change Customer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="!selectedCustomer">
                    <div class="relative flex items-center gap-2">
                        <div class="relative flex-1">
                            <input 
                                type="text" 
                                x-model="customerSearch"
                                wire:model.live.debounce.200ms="customerSearch"
                                x-ref="custSearch"
                                placeholder="Search customer (Name / Phone)... (F6)" 
                                class="w-full bg-[#0b1120] text-xs text-white placeholder-slate-500 rounded-lg px-3 py-2 border border-slate-700 focus:outline-none focus:border-brand-400"
                            >
                            @if ($customerSearch && count($this->filteredCustomers) > 0)
                                <div class="absolute left-0 right-0 top-full mt-1 bg-slate-900 border border-slate-700 rounded-lg shadow-2xl z-50 max-h-48 overflow-y-auto">
                                    @foreach ($this->filteredCustomers as $c)
                                        <button 
                                            type="button"
                                            @click="selectCustomer({ id: {{ $c->id }}, name: '{{ addslashes($c->name) }}', phone: '{{ addslashes($c->phone ?? '') }}' })" 
                                            class="w-full px-3 py-2 text-left hover:bg-slate-800 flex items-center justify-between border-b border-slate-800/60 last:border-0 text-xs"
                                        >
                                            <div>
                                                <span class="font-bold text-white">{{ $c->name }}</span>
                                                <span class="text-[10px] text-slate-400 ml-1">({{ $c->phone ?: 'No phone' }})</span>
                                            </div>
                                            <span class="text-[10px] text-brand-300 font-mono">Select</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <button 
                            type="button"
                            wire:click="$set('showQuickAddCustomer', true)" 
                            class="px-2.5 py-2 bg-brand-600 hover:bg-brand-500 text-white rounded-lg text-xs font-semibold shrink-0 transition"
                            title="New Customer"
                        >
                            + New
                        </button>
                    </div>
                </template>
            @else
                <!-- Walk-in Customer Only indicator -->
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-slate-500"></span>
                        Walk-in Cash Sale
                    </span>
                    <span class="text-[11px] font-mono text-slate-500">Credit disabled</span>
                </div>
            @endif
        </div>

        <!-- Cart Items List (Scrollable - Pure Local Alpine Rendering) -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <template x-for="(item, index) in cart" :key="item.id">
                <div class="pos-cart-item bg-[#131b2e] border border-slate-800 rounded-xl p-2.5 flex flex-col gap-2 hover:border-slate-700 transition">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-white line-clamp-1" x-text="item.name"></h4>
                            <span class="text-[10px] font-mono text-slate-400" x-text="formatMoney(item.sale_price) + ' / ' + (item.unit || 'unit')"></span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-extrabold text-white font-mono" x-text="formatMoney(item.line_total)"></span>
                        </div>
                        <button @click="removeFromCart(index)" class="text-slate-500 hover:text-rose-400 p-0.5 transition" title="Remove line">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-800/60 text-xs">
                        <!-- Qty Controls -->
                        <div class="flex items-center gap-1">
                            <button @click="decrementQty(index)" class="h-6 w-6 rounded bg-slate-800 hover:bg-slate-700 text-white font-bold flex items-center justify-center select-none">-</button>
                            <input 
                                type="text" 
                                :value="item.qty" 
                                @change="updateQty(index, $event.target.value)"
                                class="w-14 h-6 text-center font-bold text-xs bg-[#0b1120] text-white border border-slate-700 rounded focus:border-brand-400 focus:outline-none"
                            >
                            <button @click="incrementQty(index)" class="h-6 w-6 rounded bg-slate-800 hover:bg-slate-700 text-white font-bold flex items-center justify-center select-none">+</button>
                            <span class="text-[10px] text-slate-400 ml-0.5" x-text="item.unit"></span>
                        </div>

                        <!-- Item Discount -->
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-slate-400">Disc:</span>
                            <input 
                                type="text" 
                                :value="item.discount" 
                                @change="updateLineDiscount(index, $event.target.value)"
                                placeholder="0"
                                class="w-14 h-6 text-right font-mono text-[11px] bg-[#0b1120] text-white border border-slate-700 rounded px-1 focus:border-brand-400 focus:outline-none"
                            >
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="cart.length === 0">
                <div class="h-full flex flex-col items-center justify-center py-16 text-slate-600">
                    <svg class="w-12 h-12 mb-2 stroke-current opacity-40" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <p class="text-xs font-semibold">Your cart is empty</p>
                    <span class="text-[10px] text-slate-500 mt-1">Scan barcode or click items to add</span>
                </div>
            </template>
        </div>

        <!-- Cart Summary & Calculations (Zero Server Calls) -->
        <div class="p-3 border-t border-slate-800 bg-[#111827] shrink-0 space-y-2">
            <div class="flex justify-between text-xs text-slate-400">
                <span>Subtotal:</span>
                <span class="font-mono text-white" x-text="formatMoney(subtotal)"></span>
            </div>

            <!-- Overall Discount -->
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400">Overall Discount:</span>
                    <select x-model="discountType" @change="recalculateTotals()" class="bg-[#0b1120] border border-slate-700 text-slate-300 text-[10px] rounded px-1.5 py-0.5 focus:outline-none">
                        <option value="fixed">৳ Fixed</option>
                        <option value="percent">% Pct</option>
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <input 
                        type="text" 
                        x-model="discountInput"
                        @input="recalculateTotals()"
                        class="w-16 h-6 text-right font-mono text-xs bg-[#0b1120] text-white border border-slate-700 rounded px-1.5 focus:border-brand-400 focus:outline-none"
                    >
                    <template x-if="parseFloat(overallDiscount) > 0">
                        <span class="text-[10px] font-mono text-amber-400" x-text="'(-' + formatMoney(overallDiscount) + ')'"></span>
                    </template>
                </div>
            </div>

            <!-- Net Total -->
            <div class="flex items-center justify-between pt-1 border-t border-slate-800">
                <span class="text-sm font-extrabold text-white uppercase tracking-wider">Net Total:</span>
                <span class="text-xl font-extrabold text-brand-400 font-mono" x-text="formatMoney(total)"></span>
            </div>

            <!-- Payment Method Pills -->
            <div class="grid grid-cols-4 gap-1 pt-1">
                <template x-for="(label, key) in { cash: 'Cash', bkash: 'bKash', nagad: 'Nagad', bank: 'Bank' }" :key="key">
                    <button 
                        type="button"
                        @click="paymentMethod = key" 
                        :class="paymentMethod === key ? 'bg-brand-500/20 text-brand-300 border-brand-400' : 'bg-slate-800 text-slate-400 border-slate-700 hover:text-white'"
                        class="py-1.5 text-center text-xs font-bold rounded-lg border transition"
                        x-text="label"
                    ></button>
                </template>
            </div>

            <!-- Received Amount & Quick Tenders (Instant Local Calculation) -->
            <div class="space-y-1.5 pt-1">
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Received Amount (৳):</label>
                        <input 
                            type="text" 
                            x-model="receivedAmount"
                            @input="customReceived = true"
                            class="w-full bg-[#0b1120] text-sm font-bold text-white font-mono px-3 py-1.5 rounded-lg border border-slate-700 focus:border-brand-400 focus:outline-none"
                        >
                    </div>

                    <div class="flex-1 text-right">
                        <template x-if="parseFloat(dueAmount) > 0">
                            <div>
                                <label class="text-[10px] uppercase font-bold text-rose-400 block mb-0.5">Due Amount (৳):</label>
                                <span class="text-sm font-bold text-rose-400 font-mono" x-text="formatMoney(dueAmount)"></span>
                            </div>
                        </template>
                        <template x-if="parseFloat(dueAmount) <= 0">
                            <div>
                                <label class="text-[10px] uppercase font-bold text-emerald-400 block mb-0.5">Change Given (৳):</label>
                                <span class="text-sm font-bold text-emerald-400 font-mono" x-text="formatMoney(changeAmount)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Quick Tender Buttons -->
                <div class="flex items-center gap-1 text-[10px] font-mono">
                    <button type="button" @click="setReceivedExact()" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700">Exact</button>
                    <button type="button" @click="addReceived(100)" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700">+100</button>
                    <button type="button" @click="addReceived(500)" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700">+500</button>
                    <button type="button" @click="addReceived(1000)" class="flex-1 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700">+1000</button>
                </div>
            </div>

            <!-- Action Buttons: Hold, Clear, Complete -->
            <div class="grid grid-cols-4 gap-2 pt-2">
                <button 
                    type="button"
                    @click="holdCart()" 
                    :disabled="isHolding || cart.length === 0"
                    class="py-2.5 bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center justify-center gap-1"
                    title="Hold Cart (F4)"
                >
                    <template x-if="isHolding">
                        <svg class="animate-spin h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    </template>
                    <span>Hold (F4)</span>
                </button>

                <button 
                    type="button"
                    @click="clearCart()" 
                    class="py-2.5 bg-slate-800 hover:bg-rose-950/60 hover:text-rose-300 text-slate-400 text-xs font-semibold rounded-lg border border-slate-700 transition"
                >
                    Clear
                </button>

                <button 
                    type="button"
                    @click="completeSale()" 
                    :disabled="isCheckingOut || cart.length === 0"
                    class="col-span-2 py-2.5 bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white font-extrabold text-sm rounded-lg shadow-lg shadow-brand-500/20 transition flex items-center justify-center gap-2"
                >
                    <template x-if="!isCheckingOut">
                        <span>Complete (F8)</span>
                    </template>
                    <template x-if="isCheckingOut">
                        <span class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Processing...
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Add Customer Modal -->
    @if ($showQuickAddCustomer)
        <div class="fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4 backdrop-blur-sm">
            <div class="bg-[#111827] border border-slate-800 rounded-2xl w-full max-w-md p-5 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-white">Add New Customer</h3>
                    <button wire:click="$set('showQuickAddCustomer', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <form wire:submit="createCustomer" class="space-y-3 text-xs">
                    <div>
                        <label class="text-slate-300 block mb-1">Customer Name *</label>
                        <input type="text" wire:model="newCustomerName" required class="w-full bg-[#0b1120] border border-slate-700 rounded-lg p-2 text-white">
                    </div>
                    <div>
                        <label class="text-slate-300 block mb-1">Phone Number</label>
                        <input type="text" wire:model="newCustomerPhone" class="w-full bg-[#0b1120] border border-slate-700 rounded-lg p-2 text-white">
                    </div>
                    <div>
                        <label class="text-slate-300 block mb-1">Address</label>
                        <textarea wire:model="newCustomerAddress" rows="2" class="w-full bg-[#0b1120] border border-slate-700 rounded-lg p-2 text-white"></textarea>
                    </div>
                    <div>
                        <label class="text-slate-300 block mb-1">Opening Balance Due (৳)</label>
                        <input type="text" wire:model="newCustomerOpeningBalance" class="w-full bg-[#0b1120] border border-slate-700 rounded-lg p-2 text-white font-mono">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showQuickAddCustomer', false)" class="px-3 py-1.5 bg-slate-800 text-slate-300 rounded-lg">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-lg">Save & Select</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Held Carts Modal -->
    <div x-show="showHeldCartsModal" x-cloak class="fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-[#111827] border border-slate-800 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white">Held Carts</h3>
                <button @click="showHeldCartsModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @forelse ($this->heldCarts as $hc)
                    <div class="flex items-center justify-between p-3 bg-[#131b2e] border border-slate-800 rounded-xl text-xs">
                        <div>
                            <h4 class="font-bold text-white">{{ $hc->name }}</h4>
                            <span class="text-[10px] text-slate-400">{{ $hc->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="resumeCart({{ $hc->id }})" class="px-3 py-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-lg">
                                Resume
                            </button>
                            <button wire:click="deleteHeldCart({{ $hc->id }})" class="p-1 text-slate-500 hover:text-rose-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-slate-500 text-xs">No held carts available.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Sale Completed Success Modal -->
    <template x-if="showSuccessModal">
        <div class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm animate-fade-in">
            <div class="bg-[#111827] border border-brand-500/40 rounded-2xl w-full max-w-sm p-6 shadow-2xl text-center">
                <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="text-base font-extrabold text-white">Sale Completed!</h3>
                <p class="text-xs text-brand-300 font-mono mt-0.5" x-text="completedSale.invoiceNo"></p>

                <div class="my-4 py-3 bg-[#0b1120] rounded-xl border border-slate-800 space-y-1 text-xs">
                    <div class="flex justify-between px-4 text-slate-400">
                        <span>Total Paid:</span>
                        <span class="font-bold text-white font-mono" x-text="completedSale.total"></span>
                    </div>
                    <template x-if="completedSale.change && completedSale.change !== '৳ 0.00' && completedSale.change !== ''">
                        <div class="flex justify-between px-4 text-slate-400">
                            <span>Change Given:</span>
                            <span class="font-bold text-emerald-400 font-mono" x-text="completedSale.change"></span>
                        </div>
                    </template>
                </div>

                <div class="flex flex-col gap-2">
                    <a 
                        :href="'/sales/' + completedSale.id + '/receipt?autoprint=1'" 
                        target="_blank" 
                        class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl border border-slate-700 transition flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Receipt
                    </a>
                    <button 
                        @click="newSale()" 
                        class="w-full py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-xs font-extrabold rounded-xl transition shadow-lg shadow-brand-500/20"
                    >
                        New Sale
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('posTerminal', (config) => ({
        mobileTab: 'catalog',
        products: config.initialProducts || [],
        categories: config.categories || [],
        creditEnabled: config.creditEnabled || false,
        autoPrint: config.autoPrint || false,

        search: '',
        selectedCategoryId: null,

        cart: [],
        discountType: 'fixed',
        discountInput: '0',
        paymentMethod: 'cash',
        receivedAmount: '0.00',
        customReceived: false,
        note: '',

        selectedCustomer: null,
        selectedCustomerId: null,
        customerDue: '0.00',
        customerSearch: '',

        idempotencyKey: '',
        isCheckingOut: false,
        isHolding: false,
        isResuming: false,
        isOffline: !navigator.onLine,
        connectionError: null,
        showHeldCartsModal: false,

        showSuccessModal: false,
        completedSale: { id: null, invoiceNo: '', total: '0.00', change: '0.00' },

        init() {
            this.generateIdempotencyKey();

            window.addEventListener('online', () => {
                this.isOffline = false;
                this.connectionError = null;
            });
            window.addEventListener('offline', () => {
                this.isOffline = true;
            });

            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.$refs.productSearch?.focus();
                } else if (e.key === 'F4') {
                    e.preventDefault();
                    this.holdCart();
                } else if (e.key === 'F6') {
                    e.preventDefault();
                    this.$refs.custSearch?.focus();
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    this.completeSale();
                } else if (e.key === 'Escape') {
                    this.search = '';
                    this.$refs.productSearch?.blur();
                }
            });

            window.addEventListener('print-receipt', (event) => {
                const saleId = event.detail.saleId;
                if (saleId) {
                    window.open('/sales/' + saleId + '/receipt?autoprint=1', 'receipt_print_window', 'width=450,height=650');
                }
            });

            window.addEventListener('held-cart-resumed', (event) => {
                const data = event.detail.cartData;
                if (data) {
                    this.cart = data.items.map(item => ({
                        id: item.product_id,
                        name: item.name,
                        sku: item.sku,
                        barcode: item.barcode,
                        sale_price: item.unit_price,
                        qty: item.qty,
                        discount: item.discount,
                        line_total: item.line_total,
                        unit: item.unit_name,
                        allow_fractional: item.allow_fractional,
                        stock_qty: item.max_stock
                    }));
                    this.selectedCustomerId = data.customer_id;
                    this.discountInput = data.discount_input || '0';
                    this.discountType = data.discount_type || 'fixed';
                    this.note = data.note || '';
                    this.showHeldCartsModal = false;
                    this.recalculateTotals();
                }
            });
        },

        generateIdempotencyKey() {
            if (window.crypto && window.crypto.randomUUID) {
                this.idempotencyKey = window.crypto.randomUUID();
            } else {
                this.idempotencyKey = 'pos_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
            }
        },

        get filteredProducts() {
            let list = this.products;
            if (this.selectedCategoryId) {
                list = list.filter(p => p.category_id === this.selectedCategoryId);
            }
            if (this.search && this.search.trim()) {
                const term = this.search.trim().toLowerCase();
                list = list.filter(p => 
                    (p.name && p.name.toLowerCase().includes(term)) ||
                    (p.sku && p.sku.toLowerCase().includes(term)) ||
                    (p.barcode && p.barcode.toLowerCase().includes(term))
                );
            }
            return list;
        },

        handleBarcodeScan() {
            const term = (this.search || '').trim().toLowerCase();
            if (!term) return;

            // 1. Look for exact barcode match
            let found = this.products.find(p => (p.barcode && p.barcode.toLowerCase() === term) || (p.sku && p.sku.toLowerCase() === term));
            
            // 2. Fallback to single filtered product
            if (!found && this.filteredProducts.length === 1) {
                found = this.filteredProducts[0];
            }

            if (found) {
                this.addToCart(found);
                this.search = '';
            }
        },

        addToCart(product) {
            if (parseFloat(product.stock_qty) <= 0) return;

            const existing = this.cart.find(i => i.id === product.id);
            if (existing) {
                const currentQty = parseFloat(existing.qty) || 0;
                const maxStock = parseFloat(product.stock_qty) || 0;
                if (currentQty + 1 > maxStock) {
                    this.connectionError = `Cannot exceed available stock of ${maxStock} for ${product.name}.`;
                    return;
                }
                existing.qty = (currentQty + 1).toFixed(product.allow_fractional ? 3 : 0);
                this.recalculateLine(existing);
            } else {
                const item = {
                    id: product.id,
                    name: product.name,
                    sku: product.sku,
                    barcode: product.barcode,
                    unit: product.unit,
                    allow_fractional: product.allow_fractional,
                    sale_price: parseFloat(product.sale_price).toFixed(2),
                    qty: (1).toFixed(product.allow_fractional ? 3 : 0),
                    discount: '0.00',
                    line_total: parseFloat(product.sale_price).toFixed(2),
                    stock_qty: product.stock_qty
                };
                this.cart.push(item);
            }
            this.recalculateTotals();
        },

        incrementQty(index) {
            const item = this.cart[index];
            if (!item) return;
            const currentQty = parseFloat(item.qty) || 0;
            const maxStock = parseFloat(item.stock_qty) || 0;
            if (currentQty + 1 > maxStock) {
                this.connectionError = `Cannot exceed available stock of ${maxStock} for ${item.name}.`;
                return;
            }
            item.qty = (currentQty + 1).toFixed(item.allow_fractional ? 3 : 0);
            this.recalculateLine(item);
            this.recalculateTotals();
        },

        decrementQty(index) {
            const item = this.cart[index];
            if (!item) return;
            const currentQty = parseFloat(item.qty) || 0;
            if (currentQty <= 1) {
                this.removeFromCart(index);
                return;
            }
            item.qty = (currentQty - 1).toFixed(item.allow_fractional ? 3 : 0);
            this.recalculateLine(item);
            this.recalculateTotals();
        },

        updateQty(index, val) {
            const item = this.cart[index];
            if (!item) return;
            let qty = parseFloat(val) || 0;
            if (qty <= 0) {
                this.removeFromCart(index);
                return;
            }
            const maxStock = parseFloat(item.stock_qty) || 0;
            if (qty > maxStock) {
                this.connectionError = `Maximum available stock is ${maxStock} for ${item.name}.`;
                qty = maxStock;
            }
            item.qty = qty.toFixed(item.allow_fractional ? 3 : 0);
            this.recalculateLine(item);
            this.recalculateTotals();
        },

        updateLineDiscount(index, val) {
            const item = this.cart[index];
            if (!item) return;
            let disc = parseFloat(val) || 0;
            const lineGross = (parseFloat(item.qty) || 0) * (parseFloat(item.sale_price) || 0);
            if (disc < 0) disc = 0;
            if (disc > lineGross) disc = lineGross;
            item.discount = disc.toFixed(2);
            this.recalculateLine(item);
            this.recalculateTotals();
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
            this.recalculateTotals();
        },

        clearCart() {
            this.cart = [];
            this.discountInput = '0';
            this.discountType = 'fixed';
            this.receivedAmount = '0.00';
            this.customReceived = false;
            this.connectionError = null;
            this.recalculateTotals();
        },

        recalculateLine(item) {
            const gross = (parseFloat(item.qty) || 0) * (parseFloat(item.sale_price) || 0);
            const disc = parseFloat(item.discount) || 0;
            const total = Math.max(0, gross - disc);
            item.line_total = total.toFixed(2);
        },

        recalculateTotals() {
            if (!this.customReceived) {
                this.receivedAmount = this.total;
            }
        },

        get subtotal() {
            const sum = this.cart.reduce((acc, item) => acc + (parseFloat(item.line_total) || 0), 0);
            return sum.toFixed(2);
        },

        get overallDiscount() {
            const sub = parseFloat(this.subtotal) || 0;
            let disc = 0;
            if (this.discountType === 'percent') {
                const pct = Math.min(100, Math.max(0, parseFloat(this.discountInput) || 0));
                disc = (sub * pct) / 100;
            } else {
                disc = Math.min(sub, Math.max(0, parseFloat(this.discountInput) || 0));
            }
            return disc.toFixed(2);
        },

        get total() {
            const sub = parseFloat(this.subtotal) || 0;
            const disc = parseFloat(this.overallDiscount) || 0;
            return Math.max(0, sub - disc).toFixed(2);
        },

        get changeAmount() {
            const rec = parseFloat(this.receivedAmount) || 0;
            const tot = parseFloat(this.total) || 0;
            if (rec > tot) {
                return (rec - tot).toFixed(2);
            }
            return '0.00';
        },

        get dueAmount() {
            if (!this.creditEnabled) return '0.00';
            const rec = parseFloat(this.receivedAmount) || 0;
            const tot = parseFloat(this.total) || 0;
            if (rec < tot) {
                return (tot - rec).toFixed(2);
            }
            return '0.00';
        },

        setReceivedExact() {
            this.customReceived = true;
            this.receivedAmount = this.total;
        },

        addReceived(amount) {
            this.customReceived = true;
            const cur = parseFloat(this.receivedAmount) || 0;
            this.receivedAmount = (cur + amount).toFixed(2);
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.selectedCustomerId = customer.id;
            this.customerSearch = '';
        },

        removeCustomer() {
            this.selectedCustomer = null;
            this.selectedCustomerId = null;
            this.customerSearch = '';
        },

        async holdCart() {
            if (this.cart.length === 0 || this.isHolding) return;
            this.isHolding = true;
            this.connectionError = null;

            try {
                const payload = {
                    items: this.cart.map(i => ({
                        product_id: i.id,
                        name: i.name,
                        sku: i.sku,
                        barcode: i.barcode,
                        unit_price: i.sale_price,
                        qty: i.qty,
                        discount: i.discount,
                        line_total: i.line_total,
                        allow_fractional: i.allow_fractional,
                        unit_name: i.unit,
                        max_stock: i.stock_qty
                    })),
                    customer_id: this.selectedCustomerId,
                    discount: this.overallDiscount,
                    discount_input: this.discountInput,
                    discount_type: this.discountType,
                    note: this.note
                };

                const res = await this.$wire.holdCart(payload);
                if (res && res.success) {
                    this.clearCart();
                    this.selectedCustomer = null;
                    this.selectedCustomerId = null;
                    this.note = '';
                } else if (res && res.error) {
                    this.connectionError = res.error;
                }
            } catch (e) {
                this.connectionError = 'Network error: could not hold cart.';
            } finally {
                this.isHolding = false;
            }
        },

        async loadHeldCarts() {
            // Livewire automatically renders held carts modal list
        },

        async resumeCart(heldCartId) {
            if (this.isResuming) return;
            this.isResuming = true;
            this.connectionError = null;

            try {
                const res = await this.$wire.resumeCart(heldCartId);
                if (res && res.success) {
                    this.cart = res.items.map(item => ({
                        id: item.product_id,
                        name: item.name,
                        sku: item.sku,
                        barcode: item.barcode,
                        sale_price: item.unit_price,
                        qty: item.qty,
                        discount: item.discount,
                        line_total: item.line_total,
                        unit: item.unit_name,
                        allow_fractional: item.allow_fractional,
                        stock_qty: item.max_stock
                    }));
                    this.selectedCustomerId = res.customer_id;
                    this.discountInput = res.discount_input || '0';
                    this.discountType = res.discount_type || 'fixed';
                    this.note = res.note || '';
                    this.showHeldCartsModal = false;
                    this.recalculateTotals();
                } else if (res && res.error) {
                    this.connectionError = res.error;
                }
            } catch (e) {
                this.connectionError = 'Network error: could not resume held cart.';
            } finally {
                this.isResuming = false;
            }
        },

        async completeSale() {
            if (this.isCheckingOut || this.cart.length === 0) return;
            if (this.isOffline) {
                this.connectionError = 'Cannot complete sale while offline. Please check your internet connection.';
                return;
            }

            this.isCheckingOut = true;
            this.connectionError = null;

            try {
                const payload = {
                    items: this.cart.map(i => ({
                        product_id: i.id,
                        name: i.name,
                        unit_price: i.sale_price,
                        qty: i.qty,
                        discount: i.discount
                    })),
                    customer_id: this.selectedCustomerId,
                    overall_discount: this.overallDiscount,
                    received_amount: this.receivedAmount,
                    payment_method: this.paymentMethod,
                    idempotency_key: this.idempotencyKey,
                    note: this.note
                };

                const res = await this.$wire.completeSale(payload);
                if (res && res.success) {
                    this.completedSale = {
                        id: res.sale_id,
                        invoiceNo: res.invoice_no,
                        total: res.total,
                        change: res.change_amount
                    };
                    this.showSuccessModal = true;
                    this.clearCart();
                    this.selectedCustomer = null;
                    this.selectedCustomerId = null;
                    this.note = '';
                    // Generate new idempotency key ONLY after successful sale
                    this.generateIdempotencyKey();
                } else if (res && res.error) {
                    this.connectionError = res.error;
                    // Do NOT regenerate idempotencyKey on failure so user can safely retry!
                }
            } catch (err) {
                this.connectionError = 'Connection failed: Unable to reach server. Please retry checkout when connection is restored.';
                // Do NOT regenerate idempotencyKey so retry is idempotent!
            } finally {
                this.isCheckingOut = false;
            }
        },

        newSale() {
            this.showSuccessModal = false;
            this.completedSale = { id: null, invoiceNo: '', total: '0.00', change: '0.00' };
            this.clearCart();
            this.generateIdempotencyKey();
            this.$wire.newSale();
        },

        formatMoney(val) {
            const num = parseFloat(val) || 0;
            return '৳ ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatQty(val) {
            const num = parseFloat(val) || 0;
            return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
        }
    }));
});
</script>
