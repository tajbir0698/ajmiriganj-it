<?php

declare(strict_types=1);

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\CustomerPaymentReceiptController;
use App\Http\Controllers\PurchaseReturnReceiptController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\SaleReturnReceiptController;
use App\Livewire\Pos\PosScreen;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/pos', PosScreen::class)->name('pos');
    Route::get('/sales/{sale}/receipt', [SaleReceiptController::class, 'show'])->name('sales.receipt');
    Route::get('/sale-returns/{saleReturn}/receipt', [SaleReturnReceiptController::class, 'show'])->name('sale-returns.receipt');
    Route::get('/purchase-returns/{purchaseReturn}/receipt', [PurchaseReturnReceiptController::class, 'show'])->name('purchase-returns.receipt');
    Route::get('/customer-payments/{customerPayment}/receipt', [CustomerPaymentReceiptController::class, 'show'])->name('customer-payments.receipt');
    Route::get('/admin/attachments/{attachment}/download', AttachmentDownloadController::class)
        ->name('admin.attachments.download');

    Route::prefix('reports/print')->name('reports.print.')->group(function () {
        Route::get('/balance-sheet', [\App\Http\Controllers\ReportPrintController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/sales', [\App\Http\Controllers\ReportPrintController::class, 'sales'])->name('sales');
        Route::get('/product-sales', [\App\Http\Controllers\ReportPrintController::class, 'productSales'])->name('product-sales');
        Route::get('/purchases', [\App\Http\Controllers\ReportPrintController::class, 'purchases'])->name('purchases');
        Route::get('/profit-and-loss', [\App\Http\Controllers\ReportPrintController::class, 'profitAndLoss'])->name('profit-and-loss');
        Route::get('/daily-summary', [\App\Http\Controllers\ReportPrintController::class, 'dailySummary'])->name('daily-summary');
        Route::get('/stock-valuation', [\App\Http\Controllers\ReportPrintController::class, 'stockValuation'])->name('stock-valuation');
        Route::get('/customer-due', [\App\Http\Controllers\ReportPrintController::class, 'customerDue'])->name('customer-due');
        Route::get('/vendor-due', [\App\Http\Controllers\ReportPrintController::class, 'vendorDue'])->name('vendor-due');
        Route::get('/dead-stock', [\App\Http\Controllers\ReportPrintController::class, 'deadStock'])->name('dead-stock');
        Route::get('/stock-losses', [\App\Http\Controllers\ReportPrintController::class, 'stockLosses'])->name('stock-losses');
        Route::get('/customer-collections', [\App\Http\Controllers\ReportPrintController::class, 'customerCollections'])->name('customer-collections');
        Route::get('/vendor-payments', [\App\Http\Controllers\ReportPrintController::class, 'vendorPayments'])->name('vendor-payments');
        Route::get('/payment-method', [\App\Http\Controllers\ReportPrintController::class, 'paymentMethod'])->name('payment-method');
        Route::get('/income-expense', [\App\Http\Controllers\ReportPrintController::class, 'incomeExpense'])->name('income-expense');
        Route::get('/price-history', [\App\Http\Controllers\ReportPrintController::class, 'priceHistory'])->name('price-history');
        Route::get('/audit-log', [\App\Http\Controllers\ReportPrintController::class, 'auditLog'])->name('audit-log');
    });
});
