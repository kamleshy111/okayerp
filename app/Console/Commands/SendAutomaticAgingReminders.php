<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendAutomaticAgingReminders extends Command
{
    protected $signature = 'reminders:send-aging';
    protected $description = 'Automatically send WhatsApp/SMS balance reminders to customers based on their aging bucket and store preferences.';

    public function handle()
    {
        // Get all store users that have either WhatsApp or SMS reminders enabled
        $stores = User::where('role', 'store')
            ->where(function ($query) {
                $query->where('auto_whatsapp_reminders_enabled', true)
                      ->orWhere('auto_sms_reminders_enabled', true);
            })
            ->get();

        $this->info("Found " . $stores->count() . " stores with reminders enabled.");

        foreach ($stores as $store) {
            $this->info("Processing store: {$store->name} (ID: {$store->id})");
            $customers = Customer::where('user_id', $store->id)->get();

            foreach ($customers as $customer) {
                if (empty($customer->phone)) {
                    continue;
                }

                // Aging Calculation Logic
                $storeCreditRefundsSum = (float)SaleReturn::whereHas('sale', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                })
                ->where('refund_method', 'Store Credit')
                ->get()
                ->sum(fn($r) => (float)$r->refund_amount + (float)$r->gst_refund_amount);

                $totalPayments = $customer->payments()->whereNull('sale_id')->sum('amount') + $storeCreditRefundsSum;
                $sales = $customer->sales()->with('saleReturns')->orderBy('created_at', 'asc')->get();

                $oldestAge = null;
                $totalDue = 0.0;

                foreach ($sales as $sale) {
                    $actualPaid = SalePayment::where('sale_id', $sale->id)->sum('amount');
                    $dueDeductionsSum = (float)$sale->saleReturnItems->sum('due_deduction');
                    $outstanding = (double)$sale->grand_total - (double)max($sale->paid, $actualPaid) - $dueDeductionsSum;

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
                        $totalDue += $outstanding;
                        $date = Carbon::parse($sale->created_at);
                        $age = $date->isFuture() ? 0 : abs(Carbon::now()->diffInDays($date));

                        if ($oldestAge === null || $age > $oldestAge) {
                            $oldestAge = $age;
                        }
                    }
                }

                if ($totalPayments > 0) {
                    $totalDue -= $totalPayments;
                }

                // If customer has outstanding dues
                if ($totalDue > 0 && $oldestAge !== null) {
                    $bucket = '';
                    if ($oldestAge <= 30) {
                        $bucket = '30_days';
                    } elseif ($oldestAge <= 60) {
                        $bucket = '60_days';
                    } else {
                        $bucket = '90_days';
                    }

                    // 1. Process WhatsApp reminders
                    if ($store->auto_whatsapp_reminders_enabled) {
                        $waFrequency = 'disabled';
                        if ($bucket === '30_days') {
                            $waFrequency = $store->auto_whatsapp_30_frequency;
                        } elseif ($bucket === '60_days') {
                            $waFrequency = $store->auto_whatsapp_60_frequency;
                        } else {
                            $waFrequency = $store->auto_whatsapp_90_frequency;
                        }

                        if ($waFrequency !== 'disabled' && $this->shouldSendReminder($customer->last_whatsapp_sent_at, $waFrequency)) {
                            $this->sendWhatsAppReminder($store, $customer, $totalDue, $bucket);
                        }
                    }

                    // 2. Process SMS reminders
                    if ($store->auto_sms_reminders_enabled) {
                        $smsFrequency = 'disabled';
                        if ($bucket === '30_days') {
                            $smsFrequency = $store->auto_sms_30_frequency;
                        } elseif ($bucket === '60_days') {
                            $smsFrequency = $store->auto_sms_60_frequency;
                        } else {
                            $smsFrequency = $store->auto_sms_90_frequency;
                        }

                        if ($smsFrequency !== 'disabled' && $this->shouldSendReminder($customer->last_sms_sent_at, $smsFrequency)) {
                            $this->sendSmsReminder($store, $customer, $totalDue, $bucket);
                        }
                    }
                }
            }
        }
    }

    /**
     * Determine if a reminder should be sent based on last sent timestamp and frequency.
     */
    private function shouldSendReminder($lastSentAt, $frequency)
    {
        if ($lastSentAt === null) {
            return true;
        }

        $lastSent = Carbon::parse($lastSentAt);
        $daysElapsed = Carbon::now()->diffInDays($lastSent);

        switch ($frequency) {
            case 'daily':
                return $daysElapsed >= 1;
            case 'three_times_a_week':
                return $daysElapsed >= 2;
            case 'twice_a_week':
                return $daysElapsed >= 3;
            case 'weekly':
                return $daysElapsed >= 7;
            case 'twice_a_month':
                return $daysElapsed >= 15;
            case 'once_a_month':
                return $daysElapsed >= 30;
            default:
                return false;
        }
    }

    /**
     * Send WhatsApp reminder.
     */
    private function sendWhatsAppReminder($store, $customer, $totalDue, $bucket)
    {
        $eventKey = 'aging_' . str_replace('_days', '', $bucket);
        $amount = number_format($totalDue, 2);
        $pdfUrl = url("/paymentsCustomer/{$customer->id}/history/download-pdf");

        (new \App\Services\NotificationService())->dispatchInline(
            $store,
            $eventKey,
            [
                'customer_name' => $customer->name,
                'amount' => $amount,
                'business_name' => $store->name ?: 'OkayERP',
                'pdf_url' => $pdfUrl,
                'date' => now()->toDateString(),
            ],
            $customer->phone,
            $customer->email,
            "/reports/aging"
        );

        $customer->update([
            'last_whatsapp_sent_at' => now(),
            'last_whatsapp_bucket' => $bucket,
        ]);
    }

    /**
     * Send SMS reminder.
     */
    private function sendSmsReminder($store, $customer, $totalDue, $bucket)
    {
        $eventKey = 'aging_' . str_replace('_days', '', $bucket);
        $amount = number_format($totalDue, 2);
        $pdfUrl = url("/paymentsCustomer/{$customer->id}/history/download-pdf");

        (new \App\Services\NotificationService())->dispatchInline(
            $store,
            $eventKey,
            [
                'customer_name' => $customer->name,
                'amount' => $amount,
                'business_name' => $store->name ?: 'OkayERP',
                'pdf_url' => $pdfUrl,
                'date' => now()->toDateString(),
            ],
            $customer->phone,
            $customer->email,
            "/reports/aging"
        );

        $customer->update([
            'last_sms_sent_at' => now(),
            'last_sms_bucket' => $bucket,
        ]);
    }
}
