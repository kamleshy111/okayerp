<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sale;
use App\Models\Purchase;

class RepairInvoiceTotals extends Command
{
    protected $signature = 'repair:invoices';
    protected $description = 'Repairs the total_amount, gst_amount, and grand_total for all sales and purchases by recalculating from line items.';

    public function handle()
    {
        $salesCount = 0;
        foreach (Sale::all() as $sale) {
            $totalAmount = 0;
            $gstAmount = 0;
            foreach ($sale->saleItems as $item) {
                $base = $item->quantity * $item->price;
                $totalAmount += $base;
                if ($sale->accepted == 1) {
                    $gst = $base * (($item->sgst + $item->cgst) / 100);
                    $gstAmount += $gst;
                }
            }
            $grandTotal = $totalAmount + $gstAmount - $sale->discount;
            
            if (abs($sale->grand_total - $grandTotal) > 0.01) {
                $sale->update([
                    'total_amount' => $totalAmount,
                    'gst_amount' => $gstAmount,
                    'grand_total' => $grandTotal
                ]);
                $salesCount++;
            }
        }

        $purchasesCount = 0;
        foreach (Purchase::all() as $purchase) {
            $totalAmount = 0;
            $gstAmount = 0;
            foreach ($purchase->items as $item) {
                $base = $item->quantity * $item->price;
                $totalAmount += $base;
                if ($purchase->accepted == 1) {
                    $gst = $base * (($item->sgst + $item->cgst) / 100);
                    $gstAmount += $gst;
                }
            }
            $grandTotal = $totalAmount + $gstAmount + $purchase->transport_amount;
            
            if (abs($purchase->grand_total - $grandTotal) > 0.01) {
                $purchase->update([
                    'total_amount' => $totalAmount,
                    'gst_amount' => $gstAmount,
                    'grand_total' => $grandTotal
                ]);
                $purchasesCount++;
            }
        }

        $this->info("Repaired {$salesCount} sales and {$purchasesCount} purchases.");
    }
}
