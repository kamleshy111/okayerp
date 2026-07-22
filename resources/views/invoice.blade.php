<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tax Invoice</title>
  <style>
    body {
      margin: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 11px;
      color: #000;
      line-height: 1.3;
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
      padding: 5px;
      vertical-align: top;
    }

    .border-bottom {
      border-bottom: 1px solid #2e2c92;
    }

    .border-right {
      border-right: 1px solid #2e2c92;
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
      padding: 8px;
    }

    .logo-container {
      width: 25%;
      text-align: left;
      vertical-align: middle;
    }

    .company-details {
      width: 55%;
      text-align: center;
    }

    .company-name {
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 2px;
      text-transform: uppercase;
      color: #2e2c92;
    }

    .invoice-type {
      width: 20%;
      text-align: right;
      font-size: 10px;
    }

    /* Meta Info Grid */
    .meta-table td {
      width: 50%;
      padding: 0;
    }

    .meta-sub-table td {
      padding: 4px 6px;
      border-bottom: 1px solid #ddd;
    }

    .meta-sub-table tr:last-child td {
      border-bottom: none;
    }

    /* Billed / Shipped To styling */
    .party-table td {
      width: 50%;
      padding: 6px 8px;
    }

    /* Items Table */
    .items-table th {
      background-color: #e0e7ff;
      color: #2e2c92;
      border-bottom: 1px solid #2e2c92;
      font-weight: bold;
      text-align: center;
      padding: 6px 4px;
    }

    .items-table td {
      border-right: 1px solid #2e2c92;
      padding: 4px 6px;
    }

    .items-table td:last-child {
      border-right: none;
    }

    .items-table tr.item-row td {
      min-height: 25px;
    }

    /* Totals rows in items table */
    .total-row td {
      border-top: 1px solid #2e2c92;
      border-bottom: 1px solid #2e2c92;
      padding: 6px;
    }

    /* Tax Summary Grid */
    .tax-summary-table th {
      background-color: #e0e7ff;
      color: #2e2c92;
      border: 1px solid #2e2c92;
      font-weight: bold;
      text-align: center;
      padding: 4px;
    }

    .tax-summary-table td {
      border: 1px solid #2e2c92;
      padding: 4px 6px;
    }

    /* Bank Details row */
    .bank-details-box {
      padding: 6px 8px;
      background-color: #f9fafb;
    }

    /* Footer styling */
    .footer-table td {
      width: 50%;
      padding: 8px;
    }

    .terms-list {
      margin: 4px 0 0 0;
      padding-left: 15px;
    }
  </style>
</head>
<body>

@php
  // Indian number to words helper logic
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

  // State Codes for GST
  $stateCodes = config('gst_states');


  $store = $sale->customer && $sale->customer->user ? $sale->customer->user : null;
  $storeGst = $store && !empty($store->gstin) ? trim($store->gstin) : '';
  $custGst = $sale->customer && !empty($sale->customer->gst_number) ? trim($sale->customer->gst_number) : '';

  $storeStateName = $store && !empty($store->state) ? trim($store->state) : '';
  $custStateName = $sale->customer && !empty($sale->customer->state) ? trim($sale->customer->state) : '';

  $isInterstate = true;
  if ($storeStateName && $custStateName) {
      if (strtolower($storeStateName) === strtolower($custStateName)) {
          $isInterstate = false;
      }
  } elseif ($storeGst && $custGst) {
      $storeState = substr($storeGst, 0, 2);
      $custState = substr($custGst, 0, 2);
      if ($storeState === $custState) {
          $isInterstate = false;
      }
  } else {
      $hasCgst = false;
      foreach ($sale->saleItems as $item) {
          if ($item->cgst > 0) { $hasCgst = true; break; }
      }
      $isInterstate = !$hasCgst;
  }

  $posState = 'N/A';
  if ($custGst && strlen($custGst) >= 2) {
      $code = substr($custGst, 0, 2);
      if (isset($stateCodes[$code])) {
          $posState = $stateCodes[$code] . " ({$code})";
      }
  }

  $hasPhysicalItems = $sale->saleItems->contains(function ($item) {
      return !$item->product || $item->product->type !== 'service';
  });

  // Group items by GST rate for calculation
  $taxGroups = [];
  $totalQty = 0;
  $subtotal = 0;
  foreach ($sale->saleItems as $item) {
      $totalQty += $item->quantity;
      $subtotal += $item->base_price;

      $gstRate = ($item->cgst + $item->sgst);
      $key = number_format($gstRate, 2);

      if (!isset($taxGroups[$key])) {
          $taxGroups[$key] = [
              'rate' => $gstRate,
              'cgst_rate' => $item->cgst,
              'sgst_rate' => $item->sgst,
              'taxable_amount' => 0,
              'cgst_amount' => 0,
              'sgst_amount' => 0,
              'total_tax' => 0
          ];
      }

      $taxable = $item->base_price;
      $taxGroups[$key]['taxable_amount'] += $taxable;

      $cgstVal = $taxable * ($item->cgst / 100);
      $sgstVal = $taxable * ($item->sgst / 100);

      $taxGroups[$key]['cgst_amount'] += $cgstVal;
      $taxGroups[$key]['sgst_amount'] += $sgstVal;
      $taxGroups[$key]['total_tax'] += ($cgstVal + $sgstVal);
  }

  // Calculate Round Off
  $calculatedGrandTotal = $subtotal + $sale->gst_amount - $sale->discount;
  $roundedGrandTotal = $calculatedGrandTotal;
  $roundOff = 0.0;
@endphp

<div class="invoice-container">

  <!-- Header Table -->
  <table class="border-bottom header-table">
    <tr>
      <td class="logo-container">
        @if($store && $store->profile_photo && file_exists(storage_path('app/public/' . $store->profile_photo)))
          <img src="{{ storage_path('app/public/' . $store->profile_photo) }}" style="height: 55px; width: auto;">
        @elseif(file_exists(public_path('images/logo.png')))
          <img src="{{ public_path('images/logo.png') }}" style="height: 55px; width: auto;">
        @else
          <span style="font-size: 14px; font-weight: bold; color: #2e2c92;">{{ $store ? $store->name : 'OKAY ERP' }}</span>
        @endif
      </td>
      <td class="company-details">
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">
          @if ($isExport || ($sale->gst_amount ?? 0) <= 0)
            {{ !empty($store->invoice_title_without_gst) ? $store->invoice_title_without_gst : 'INVOICE' }}
          @else
            {{ !empty($store->invoice_title_with_gst) ? $store->invoice_title_with_gst : 'TAX INVOICE' }}
          @endif
        </div>
        <div class="company-name">{{ $store ? $store->name : 'Your Store Name' }}</div>
        <div>{{ $store ? $store->address : 'Store Address' }}</div>
        @if($store && $store->gstin)
          <div class="bold">GSTIN : {{ $store->gstin }}</div>
        @endif
        @if($store && $store->phone)
          <div>Tel : {{ $store->phone }}</div>
        @endif
      </td>
      <td class="invoice-type text-right bold">
        Original Copy
      </td>
    </tr>
  </table>

  <!-- Meta Info Table -->
  <table class="border-bottom meta-table">
    <tr>
      <td class="{{ $hasPhysicalItems ? 'border-right' : '' }}" style="width: {{ $hasPhysicalItems ? '50%' : '100%' }};">
        <table class="meta-sub-table" style="width: 100%;">
          <tr>
            <td class="bold" style="width: 35%;">Invoice No.</td>
            <td style="width: 5%;">:</td>
            <td>{{ $sale->id }}/2026-27</td>
          </tr>
          <tr>
            <td class="bold">Dated</td>
            <td>:</td>
            <td>{{ $sale->created_at->format('d-m-Y') }}</td>
          </tr>
          @if($hasPhysicalItems)
          <tr>
            <td class="bold">Place of Supply</td>
            <td>:</td>
            <td>{{ $posState }}</td>
          </tr>
          <tr>
            <td class="bold">Reverse Charge</td>
            <td>:</td>
            <td>N</td>
          </tr>
          @endif
        </table>
      </td>
      @if($hasPhysicalItems)
      <td style="width: 50%;">
        <table class="meta-sub-table" style="width: 100%;">
          <tr>
            <td class="bold" style="width: 35%;">Transport</td>
            <td style="width: 5%;">:</td>
            <td>N/A</td>
          </tr>
          <tr>
            <td class="bold">Vehicle No.</td>
            <td>:</td>
            <td>N/A</td>
          </tr>
          <tr>
            <td class="bold">Station</td>
            <td>:</td>
            <td>N/A</td>
          </tr>
          <tr>
            <td class="bold">E-Way Bill No.</td>
            <td>:</td>
            <td>N/A</td>
          </tr>
        </table>
      </td>
      @endif
    </tr>
  </table>

  <!-- Billed / Shipped Party Table -->
  <table class="border-bottom party-table">
    <tr>
      <td class="border-right">
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">Billed From :</div>
        <div class="bold">{{ $store ? $store->name : 'N/A' }}</div>
        <div>{{ $store ? $store->address : 'N/A' }}</div>
        @if($store && $store->phone)
          <div>Phone: {{ $store->phone }}</div>
        @endif
        @if($store && $store->gstin)
          <div class="bold" style="margin-top: 4px;">GSTIN : {{ $store->gstin }}</div>
        @endif
      </td>
      <td>
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">Billed To :</div>
        <div class="bold">{{ $sale->customer->name ?? 'N/A' }}</div>
        <div>{{ $sale->customer->address ?? 'N/A' }}</div>
        @if($sale->customer && $sale->customer->phone)
          <div>Phone: {{ $sale->customer->phone }}</div>
        @endif
        @if($sale->customer && $sale->customer->gst_number)
          <div class="bold" style="margin-top: 4px;">GSTIN / UIN : {{ $sale->customer->gst_number }}</div>
        @endif
      </td>
    </tr>
  </table>

  <!-- Items Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th class="border-right" style="width: 5%;">S.N.</th>
        <th class="border-right" style="width: 45%;">Description of Goods</th>
        <th class="border-right" style="width: 12%;">HSN/SAC</th>
        <th class="border-right" style="width: 10%;">Qty.</th>
        <th class="border-right" style="width: 18%;">Price</th>
        <th style="width: 10%;">Amount({{ $isExport ? $currencyCode : '₹' }})</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($sale->saleItems as $index => $item)
      <tr class="item-row">
        <td class="text-center border-right">{{ $index + 1 }}.</td>
        <td class="border-right">
          {{ optional($item->product)->name ?? 'N/A' }}
          @if((!empty($item->width) && !empty($item->height)) || !empty($item->alternate_quantity))
            <div style="font-size: 9px; color: #555; margin-top: 2px; font-weight: normal; font-style: italic;">
              @if(!empty($item->width) && !empty($item->height))
                Size: {{ (float)$item->width }} x {{ (float)$item->height }}
              @endif
              @if(!empty($item->alternate_quantity))
                {{ (!empty($item->width) && !empty($item->height)) ? ' | ' : '' }}Alt Qty: {{ (float)$item->alternate_quantity }} {{ $item->alternate_unit_type ?: (optional($item->product)->alternate_unit_type ?? 'pcs') }}
              @endif
            </div>
          @endif
        </td>
        <td class="text-center border-right">{{ optional($item->product)->hsn_code ?? 'N/A' }}</td>
        <td class="text-right border-right">{{ number_format($item->quantity, 2) }}</td>
        <td class="text-right border-right">
          @if($isExport)
            {{ number_format($item->price / $exchangeRate, 2) }}
          @else
            {{ number_format($item->price, 2) }}
          @endif
          / {{ $item->unit_type ?? 'Pcs.' }}
        </td>
        <td class="text-right">
          @if($isExport)
            {{ number_format($item->base_price / $exchangeRate, 2) }}
          @else
            {{ number_format($item->base_price, 2) }}
          @endif
        </td>
      </tr>
      @endforeach

      <!-- Space filler row to push totals down -->
      @for ($i = count($sale->saleItems); $i < 6; $i++)
      <tr class="item-row">
        <td class="border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      @endfor

      <!-- Subtotal exclusive of Tax -->
      <tr class="total-row">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Total Amount</td>
        <td class="text-right border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">
          @if($isExport)
            {{ number_format($subtotal / $exchangeRate, 2) }}
          @else
            {{ number_format($subtotal, 2) }}
          @endif
        </td>
      </tr>

      <!-- Total GST -->
      @if(!$isExport && $sale->gst_amount > 0)
      <tr class="total-row">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Total GST</td>
        <td class="text-right border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">{{ number_format($sale->gst_amount, 2) }}</td>
      </tr>
      @endif

      <!-- Discount -->
      @if($sale->discount > 0)
      <tr class="total-row">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Discount</td>
        <td class="text-right border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">
          @if($isExport)
            -{{ number_format($sale->discount / $exchangeRate, 2) }}
          @else
            -{{ number_format($sale->discount, 2) }}
          @endif
        </td>
      </tr>
      @endif

      <!-- Grand Total row -->
      <tr class="total-row" style="background-color: #f3f4f6;">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Grand Total</td>
        <td class="text-right border-right bold">{{ number_format($totalQty, 2) }}</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">
          {{ $currencySymbol }}&nbsp;@if($isExport){{ number_format($roundedGrandTotal / $exchangeRate, 2) }}@else{{ number_format($roundedGrandTotal, 2) }}@endif
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Tax Rate Summary Table & Rupees in Words Box -->
  <table class="border-bottom">
    <tr>
      @if(!$isExport && ($sale->gst_amount ?? 0) > 0)
      <td class="border-right" style="width: 50%; padding: 8px;">
        <div class="bold" style="margin-bottom: 6px;">Tax Summary :</div>
        <table class="tax-summary-table">
          <thead>
            <tr>
              <th>Tax Rate</th>
              <th>Taxable Amt.</th>
              @if($isInterstate)
                <th>IGST %</th>
              @else
                <th>CGST %</th>
                <th>SGST %</th>
              @endif
              <th>Total Tax</th>
            </tr>
          </thead>
          <tbody>
            @foreach($taxGroups as $rateKey => $group)
            <tr>
              <td class="text-center">{{ (float)$group['rate'] }}%</td>
              <td class="text-right">{{ number_format($group['taxable_amount'], 2) }}</td>
              @if($isInterstate)
                <td class="text-right">{{ (float)$group['rate'] }}%</td>
              @else
                <td class="text-right">{{ (float)$group['cgst_rate'] }}%</td>
                <td class="text-right">{{ (float)$group['sgst_rate'] }}%</td>
              @endif
              <td class="text-right">{{ number_format($group['total_tax'], 2) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </td>
      @endif
      <td style="width: {{ ($isExport || ($sale->gst_amount ?? 0) <= 0) ? '100%' : '50%' }}; padding: 8px; vertical-align: middle;">
        <div style="font-size: 11px; margin-bottom: 4px;"><span class="bold">Amount in Words:</span></div>
        <div class="bold" style="font-size: 12px; text-transform: capitalize; color: #111;">
          @if($isExport)
            {{ convertNumberToWords($roundedGrandTotal / $exchangeRate, $currencyCode) }}
          @else
            {{ convertNumberToWords($roundedGrandTotal) }}
          @endif
        </div>
      </td>
    </tr>
  </table>

  <!-- Bank Details Row -->
  @if($store && $store->bank_name && !($store->hide_bank_details ?? false))
  <div class="border-bottom bank-details-box">
    <table style="width: 100%;">
      <tr>
        <td style="padding: 0; width: 10%;" class="bold">Bank Details :</td>
        <td style="padding: 0; width: 90%;">
          BANK : <span class="bold">{{ $store->bank_name }}</span>,
          BRANCH : <span class="bold">{{ $store->branch_name ?? 'N/A' }}</span>,
          A/C NO. : <span class="bold">{{ $store->account_number }}</span>,
          IFSC : <span class="bold">{{ $store->ifsc_code }}</span>
        </td>
      </tr>
    </table>
  </div>
  @endif

  <!-- Footer Section -->
  <table class="footer-table">
    <tr>
      <td class="border-right" style="width: 50%;">
        <div class="bold border-bottom" style="padding-bottom: 2px; margin-bottom: 4px;">Terms & Conditions :</div>
        <div style="font-size: 9px; color: #333;">
          E. & O.E.
          <ol class="terms-list">
            <li>{{ $hasPhysicalItems ? 'Goods once sold will not be taken back.' : 'Services once rendered are not refundable.' }}</li>
          </ol>
        </div>
      </td>
      <td style="width: 50%; vertical-align: top; padding: 8px;">
        <div class="text-center" style="font-size: 10px; margin-bottom: 40px;">Receiver's Signature</div>
        <div style="text-align: right; width: 100%;">
          <div style="font-size: 9px; margin-bottom: 2px;">for <span class="bold">{{ $store ? $store->name : 'Your Company' }}</span></div>
          <div class="bold" style="font-size: 10px; margin-top: 25px; padding-right: 5px;">Authorized Signatory</div>
        </div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>
