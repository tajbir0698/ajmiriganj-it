<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseReturnReceiptController extends Controller
{
    public function show(Request $request, PurchaseReturn $purchaseReturn): View
    {
        Gate::authorize('view', $purchaseReturn);

        $purchaseReturn->load(['items.product', 'vendor', 'creator', 'purchase']);

        $shopName = (string) Setting::get('shop_name', 'Ajmiriganj IT');
        $shopAddress = (string) Setting::get('shop_address', 'Main Bazar, Ajmiriganj, Habiganj, Sylhet');
        $shopPhone = (string) Setting::get('shop_phone', '+8801712345678');

        return view('purchase-returns.receipt', [
            'return' => $purchaseReturn,
            'shopName' => $shopName,
            'shopAddress' => $shopAddress,
            'shopPhone' => $shopPhone,
        ]);
    }
}
