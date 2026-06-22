<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Purchase Bill</title>
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
      border: 1px solid #000;
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
      border-bottom: 1px solid #000;
    }

    .border-right {
      border-right: 1px solid #000;
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
      background-color: #f3f4f6;
      border-bottom: 1px solid #000;
      font-weight: bold;
      text-align: center;
      padding: 6px 4px;
    }

    .items-table td {
      border-right: 1px solid #000;
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
      border-top: 1px solid #000;
      border-bottom: 1px solid #000;
      padding: 6px;
    }

    /* Tax Summary Grid */
    .tax-summary-table th {
      background-color: #f9fafb;
      border: 1px solid #000;
      font-weight: bold;
      text-align: center;
      padding: 4px;
    }

    .tax-summary-table td {
      border: 1px solid #000;
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
      function convertNumberToWords($number) {
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
          $Rupees = implode('', array_reverse($str));
          $paise = ($decimal > 0) ? "and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise ' : '';
          return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . 'Only';
      }
  }

  // State Codes for GST
  $stateCodes = [
      '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
      '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
      '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
      '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
      '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
      '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam',
      '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
      '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
      '27' => 'Maharashtra', '28' => 'Andhra Pradesh', '29' => 'Karnataka',
      '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala',
      '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar Islands',
      '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh'
  ];

  $store = $purchase->supplier && $purchase->supplier->user ? $purchase->supplier->user : null;
  $supplier = $purchase->supplier ?? null;
  
  $storeGst = $store && !empty($store->gstin) ? trim($store->gstin) : '';
  $supplierGst = $supplier && !empty($supplier->gstin) ? trim($supplier->gstin) : '';
  
  $isInterstate = true;
  if ($storeGst && $supplierGst) {
      $storeState = substr($storeGst, 0, 2);
      $supplierState = substr($supplierGst, 0, 2);
      if ($storeState === $supplierState) {
          $isInterstate = false;
      }
  } else {
      $hasCgst = false;
      foreach ($purchase->items as $item) {
          if ($item->cgst > 0) { $hasCgst = true; break; }
      }
      $isInterstate = !$hasCgst;
  }

  $posState = 'N/A';
  if ($storeGst && strlen($storeGst) >= 2) {
      $code = substr($storeGst, 0, 2);
      if (isset($stateCodes[$code])) {
          $posState = $stateCodes[$code] . " ({$code})";
      }
  }

  // Group items by GST rate for calculation
  $taxGroups = [];
  $totalQty = 0;
  $subtotal = 0;
  foreach ($purchase->items as $item) {
      $totalQty += $item->quantity;
      $subtotal += ($item->price * $item->quantity);
      
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
      
      $taxable = $item->price * $item->quantity;
      $taxGroups[$key]['taxable_amount'] += $taxable;
      
      $cgstVal = $taxable * ($item->cgst / 100);
      $sgstVal = $taxable * ($item->sgst / 100);
      
      $taxGroups[$key]['cgst_amount'] += $cgstVal;
      $taxGroups[$key]['sgst_amount'] += $sgstVal;
      $taxGroups[$key]['total_tax'] += ($cgstVal + $sgstVal);
  }

  // Calculate Round Off
  $calculatedGrandTotal = $subtotal + $purchase->gst_amount + ($purchase->transport_amount ?? 0);
  $roundedGrandTotal = round($calculatedGrandTotal);
  $roundOff = $roundedGrandTotal - $calculatedGrandTotal;
@endphp

<div class="invoice-container">
  
  <!-- Header Table -->
  <table class="border-bottom header-table">
    <tr>
      <td class="logo-container">
        @if($store && $store->profile_photo && file_exists(storage_path('app/public/' . $store->profile_photo)))
          <img src="{{ storage_path('app/public/' . $store->profile_photo) }}" style="max-height: 55px; max-width: 150px;">
        @elseif(file_exists(public_path('images/logo.png')))
          <img src="{{ public_path('images/logo.png') }}" style="max-height: 55px; max-width: 150px;">
        @else
          <span style="font-size: 14px; font-weight: bold; color: #2e2c92;">{{ $supplier ? $supplier->name : 'OKAY ERP' }}</span>
        @endif
      </td>
      <td class="company-details">
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">TAX INVOICE</div>
        <div class="company-name">{{ $supplier ? $supplier->name : 'Your Supplier Name' }}</div>
        <div>
          @if($supplier)
            @if($supplier->address)
              {{ $supplier->address }}<br>
            @endif
            @if($supplier->city || $supplier->district || $supplier->state)
              {{ implode(', ', array_filter([$supplier->city, $supplier->district, $supplier->state])) }}<br>
            @endif
            @if($supplier->country || $supplier->pin_code)
              {{ implode(', ', array_filter([$supplier->country, $supplier->pin_code])) }}
            @endif
          @else
            Supplier Address
          @endif
        </div>
        @if($supplierGst)
          <div class="bold">GSTIN : {{ $supplierGst }}</div>
        @endif
        @if($supplier && $supplier->phone)
          <div>Tel : {{ $supplier->phone }}</div>
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
      <td class="border-right">
        <table class="meta-sub-table">
          <tr>
            <td class="bold" style="width: 35%;">Invoice No.</td>
            <td style="width: 5%;">:</td>
            <td>
              @php
                $displayInvoiceNo = $purchase->invoice_no ?? $purchase->id;
                if ($displayInvoiceNo && preg_match('/^\d+$/', $displayInvoiceNo)) {
                    $date = \Carbon\Carbon::parse($purchase->purchase_date ?? $purchase->created_at);
                    $year = $date->year;
                    $month = $date->month;
                    $startYear = ($month >= 4) ? $year : $year - 1;
                    $endYear = $startYear + 1;
                    $displayInvoiceNo = $displayInvoiceNo . '/' . $startYear . '-' . substr($endYear, 2);
                }
              @endphp
              {{ $displayInvoiceNo }}
            </td>
          </tr>
          <tr>
            <td class="bold">Dated</td>
            <td>:</td>
            <td>{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d-m-Y') : $purchase->created_at->format('d-m-Y') }}</td>
          </tr>
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
        </table>
      </td>
      <td>
        <table class="meta-sub-table">
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
    </tr>
  </table>

  <!-- Billed / Shipped Party Table -->
  <table class="border-bottom party-table">
    <tr>
      <td class="border-right">
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">Billed to :</div>
        <div class="bold">{{ $store ? $store->name : 'N/A' }}</div>
        <div>{{ $store ? $store->address : 'N/A' }}</div>
        @if($store && $store->phone)
          <div>Phone: {{ $store->phone }}</div>
        @endif
        @if($storeGst)
          <div class="bold" style="margin-top: 4px;">GSTIN / UIN : {{ $storeGst }}</div>
        @endif
      </td>
      <td>
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">Shipped to :</div>
        <div class="bold">{{ $store ? $store->name : 'N/A' }}</div>
        <div>{{ $store ? $store->address : 'N/A' }}</div>
        @if($store && $store->phone)
          <div>Phone: {{ $store->phone }}</div>
        @endif
        @if($storeGst)
          <div class="bold" style="margin-top: 4px;">GSTIN / UIN : {{ $storeGst }}</div>
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
        <th class="border-right" style="width: 8%;">Unit</th>
        <th class="border-right" style="width: 10%;">Price</th>
        <th style="width: 10%;">Amount(₹)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($purchase->items as $index => $item)
      <tr class="item-row">
        <td class="text-center border-right">{{ $index + 1 }}.</td>
        <td class="border-right">{{ optional($item->product)->name ?? 'N/A' }}</td>
        <td class="text-center border-right">{{ optional($item->product)->hsn_code ?? 'N/A' }}</td>
        <td class="text-right border-right">{{ number_format($item->quantity, 2) }}</td>
        <td class="text-center border-right">{{ $item->unit_type ?? 'Pcs.' }}</td>
        <td class="text-right border-right">{{ number_format($item->price, 2) }}</td>
        <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }}</td>
      </tr>
      @endforeach

      <!-- Space filler row to push totals down -->
      @for ($i = count($purchase->items); $i < 6; $i++)
      <tr class="item-row">
        <td class="border-right">&nbsp;</td>
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
        <td class="bold border-right text-right" colspan="2">Total Taxable Value</td>
        <td class="text-right border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">{{ number_format($subtotal, 2) }}</td>
      </tr>

      <!-- CGST/SGST/IGST breakdown in items table -->
      @foreach($taxGroups as $gstRateKey => $group)
        @if($group['total_tax'] > 0)
          @if($isInterstate)
            <tr class="total-row">
              <td class="border-right">&nbsp;</td>
              <td class="border-right text-right bold" colspan="2">Add : IGST @ {{ number_format($group['rate'], 2) }}%</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="text-right">{{ number_format($group['total_tax'], 2) }}</td>
            </tr>
          @else
            <tr class="total-row">
              <td class="border-right">&nbsp;</td>
              <td class="border-right text-right bold" colspan="2">Add : CGST @ {{ number_format($group['cgst_rate'], 2) }}%</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="text-right">{{ number_format($group['cgst_amount'], 2) }}</td>
            </tr>
            <tr class="total-row">
              <td class="border-right">&nbsp;</td>
              <td class="border-right text-right bold" colspan="2">Add : SGST @ {{ number_format($group['sgst_rate'], 2) }}%</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="border-right">&nbsp;</td>
              <td class="text-right">{{ number_format($group['sgst_amount'], 2) }}</td>
            </tr>
          @endif
        @endif
      @endforeach

      <!-- Transport Charges row -->
      @if($purchase->transport_amount && (float)$purchase->transport_amount > 0)
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="border-right text-right bold" colspan="2">Add : Transport Charges</td>
          <td class="border-right">&nbsp;</td>
          <td class="border-right">&nbsp;</td>
          <td class="border-right">&nbsp;</td>
          <td class="text-right">{{ number_format($purchase->transport_amount, 2) }}</td>
        </tr>
      @endif

      <!-- Rounded off row -->
      @if($roundOff != 0)
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="border-right text-right bold" colspan="2">Add : Rounded Off ({{ $roundOff > 0 ? '+' : '' }})</td>
          <td class="border-right">&nbsp;</td>
          <td class="border-right">&nbsp;</td>
          <td class="border-right">&nbsp;</td>
          <td class="text-right">{{ number_format($roundOff, 2) }}</td>
        </tr>
      @endif

      <!-- Grand Total row -->
      <tr class="total-row" style="background-color: #f3f4f6;">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Grand Total</td>
        <td class="text-right border-right bold">{{ number_format($totalQty, 2) }}</td>
        <td class="text-center border-right bold">Pcs.</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">₹ {{ number_format($roundedGrandTotal, 2) }}</td>
      </tr>
    </tbody>
  </table>

  <!-- Tax Rate Summary Table & Rupees in Words Box -->
  <table class="border-bottom">
    <tr>
      <td class="border-right" style="width: 50%; padding: 8px;">
        <div class="bold" style="margin-bottom: 6px;">Tax Summary :</div>
        <table class="tax-summary-table">
          <thead>
            <tr>
              <th>Tax Rate</th>
              <th>Taxable Amt.</th>
              @if($isInterstate)
                <th>IGST Amt.</th>
              @else
                <th>CGST Amt.</th>
                <th>SGST Amt.</th>
              @endif
              <th>Total Tax</th>
            </tr>
          </thead>
          <tbody>
            @foreach($taxGroups as $rateKey => $group)
            <tr>
              <td class="text-center">{{ number_format($group['rate'], 0) }}%</td>
              <td class="text-right">{{ number_format($group['taxable_amount'], 2) }}</td>
              @if($isInterstate)
                <td class="text-right">{{ number_format($group['total_tax'], 2) }}</td>
              @else
                <td class="text-right">{{ number_format($group['cgst_amount'], 2) }}</td>
                <td class="text-right">{{ number_format($group['sgst_amount'], 2) }}</td>
              @endif
              <td class="text-right">{{ number_format($group['total_tax'], 2) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </td>
      <td style="width: 50%; padding: 8px; vertical-align: middle;">
        <div style="font-size: 11px; margin-bottom: 4px;"><span class="bold">Rupees in Words:</span></div>
        <div class="bold" style="font-size: 12px; text-transform: capitalize; color: #111;">
          {{ convertNumberToWords($roundedGrandTotal) }}
        </div>
      </td>
    </tr>
  </table>

  <!-- Bank Details Row -->
  <div class="border-bottom bank-details-box">
    <table style="width: 100%;">
      <tr>
        <td style="padding: 0; width: 10%;" class="bold">Bank Details :</td>
        <td style="padding: 0; width: 90%;">
          BANK : <span class="bold">N/A</span>, 
          BRANCH : <span class="bold">N/A</span>, 
          A/C NO. : <span class="bold">N/A</span>, 
          IFSC : <span class="bold">N/A</span>
        </td>
      </tr>
    </table>
  </div>

  <!-- Footer Section -->
  <table class="footer-table">
    <tr>
      <td class="border-right" style="width: 50%;">
        <div class="bold border-bottom" style="padding-bottom: 2px; margin-bottom: 4px;">Terms & Conditions :</div>
        <div style="font-size: 9px; color: #333;">
          E. & O.E.
          <ol class="terms-list">
            <li>Goods once sold will not be taken back.</li>
            <li>Interest @ 18% p.a. will be charged if the payment is not made within the due date.</li>
          </ol>
        </div>
      </td>
      <td style="width: 50%; position: relative; height: 90px;">
        <div style="width: 100%;">
          <div class="text-center" style="font-size: 10px; margin-bottom: 30px;">Receiver's Signature</div>
        </div>
        <div style="position: absolute; bottom: 8px; right: 8px; width: 100%; text-align: right;">
          <div style="font-size: 9px; margin-bottom: 2px;">for <span class="bold">{{ $supplier ? $supplier->name : 'Supplier' }}</span></div>
          <div class="bold" style="font-size: 10px; margin-top: 25px; padding-right: 5px;">Authorized Signatory</div>
        </div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>
