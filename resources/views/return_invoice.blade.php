<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Credit Note - Return Invoice</title>
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

  $store = $return->customer && $return->customer->user ? $return->customer->user : null;
  $storeGst = $store && !empty($store->gstin) ? trim($store->gstin) : '';
  $custGst = $return->customer && !empty($return->customer->gst_number) ? trim($return->customer->gst_number) : '';
  
  $storeStateName = $store && !empty($store->state) ? trim($store->state) : '';
  $custStateName = $return->customer && !empty($return->customer->state) ? trim($return->customer->state) : '';

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
  }

  $totalQty = 0;
  foreach ($return->items as $item) {
      $totalQty += $item->quantity;
  }

  $grandTotal = $return->refund_amount + $return->gst_refund_amount;
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
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">CREDIT NOTE / SALE RETURN</div>
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
        Credit Note Copy
      </td>
    </tr>
  </table>

  <!-- Meta Info Table -->
  <table class="border-bottom meta-table">
    <tr>
      <td class="border-right">
        <table class="meta-sub-table">
          <tr>
            <td class="bold" style="width: 35%;">Credit Note No.</td>
            <td style="width: 5%;">:</td>
            <td>{{ $return->return_no }}</td>
          </tr>
          <tr>
            <td class="bold">Return Date</td>
            <td>:</td>
            <td>{{ \Carbon\Carbon::parse($return->return_date)->format('d-m-Y') }}</td>
          </tr>
          <tr>
            <td class="bold">Original Invoice ID</td>
            <td>:</td>
            <td>{{ $return->items->pluck('sale_id')->filter()->unique()->map(fn($id) => "#{$id}")->implode(', ') ?: 'N/A' }}</td>
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
            <td class="bold">Refund Method</td>
            <td>:</td>
            <td>{{ strtoupper($return->refund_method) }}</td>
          </tr>
          <tr>
            <td class="bold">Station</td>
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
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">From (Store) :</div>
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
        <div class="bold border-bottom" style="margin-bottom: 4px; padding-bottom: 2px;">Refund To (Customer) :</div>
        <div class="bold">{{ $return->customer->name ?? 'N/A' }}</div>
        <div>{{ $return->customer->address ?? 'N/A' }}</div>
        @if($return->customer && $return->customer->phone)
          <div>Phone: {{ $return->customer->phone }}</div>
        @endif
        @if($return->customer && $return->customer->gst_number)
          <div class="bold" style="margin-top: 4px;">GSTIN / UIN : {{ $return->customer->gst_number }}</div>
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
        <th style="width: 10%;">Amount(₹)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($return->items as $index => $item)
      <tr class="item-row">
        <td class="text-center border-right">{{ $index + 1 }}.</td>
        <td class="border-right">{{ optional($item->product)->name ?? 'N/A' }}</td>
        <td class="text-center border-right">{{ optional($item->product)->hsn_code ?? 'N/A' }}</td>
        <td class="text-right border-right">{{ number_format($item->quantity, 2) }}</td>
        <td class="text-right border-right">{{ number_format($item->price, 2) }} / {{ $item->unit_type ?? 'Pcs.' }}</td>
        <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }}</td>
      </tr>
      @endforeach

      <!-- Space filler row to push totals down -->
      @for ($i = count($return->items); $i < 6; $i++)
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
        <td class="bold border-right text-right" colspan="2">Subtotal Refund</td>
        <td class="text-right border-right">&nbsp;</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">{{ number_format($return->refund_amount, 2) }}</td>
      </tr>

      <!-- GST Refund row -->
      @if($return->gst_refund_amount > 0)
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="border-right text-right bold" colspan="2">GST Refunded</td>
          <td class="border-right">&nbsp;</td>
          <td class="border-right">&nbsp;</td>
          <td class="text-right">{{ number_format($return->gst_refund_amount, 2) }}</td>
        </tr>
      @endif

      <!-- Grand Total row -->
      <tr class="total-row" style="background-color: #f3f4f6;">
        <td class="border-right">&nbsp;</td>
        <td class="bold border-right text-right" colspan="2">Total Refunded</td>
        <td class="text-right border-right bold">{{ number_format($totalQty, 2) }}</td>
        <td class="border-right">&nbsp;</td>
        <td class="text-right bold">₹ {{ number_format($grandTotal, 2) }}</td>
      </tr>
    </tbody>
  </table>

  <!-- Tax Rate Summary Table & Rupees in Words Box -->
  <table class="border-bottom">
    <tr>
      <td class="border-right" style="width: 50%; padding: 8px;">
        @if($return->reason)
          <div style="font-size: 11px;">
            <span class="bold">Reason for Return:</span><br>
            {{ $return->reason }}
          </div>
        @else
          &nbsp;
        @endif
      </td>
      <td style="width: 50%; padding: 8px; vertical-align: middle;">
        <div style="font-size: 11px; margin-bottom: 4px;"><span class="bold">Rupees in Words:</span></div>
        <div class="bold" style="font-size: 12px; text-transform: capitalize; color: #111;">
          {{ convertNumberToWords($grandTotal) }}
        </div>
      </td>
    </tr>
  </table>

  <!-- Bank Details Row -->
  @if(!($store->hide_bank_details ?? false))
  <div class="border-bottom bank-details-box">
    <table style="width: 100%;">
      <tr>
        <td style="padding: 0; width: 10%;" class="bold">Bank Details :</td>
        <td style="padding: 0; width: 90%;">
          @if($store && $store->bank_name)
            BANK : <span class="bold">{{ $store->bank_name }}</span>, 
            BRANCH : <span class="bold">{{ $store->branch_name ?? 'N/A' }}</span>, 
            A/C NO. : <span class="bold">{{ $store->account_number }}</span>, 
            IFSC : <span class="bold">{{ $store->ifsc_code }}</span>
          @else
            <span style="color: #6b7280; font-style: italic;">Bank details not configured in Store Profile settings.</span>
          @endif
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
            <li>This document is a credit transaction record for billing adjustment.</li>
            <li>Credit notes reflect sale return adjustments to customer accounts.</li>
          </ol>
        </div>
      </td>
      <td style="width: 50%; vertical-align: top; padding: 8px;">
        <div class="text-center" style="font-size: 10px; margin-bottom: 40px;">Customer's Signature</div>
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
