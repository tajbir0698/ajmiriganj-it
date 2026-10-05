<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Atomically generate the next sequential document number with pessimistic locking.
     */
    public static function next(string $type, string $prefix, int $padLength = 6): string
    {
        return DB::transaction(function () use ($type, $prefix, $padLength): string {
            /** @var DocumentSequence $sequence */
            $sequence = DocumentSequence::where('type', $type)->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'type' => $type,
                    'current_number' => 0,
                ]);
                // Re-lock after creation
                $sequence = DocumentSequence::where('type', $type)->lockForUpdate()->first();
            }

            $sequence->current_number++;
            $sequence->save();

            return sprintf('%s-%s', $prefix, str_pad((string) $sequence->current_number, $padLength, '0', STR_PAD_LEFT));
        });
    }

    public static function nextPurchaseInvoice(): string
    {
        return self::next('purchase', 'PUR', 6);
    }

    public static function nextAdjustmentNo(): string
    {
        return self::next('adjustment', 'ADJ', 6);
    }

    public static function nextInvoiceNo(): string
    {
        return self::next('sale', 'INV', 6);
    }

    public static function nextCustomerPaymentNo(): string
    {
        return self::next('customer_payment', 'CPAY', 6);
    }

    public static function nextVoucherNo(): string
    {
        return self::next('voucher', 'VCH', 6);
    }

    public static function nextPurchaseReturnNo(): string
    {
        return self::next('purchase_return', 'PRT', 6);
    }

    public static function nextSaleReturnNo(): string
    {
        return self::next('sale_return', 'SRT', 6);
    }

    public static function nextRestockRequestNo(): string
    {
        return self::next('restock_request', 'REQ', 6);
    }
}
