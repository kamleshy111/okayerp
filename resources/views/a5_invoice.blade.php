<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice</title>
  <style>
    @page {
      margin: 12px;
    }

    body {
      margin: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 8px;
      color: #000;
      line-height: 1.15;
    }

    .invoice-container {
      width: 100%;
      border: 1px solid #2e2c92;
      padding: 0;
      box-sizing: border-box;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    td, th {
      padding: 3px 5px;
      vertical-align: top;
    }

    .border-bottom {
      border-bottom: 1px solid #2e2c92;
    }

    .border-right {
      border-right: 1px solid #2e2c92;
    }

    .border-top {
      border-top: 1px solid #2e2c92;
    }

    .text-center {
      text-align: center;
    }

    .text-right {
      text-align: right;
    }

    .bold {
      font-weight: bold;
    }

    /* Header styling */
    .header-table td {
      padding: 4px;
    }

    .logo-container {
      width: 20%;
      text-align: left;
      vertical-align: middle;
    }

    .company-details {
      width: 60%;
      text-align: center;
    }

    .company-name {
      font-size: 11px;
      font-weight: bold;
      margin-bottom: 1px;
      text-transform: uppercase;
      color: #2e2c92;
    }

    .invoice-type {
      width: 20%;
      text-align: right;
      font-size: 8px;
    }

    /* Meta Info Grid */
    .meta-table td {
      width: 50%;
      padding: 0;
    }

    .meta-sub-table td {
      padding: 2px 4px;
      border-bottom: 1px solid #ddd;
    }

    .meta-sub-table tr:last-child td {
      border-bottom: none;
    }

    /* Billed / Shipped To styling */
    .party-table td {
      width: 50%;
      padding: 3px 5px;
      border-bottom: 1px solid #2e2c92;
    }

    /* Items Table styling */
    .items-table th {
      background-color: #2e2c92;
      color: #ffffff;
      font-weight: bold;
      text-align: center;
      padding: 3px;
      font-size: 8px;
    }

    .items-table td {
      padding: 3px;
    }

    .item-row td {
      height: 14px;
    }

    .total-row td {
      padding: 3px;
      border-top: 1px solid #2e2c92;
      background-color: #f9fafb;
    }

    /* Footer styling */
    .footer-table td {
      width: 50%;
      padding: 4px;
    }

    .terms-list {
      margin: 1px 0 0 0;
      padding-left: 10px;
    }
  </style>
</head>
<body>

@php
  if (!function_exists('convertNumberToWords')) {
      function convertNumberToWords($number, $currencyCode = 'INR') {
          $decimal = round($number - ($no = floor($number)), 2) * 100;
          $hundred = null;
          $digits_length = strlen($no);
          $i = 0;
          $str = array();
          $words = array(
              0 => '', 1 => 'One', 2 => 'Two',
              3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
              7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
              10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
              13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
              16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
              19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
              40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
              70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
          );
          $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
          while( $i < $digits_length ) {
              $divider = ($i == 2) ? 10 : 100;
              $number = floor($no % $divider);
              $no = floor($no / $divider);
              $i += $divider == 10 ? 1 : 2;
              if ($number) {
                  $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                  $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                  $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter].$plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
              } else $str[] = null;
          }
          $mainUnits = implode('', array_reverse($str));
          if ($currencyCode !== 'INR') {
              $paise = ($decimal > 0) ? "and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Cents ' : '';
              return ($mainUnits ? $mainUnits . $currencyCode . ' ' : '') . $paise . 'Only';
          } else {
              $paise = ($decimal > 0) ? "and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise ' : '';
              return ($mainUnits ? $mainUnits . 'Rupees ' : '') . $paise . 'Only';
          }
      }
  }

  $customer = $sale->customer;
  $isExport = $customer && !empty($customer->country) && strtolower(trim($customer->country)) !== 'india';
  $currencySymbol = '₹';
  $currencyCode = 'INR';
  $exchangeRate = 1.0000;

  if ($isExport && isset($sale->currency) && !empty($sale->currency) && isset($sale->exchange_rate) && $sale->exchange_rate > 0) {
      $currencyCode = $sale->currency;
      $exchangeRate = (double) $sale->exchange_rate;
      switch ($currencyCode) {
          case 'USD': $currencySymbol = '$'; break;
          case 'GBP': $currencySymbol = '£'; break;
          case 'EUR': $currencySymbol = '€'; break;
          case 'SGD': $currencySymbol = 'S$'; break;
          case 'SAR': $currencySymbol = 'SR'; break;
          case 'CAD': $currencySymbol = 'C$'; break;
          case 'AUD': $currencySymbol = 'A$'; break;
          case 'AED': $currencySymbol = 'AED'; break;
          default: $currencySymbol = $currencyCode; break;
      }
  }

  $store = $sale->customer && $sale->customer->user ? $sale->customer->user : null;
  $hasPhysicalItems = $sale->saleItems->contains(function ($item) {
      return !$item->product || $item->product->type !== 'service';
  });

  $totalQty = 0;
  $subtotal = 0;
  foreach ($sale->saleItems as $item) {
      $totalQty += $item->quantity;
      $subtotal += $item->base_price;
  }
  $roundedGrandTotal = round($sale->grand_total);
@endphp

  <div class="invoice-container">
    <!-- Header Section -->
    <table class="header-table border-bottom">
      <tr>
        <td class="logo-container">
          @if($store && $store->profile_photo && file_exists(storage_path('app/public/' . $store->profile_photo)))
            <img src="{{ storage_path('app/public/' . $store->profile_photo) }}" style="height: 40px; width: auto;">
          @elseif(file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" style="height: 40px; width: auto;">
          @else
            <span style="font-size: 11px; font-weight: bold; color: #2e2c92;">{{ $store ? $store->name : 'OKAY ERP' }}</span>
          @endif
        </td>
        <td class="company-details">
          <div class="bold text-center" style="font-size: 10px; margin-bottom: 1px; letter-spacing: 1px;">
            @if (($sale->gst_amount ?? 0) <= 0)
              {{ !empty($store->invoice_title_without_gst) ? $store->invoice_title_without_gst : 'INVOICE' }}
            @else
              {{ !empty($store->invoice_title_with_gst) ? $store->invoice_title_with_gst : 'TAX INVOICE' }}
            @endif
          </div>
          <div class="company-name">{{ $store ? $store->name : 'Your Store Name' }}</div>
          <div style="font-size: 9px; color: #4b5563;">{{ $store ? $store->address : 'Store Address' }}</div>
        </td>
        <td class="invoice-type text-right bold">
          Original for Recipient
        </td>
      </tr>
    </table>

    <!-- Meta Info Section -->
    <table class="meta-table border-bottom">
      <tr>
        <td class="border-right" style="width: 50%;">
          <table class="meta-sub-table" style="width: 100%;">
            <tr>
              <td class="bold" style="width: 35%;">Invoice No.</td>
              <td style="width: 5%;">:</td>
              <td class="bold">{{ $sale->id }}</td>
            </tr>
            <tr>
              <td class="bold">Dated</td>
              <td>:</td>
              <td>{{ $sale->created_at->format('d-m-Y') }}</td>
            </tr>
          </table>
        </td>
        <td style="width: 50%;">
          <table class="meta-sub-table" style="width: 100%;">
            <tr>
              <td class="bold" style="width: 35%;">Payment Mode</td>
              <td style="width: 5%;">:</td>
              <td>{{ $sale->payment_method }}</td>
            </tr>
            <tr>
              <td class="bold">Status</td>
              <td>:</td>
              <td class="bold">{{ strtoupper($sale->payment_status) }}</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- Billed To Section -->
    <table class="party-table border-bottom">
      <tr>
        <td class="border-right" style="width: 50%;">
          <div class="bold" style="color: #2e2c92; font-size: 9px; margin-bottom: 2px;">Billed To :</div>
          <div class="bold" style="font-size: 11px;">{{ $customer->name }}</div>
          <div style="color: #4b5563; font-size: 9px;">{{ $customer->address }}</div>
          <div style="color: #4b5563; font-size: 9px;">{{ $customer->city }}{{ $customer->district ? ', ' . $customer->district : '' }}{{ $customer->state ? ', ' . $customer->state : '' }}{{ $customer->pin_code ? ' - ' . $customer->pin_code : '' }}</div>
          @if(!empty($customer->phone))
            <div style="color: #4b5563; font-size: 9px;">Phone: {{ $customer->phone }}</div>
          @endif
        </td>
        <td style="width: 50%;">
          @if($hasPhysicalItems)
            <div class="bold" style="color: #2e2c92; font-size: 9px; margin-bottom: 2px;">Shipped To :</div>
            <div class="bold" style="font-size: 11px;">{{ $customer->shipping_name ?: $customer->name }}</div>
            <div style="color: #4b5563; font-size: 9px;">{{ $customer->shipping_address ?: $customer->address }}</div>
            <div style="color: #4b5563; font-size: 9px;">{{ $customer->shipping_city ?: $customer->city }}{{ ($customer->shipping_district ?: $customer->district) ? ', ' . ($customer->shipping_district ?: $customer->district) : '' }}{{ ($customer->shipping_state ?: $customer->state) ? ', ' . ($customer->shipping_state ?: $customer->state) : '' }}{{ ($customer->shipping_pin_code ?: $customer->pin_code) ? ' - ' . ($customer->shipping_pin_code ?: $customer->pin_code) : '' }}</div>
          @else
            <div style="color: #6b7280; font-style: italic; font-size: 9px; padding-top: 10px;">Service Invoice - Shipping not applicable</div>
          @endif
        </td>
      </tr>
    </table>

    <!-- Product Items Table -->
    <table class="items-table">
      <thead>
        <tr>
          <th class="border-right" style="width: 8%;">S.No.</th>
          <th class="border-right" style="width: 47%;">Description of Goods/Services</th>
          <th class="border-right" style="width: 10%;">Qty</th>
          <th class="border-right" style="width: 20%;">Rate</th>
          <th style="width: 15%;" class="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sale->saleItems as $index => $item)
        <tr class="item-row border-bottom">
          <td class="text-center border-right">{{ $index + 1 }}</td>
          <td class="border-right bold">
            {{ optional($item->product)->name ?? 'N/A' }}
            @if((!empty($item->width) && !empty($item->height)) || !empty($item->alternate_quantity))
              <div style="font-size: 8px; color: #555; margin-top: 1px; font-weight: normal; font-style: italic;">
                @if(!empty($item->width) && !empty($item->height))
                  Size: {{ (float)$item->width }} x {{ (float)$item->height }}
                @endif
                @if(!empty($item->alternate_quantity))
                  {{ (!empty($item->width) && !empty($item->height)) ? ' | ' : '' }}Alt Qty: {{ (float)$item->alternate_quantity }} {{ $item->alternate_unit_type ?: (optional($item->product)->alternate_unit_type ?? 'pcs') }}
                @endif
              </div>
            @endif
          </td>
          <td class="text-right border-right">{{ number_format($item->quantity, 2) }}</td>
          <td class="text-right border-right">{{ number_format($item->price, 2) }} / {{ $item->unit_type ?? 'Pcs.' }}</td>
          <td class="text-right bold">{{ number_format($item->base_price, 2) }}</td>
        </tr>
        @endforeach

        <!-- Totals -->
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="bold border-right text-right">Total Amount</td>
          <td class="text-right border-right bold">{{ number_format($totalQty, 2) }}</td>
          <td class="border-right">&nbsp;</td>
          <td class="text-right bold">{{ number_format($subtotal, 2) }}</td>
        </tr>

        @if($sale->discount > 0)
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="bold border-right text-right" colspan="3">Discount</td>
          <td class="text-right bold">-{{ number_format($sale->discount, 2) }}</td>
        </tr>
        @endif

        <tr class="total-row" style="background-color: #f3f4f6;">
          <td class="border-right">&nbsp;</td>
          <td class="bold border-right text-right" colspan="3">Grand Total</td>
          <td class="text-right bold">{{ $currencySymbol }}&nbsp;{{ number_format($roundedGrandTotal, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <table class="border-bottom">
      <tr>
        <td style="padding: 3px 5px; width: 100%;">
          <div style="font-size: 8px; margin-bottom: 1px;"><span class="bold">Amount in Words:</span> <span class="bold" style="text-transform: capitalize; color: #111;">{{ convertNumberToWords($roundedGrandTotal) }}</span></div>
        </td>
      </tr>
    </table>

    <!-- Bank Details Row -->
    @if($store && $store->bank_name && !($store->hide_bank_details ?? false))
    <div class="border-bottom bank-details-box" style="padding: 4px 6px;">
      <table style="width: 100%;">
        <tr>
          <td style="padding: 0; width: 15%;" class="bold">Bank Details :</td>
          <td style="padding: 0; width: 85%;">
            BANK : <span class="bold">{{ $store->bank_name }}</span>,
            BRANCH : <span class="bold">{{ $store->branch_name ?? 'N/A' }}</span>,
            A/C NO. : <span class="bold">{{ $store->account_number }}</span>,
            IFSC : <span class="bold">{{ $store->ifsc_code }}</span>
          </td>
        </tr>
      </table>
    </div>
    @endif

    <!-- Terms & conditions and signatory signature -->
    <table class="footer-table">
      <tr>
        <td class="border-right" style="width: 50%; padding: 4px 6px;">
          <div class="bold border-bottom" style="padding-bottom: 1px; margin-bottom: 2px;">Terms & Conditions :</div>
          <div style="font-size: 8px; color: #333;">
            E. & O.E.
            <ol class="terms-list">
              <li>{{ $hasPhysicalItems ? 'Goods once sold will not be taken back.' : 'Services once rendered are not refundable.' }}</li>
            </ol>
          </div>
        </td>
        <td style="width: 50%; vertical-align: top; padding: 4px 6px;">
          <div class="text-center" style="font-size: 9px; margin-bottom: 20px;">Receiver's Signature</div>
          <div style="text-align: right; width: 100%;">
            <div style="font-size: 8px; margin-bottom: 1px;">for <span class="bold">{{ $store ? $store->name : 'Your Company' }}</span></div>
            <div class="bold" style="font-size: 9px; margin-top: 10px; padding-right: 5px;">Authorized Signatory</div>
          </div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
