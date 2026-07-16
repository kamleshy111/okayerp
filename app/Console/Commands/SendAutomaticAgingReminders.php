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
        $cleanNumber = preg_replace('/\D/', '', $customer->phone);
        $mobile10 = substr($cleanNumber, -10);
        if (strlen($mobile10) !== 10) {
            return;
        }

        $apiUrlBase = $store->whatsapp_api_url ?: 'https://wapi.hspsms.com/public/wa/api/send';
        $apiKey = $store->whatsapp_api_key ?: config('services.whatsapp.api_key', '30dce73d773a4ceaa7b35c369e4b5b43');
        $campName = $store->whatsapp_app_name ?: config('services.whatsapp.camp_name', 'sarpanchsangh');
        
        $pdfUrl = url("/paymentsCustomer/{$customer->id}/history/download-pdf");
        $businessName = $store->name ?? 'OkayERP';
        $amount = number_format($totalDue, 2);

        $template = $store->whatsapp_message_template ?: "Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Please find your account statement link below. Thank you!";
        
        $message = str_replace(
            ['{customer_name}', '{amount}', '{business_name}', '{pdf_url}'],
            [$customer->name, $amount, $businessName, $pdfUrl],
            $template
        );

        $apiUrl = $apiUrlBase
            . '?campname=' . rawurlencode($campName)
            . '&campbody=' . rawurlencode($message)
            . '&contact=91' . $mobile10
            . '&apikey=' . $apiKey
            . '&attpdf=' . rawurlencode($pdfUrl);

        try {
            $response = Http::timeout(15)->get($apiUrl);
            if ($response->successful()) {
                $customer->update([
                    'last_whatsapp_sent_at' => now(),
                    'last_whatsapp_bucket' => $bucket,
                ]);

                AuditLog::create([
                    'user_id' => $store->id,
                    'action' => 'send_automatic_whatsapp_aging_reminder',
                    'model_type' => Customer::class,
                    'model_id' => $customer->id,
                    'new_values' => [
                        'bucket' => $bucket,
                        'total_due' => $totalDue,
                        'message_sent' => $message,
                    ],
                ]);

                Log::info("Auto WhatsApp reminder sent to {$customer->name} ({$mobile10}): " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed sending auto WhatsApp reminder to {$customer->name}: " . $e->getMessage());
        }
    }

    /**
     * Send SMS reminder.
     */
    private function sendSmsReminder($store, $customer, $totalDue, $bucket)
    {
        $cleanNumber = preg_replace('/\D/', '', $customer->phone);
        $mobile10 = substr($cleanNumber, -10);
        if (strlen($mobile10) !== 10) {
            return;
        }

        $apiUrlBase = $store->sms_api_url ?: 'http://sms.hspsms.com/sendSMS';
        $apiKey = $store->sms_api_key ?: config('services.whatsapp.api_key', '30dce73d773a4ceaa7b35c369e4b5b43');
        $senderName = $store->sms_sender_name ?: 'SARPCH';
        
        $pdfUrl = url("/paymentsCustomer/{$customer->id}/history/download-pdf");
        $businessName = $store->name ?? 'OkayERP';
        $amount = number_format($totalDue, 2);

        $template = $store->sms_message_template ?: "Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Please find your account statement link: {pdf_url} Thank you!";
        
        $message = str_replace(
            ['{customer_name}', '{amount}', '{business_name}', '{pdf_url}'],
            [$customer->name, $amount, $businessName, $pdfUrl],
            $template
        );

        $apiUrl = $apiUrlBase
            . '?apikey=' . $apiKey
            . '&message=' . rawurlencode($message)
            . '&numbers=91' . $mobile10
            . '&sendername=' . rawurlencode($senderName)
            . '&smstype=TRANS';

        try {
            $response = Http::timeout(15)->get($apiUrl);
            if ($response->successful()) {
                $customer->update([
                    'last_sms_sent_at' => now(),
                    'last_sms_bucket' => $bucket,
                ]);

                AuditLog::create([
                    'user_id' => $store->id,
                    'action' => 'send_automatic_sms_aging_reminder',
                    'model_type' => Customer::class,
                    'model_id' => $customer->id,
                    'new_values' => [
                        'bucket' => $bucket,
                        'total_due' => $totalDue,
                        'message_sent' => $message,
                    ],
                ]);

                Log::info("Auto SMS reminder sent to {$customer->name} ({$mobile10}): " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed sending auto SMS reminder to {$customer->name}: " . $e->getMessage());
        }
    }
}
