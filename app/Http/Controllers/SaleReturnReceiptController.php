<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleReturnReceiptController extends Controller
{
    public function show(Request $request, SaleReturn $saleReturn): View
    {
        Gate::authorize('view', $saleReturn);

        $saleReturn->load(['items.product', 'customer', 'creator', 'sale']);

        $receiptWidth = (int) Setting::get('receipt_width_mm', 80);
        $shopName = (string) Setting::get('shop_name', 'Ajmiriganj IT');
        $shopAddress = (string) Setting::get('shop_address', 'Main Bazar, Ajmiriganj, Habiganj, Sylhet');
        $shopPhone = (string) Setting::get('shop_phone', '+8801712345678');

        return view('sale-returns.receipt', [
            'return' => $saleReturn,
            'receiptWidth' => $receiptWidth,
            'shopName' => $shopName,
            'shopAddress' => $shopAddress,
            'shopPhone' => $shopPhone,
        ]);
    }
}
