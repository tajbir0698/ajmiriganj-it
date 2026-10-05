<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleReceiptController extends Controller
{
    public function show(Request $request, Sale $sale): View
    {
        Gate::authorize('view', $sale);

        $isDuplicate = $sale->printed_count > 0 || $request->has('reprint');

        // Increment printed_count
        $sale->increment('printed_count');

        $sale->load(['items', 'customer', 'creator']);

        $receiptWidth = (int) Setting::get('receipt_width_mm', 80);
        $shopName = (string) Setting::get('shop_name', 'Ajmiriganj IT');
        $shopAddress = (string) Setting::get('shop_address', 'Main Bazar, Ajmiriganj, Habiganj, Sylhet');
        $shopPhone = (string) Setting::get('shop_phone', '+8801712345678');
        $footerText = (string) Setting::get('receipt_footer', 'Thank you for shopping with us');

        return view('sales.receipt', [
            'sale' => $sale,
            'isDuplicate' => $isDuplicate,
            'receiptWidth' => $receiptWidth,
            'shopName' => $shopName,
            'shopAddress' => $shopAddress,
            'shopPhone' => $shopPhone,
            'footerText' => $footerText,
        ]);
    }
}
