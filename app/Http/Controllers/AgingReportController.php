<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AgingReportController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // 1. Accounts Receivable (AR) Calculation
        $customers = Customer::where('user_id', $userId)->get();
        $arData = [];
        $arSummary = [
            'total_receivables' => 0.0,
            'total_0_30' => 0.0,
            'total_31_60' => 0.0,
            'total_61_90' => 0.0,
            'total_90_plus' => 0.0,
        ];

        foreach ($customers as $customer) {
            $paymentQuery = $customer->payments();
            $saleQuery = $customer->sales();
            if (session('private_ledger_unlocked') !== true) {
                $paymentQuery->where('accepted', 1);
                $saleQuery->where('accepted', 1);
            }
            $totalPayments = $paymentQuery->whereNull('sale_id')->sum('amount');
            $sales = $saleQuery->orderBy('created_at', 'asc')->get();

            $buckets = [
                'bucket_0_30' => 0.0,
                'bucket_31_60' => 0.0,
                'bucket_61_90' => 0.0,
                'bucket_90_plus' => 0.0,
            ];

            foreach ($sales as $sale) {
                $outstanding = (double)$sale->grand_total - (double)$sale->paid;
                if ($outstanding < 0) {
                    $totalPayments += abs($outstanding);
                    continue;
                }
                if ($outstanding == 0) {
                    continue;
                }

                if ($totalPayments > 0) {
                    if ($totalPayments >= $outstanding) {
                        $totalPayments -= $outstanding;
                        $outstanding = 0.0;
                    } else {
                        $outstanding -= $totalPayments;
                        $totalPayments = 0.0;
                    }
                }

                if ($outstanding > 0) {
                    $date = Carbon::parse($sale->created_at);
                    $age = $date->isFuture() ? 0 : abs(Carbon::now()->diffInDays($date));

                    if ($age <= 30) {
                        $buckets['bucket_0_30'] += $outstanding;
                    } elseif ($age <= 60) {
                        $buckets['bucket_31_60'] += $outstanding;
                    } elseif ($age <= 90) {
                        $buckets['bucket_61_90'] += $outstanding;
                    } else {
                        $buckets['bucket_90_plus'] += $outstanding;
                    }
                }
            }

            if ($totalPayments > 0) {
                // Unapplied payments reduce the current bucket (0-30 days)
                $buckets['bucket_0_30'] -= $totalPayments;
            }

            $totalDue = array_sum($buckets);

            // Only include customers who have non-zero aging or outstanding amounts
            if ($totalDue != 0 || array_filter($buckets)) {
                $arData[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone ?? 'N/A',
                    'email' => $customer->email ?? 'N/A',
                    'total_due' => round($totalDue, 2),
                    'bucket_0_30' => round($buckets['bucket_0_30'], 2),
                    'bucket_31_60' => round($buckets['bucket_31_60'], 2),
                    'bucket_61_90' => round($buckets['bucket_61_90'], 2),
                    'bucket_90_plus' => round($buckets['bucket_90_plus'], 2),
                ];

                $arSummary['total_receivables'] += $totalDue;
                $arSummary['total_0_30'] += $buckets['bucket_0_30'];
                $arSummary['total_31_60'] += $buckets['bucket_31_60'];
                $arSummary['total_61_90'] += $buckets['bucket_61_90'];
                $arSummary['total_90_plus'] += $buckets['bucket_90_plus'];
            }
        }

        // Round summary values
        foreach ($arSummary as $key => $val) {
            $arSummary[$key] = round($val, 2);
        }

        // 2. Accounts Payable (AP) Calculation
        $suppliers = Supplier::where('user_id', $userId)->get();
        $apData = [];
        $apSummary = [
            'total_payables' => 0.0,
            'total_0_30' => 0.0,
            'total_31_60' => 0.0,
            'total_61_90' => 0.0,
            'total_90_plus' => 0.0,
        ];

        foreach ($suppliers as $supplier) {
            $paymentQuery = $supplier->purchasePayments();
            $purchaseQuery = $supplier->purchases();
            if (session('private_ledger_unlocked') !== true) {
                $paymentQuery->where('accepted', 1);
                $purchaseQuery->where('accepted', 1);
            }
            $totalPayments = $paymentQuery->whereNull('purchase_id')->sum('amount');
            $purchases = $purchaseQuery->orderBy('purchase_date', 'asc')->orderBy('created_at', 'asc')->get();

            $buckets = [
                'bucket_0_30' => 0.0,
                'bucket_31_60' => 0.0,
                'bucket_61_90' => 0.0,
                'bucket_90_plus' => 0.0,
            ];

            foreach ($purchases as $purchase) {
                $outstanding = (double)$purchase->grand_total - (double)$purchase->paid;
                if ($outstanding < 0) {
                    $totalPayments += abs($outstanding);
                    continue;
                }
                if ($outstanding == 0) {
                    continue;
                }

                if ($totalPayments > 0) {
                    if ($totalPayments >= $outstanding) {
                        $totalPayments -= $outstanding;
                        $outstanding = 0.0;
                    } else {
                        $outstanding -= $totalPayments;
                        $totalPayments = 0.0;
                    }
                }

                if ($outstanding > 0) {
                    $date = Carbon::parse($purchase->purchase_date ?? $purchase->created_at);
                    $age = $date->isFuture() ? 0 : abs(Carbon::now()->diffInDays($date));

                    if ($age <= 30) {
                        $buckets['bucket_0_30'] += $outstanding;
                    } elseif ($age <= 60) {
                        $buckets['bucket_31_60'] += $outstanding;
                    } elseif ($age <= 90) {
                        $buckets['bucket_61_90'] += $outstanding;
                    } else {
                        $buckets['bucket_90_plus'] += $outstanding;
                    }
                }
            }

            if ($totalPayments > 0) {
                // Unapplied payments reduce the current bucket (0-30 days)
                $buckets['bucket_0_30'] -= $totalPayments;
            }

            $totalDue = array_sum($buckets);

            // Only include suppliers who have non-zero aging or outstanding amounts
            if ($totalDue != 0 || array_filter($buckets)) {
                $apData[] = [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone ?? 'N/A',
                    'email' => $supplier->email ?? 'N/A',
                    'total_due' => round($totalDue, 2),
                    'bucket_0_30' => round($buckets['bucket_0_30'], 2),
                    'bucket_31_60' => round($buckets['bucket_31_60'], 2),
                    'bucket_61_90' => round($buckets['bucket_61_90'], 2),
                    'bucket_90_plus' => round($buckets['bucket_90_plus'], 2),
                ];

                $apSummary['total_payables'] += $totalDue;
                $apSummary['total_0_30'] += $buckets['bucket_0_30'];
                $apSummary['total_31_60'] += $buckets['bucket_31_60'];
                $apSummary['total_61_90'] += $buckets['bucket_61_90'];
                $apSummary['total_90_plus'] += $buckets['bucket_90_plus'];
            }
        }

        // Round summary values
        foreach ($apSummary as $key => $val) {
            $apSummary[$key] = round($val, 2);
        }

        return Inertia::render('Reports/Aging', [
            'arData' => $arData,
            'arSummary' => $arSummary,
            'apData' => $apData,
            'apSummary' => $apSummary,
        ]);
    }
}
