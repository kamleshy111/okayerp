<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppController extends Controller
{
    /**
     * Send a WhatsApp message with dynamic template parameters.
     */
    private function sendWhatsAppMessage(string $mobileNumber, string $pdfUrl, array $params = [], string $eventKey = 'sale_created'): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        $defaultParams = [
            'customer_name' => 'Customer',
            'supplier_name' => 'Supplier',
            'amount' => '0.00',
            'invoice_no' => 'DOC',
            'date' => date('d-m-Y'),
            'pdf_url' => $pdfUrl,
            'business_name' => $user->name ?: 'OkayERP',
        ];

        $variables = array_merge($defaultParams, $params);

        // Delegate to NotificationService driver dispatch
        $result = (new \App\Services\NotificationService())->dispatchInline(
            $user,
            $eventKey,
            $variables,
            $mobileNumber,
            null,
            $pdfUrl
        );

        return isset($result['whatsapp']) && $result['whatsapp'] === 'sent';
    }

    /**
     * Send a Sales Invoice via WhatsApp.
     * Route: POST /whatsapp/send-sale-invoice/{id}
     */
    public function sendSaleInvoice(Request $request, $id)
    {
        $query = Sale::with('customer');
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
        }

        $sale = $query->find($id);

        if (!$sale) {
            return response()->json(['success' => false, 'message' => 'Sale not found.'], 404);
        }

        $phone = $sale->customer->phone ?? null;
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'Customer does not have a phone number on file.'], 422);
        }

        $pdfUrl = url("/sale/{$id}/download-pdf");
        $businessName = Auth::user()->name ?? 'OkayERP';
        $customerName = $sale->customer->name ?? 'Customer';
        $amount       = number_format($sale->grand_total, 2);

        $params = [
            'customer_name' => $customerName,
            'amount'        => $amount,
            'invoice_no'    => $sale->id,
            'date'          => $sale->created_at ? $sale->created_at->format('d-m-Y') : date('d-m-Y'),
            'pdf_url'       => $pdfUrl,
            'business_name' => $businessName,
        ];

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $params, 'sale_created');

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Invoice sent via WhatsApp successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message. Please check the phone number and try again.'], 500);
    }

    /**
     * Send a Purchase Bill via WhatsApp (to supplier).
     * Route: POST /whatsapp/send-purchase-invoice/{id}
     */
    public function sendPurchaseInvoice(Request $request, $id)
    {
        $query = Purchase::with('supplier');
        if (Auth::user()->role !== 'admin') {
            $query->where('user_id', Auth::id());
        }

        $purchase = $query->find($id);

        if (!$purchase) {
            return response()->json(['success' => false, 'message' => 'Purchase not found.'], 404);
        }

        $phone = $purchase->supplier->phone ?? null;
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'Supplier does not have a phone number on file.'], 422);
        }

        $pdfUrl = url("/purchase/{$id}/download-pdf");
        $businessName  = Auth::user()->name ?? 'OkayERP';
        $supplierName  = $purchase->supplier->name ?? 'Supplier';
        $amount        = number_format($purchase->grand_total, 2);

        $params = [
            'supplier_name' => $supplierName,
            'customer_name' => $supplierName,
            'amount'        => $amount,
            'invoice_no'    => $purchase->invoice_no ?? $purchase->id,
            'date'          => $purchase->created_at ? $purchase->created_at->format('d-m-Y') : date('d-m-Y'),
            'pdf_url'       => $pdfUrl,
            'business_name' => $businessName,
        ];

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $params, 'purchase_created');

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Purchase bill sent via WhatsApp successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message. Please check the phone number and try again.'], 500);
    }

    /**
     * Send a Customer Payment Statement via WhatsApp.
     * Route: POST /whatsapp/send-statement/{customerId}
     */
    public function sendCustomerStatement(Request $request, $customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $phone = $customer->phone ?? null;
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'Customer does not have a phone number on file.'], 422);
        }

        $pdfUrl       = url("/paymentsCustomer/{$customerId}/history/download-pdf");
        $businessName = Auth::user()->name ?? 'OkayERP';
        $customerName = $customer->name ?? 'Customer';
        $amount       = number_format($customer->total_due ?? 0, 2);

        $params = [
            'customer_name' => $customerName,
            'amount'        => $amount,
            'business_name' => $businessName,
            'pdf_url'       => $pdfUrl,
            'date'          => date('d-m-Y'),
        ];

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $params, 'aging_30');

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Statement sent via WhatsApp successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message. Please check the phone number and try again.'], 500);
    }

    /**
     * Send a Supplier Payment Statement via WhatsApp.
     * Route: POST /whatsapp/send-supplier-statement/{supplierId}
     */
    public function sendSupplierStatement(Request $request, $supplierId)
    {
        $supplier = Supplier::find($supplierId);

        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        $phone = $supplier->phone ?? null;
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'Supplier does not have a phone number on file.'], 422);
        }

        $pdfUrl       = url("/paymentSupplier/{$supplierId}/history/download-pdf");
        $businessName = Auth::user()->name ?? 'OkayERP';
        $supplierName = $supplier->name ?? 'Supplier';

        $params = [
            'supplier_name' => $supplierName,
            'customer_name' => $supplierName,
            'amount'        => '0.00',
            'business_name' => $businessName,
            'pdf_url'       => $pdfUrl,
            'date'          => date('d-m-Y'),
        ];

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $params, 'supplier_payment');

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Statement sent via WhatsApp successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message. Please check the phone number and try again.'], 500);
    }

    /**
     * Send a custom WhatsApp message with a provided PDF URL.
     * Route: POST /whatsapp/send-custom
     */
    public function sendCustom(Request $request)
    {
        $request->validate([
            'phone'   => 'required|string',
            'pdf_url' => 'required|url',
            'message' => 'nullable|string|max:500',
        ]);

        $params = [
            'pdf_url' => $request->pdf_url,
        ];

        $sent = $this->sendWhatsAppMessage($request->phone, $request->pdf_url, $params, 'sale_created');

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'WhatsApp message sent successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message.'], 500);
    }

    /**
     * Send 1-Click Aging Due Reminder from /reports/aging
     */
    public function sendAgingReminder(Request $request, $customerId)
    {
        $user = Auth::user();
        $customer = Customer::where('user_id', $user->id)->findOrFail($customerId);

        if (empty($customer->phone)) {
            return response()->json(['success' => false, 'message' => 'Customer phone number is missing.'], 422);
        }

        // Try automatic dispatch via NotificationService
        $result = (new \App\Services\NotificationService())->dispatchInline(
            $user,
            'aging_30',
            [
                'customer_name' => $customer->name,
                'amount'        => number_format($customer->total_due ?? 0, 2),
                'business_name' => $user->name ?: 'OkayERP',
                'pdf_url'       => route('paymentsCustomer.history.pdf', ['id' => $customer->id]),
                'date'          => now()->toDateString(),
            ],
            $customer->phone,
            $customer->email,
            "/reports/aging"
        );

        if (isset($result['whatsapp']) && $result['whatsapp'] === 'sent') {
            return response()->json([
                'success' => true,
                'sent_auto' => true,
                'message' => "WhatsApp reminder sent automatically to {$customer->name}!",
            ]);
        }

        // Fallback: Generate manual WhatsApp web URL if gateway is offline/unconfigured
        $cleanPhone = preg_replace('/[^0-9]/', '', $customer->phone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91' . $cleanPhone;
        }
        $pdfUrl = route('paymentsCustomer.history.pdf', ['id' => $customer->id]);
        $messageText = "Dear {$customer->name}, you have an outstanding balance of ₹" . number_format($customer->total_due ?? 0, 2) . " with " . ($user->name ?: 'OkayERP') . ". Account statement: {$pdfUrl}";
        $waUrl = "https://api.whatsapp.com/send?phone={$cleanPhone}&text=" . rawurlencode($messageText);

        return response()->json([
            'success' => true,
            'sent_auto' => false,
            'wa_url' => $waUrl,
            'message' => 'WhatsApp Gateway offline or not connected. Opening manual WhatsApp Web...',
        ]);
    }
}
