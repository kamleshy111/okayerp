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
     * Send a WhatsApp message with a PDF attachment URL.
     *
     * @param  string  $mobileNumber  The recipient's mobile number (any length, uses last 10 digits)
     * @param  string  $pdfUrl        The publicly accessible PDF URL to attach
     * @param  string  $message       Custom message body (optional)
     */
    private function sendWhatsAppMessage(string $mobileNumber, string $pdfUrl, string $message = 'Please find the attached document.'): bool
    {
        $apiKey = config('services.whatsapp.api_key', '30dce73d773a4ceaa7b35c369e4b5b43');
        $campName = config('services.whatsapp.camp_name', 'sarpanchsangh');

        $encodedMessage = rawurlencode($message);
        $encodedPdfUrl  = rawurlencode($pdfUrl);

        // Ensure we only use the last 10 digits
        $cleanNumber = preg_replace('/\D/', '', $mobileNumber);
        $mobile10    = substr($cleanNumber, -10);

        if (strlen($mobile10) !== 10) {
            return false;
        }

        $apiUrl = 'https://wapi.hspsms.com/public/wa/api/send'
            . '?campname=' . rawurlencode($campName)
            . '&campbody=' . $encodedMessage
            . '&contact=91' . $mobile10
            . '&apikey=' . $apiKey
            . '&attpdf=' . $encodedPdfUrl;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $httpCode >= 200 && $httpCode < 300;
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

        $message = "Dear {$customerName}, please find your invoice #{$id} for ₹{$amount} from {$businessName}. You can download it using the link provided.";

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $message);

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

        $message = "Dear {$supplierName}, please find the purchase bill #{$id} for ₹{$amount} from {$businessName}.";

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $message);

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

        $message = "Dear {$customerName}, please find your account statement from {$businessName} attached.";

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $message);

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

        $message = "Dear {$supplierName}, please find your account statement from {$businessName} attached.";

        $sent = $this->sendWhatsAppMessage($phone, $pdfUrl, $message);

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

        $message = $request->input('message', 'Please find the attached document.');
        $sent    = $this->sendWhatsAppMessage($request->phone, $request->pdf_url, $message);

        if ($sent) {
            return response()->json(['success' => true, 'message' => 'WhatsApp message sent successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message.'], 500);
    }
}
