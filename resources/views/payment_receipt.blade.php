<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Receipt</title>
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
      padding: 6px 8px;
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

    .company-name {
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 2px;
      text-transform: uppercase;
      color: #2e2c92;
    }

    .receipt-title {
      font-size: 14px;
      font-weight: bold;
      text-align: center;
      background-color: #2e2c92;
      color: #ffffff;
      padding: 6px;
      border-bottom: 1px solid #2e2c92;
      border-top: 1px solid #2e2c92;
      letter-spacing: 1px;
    }

    /* Table headers styling */
    .receipt-table th {
      background-color: #e0e7ff;
      color: #2e2c92;
      border-bottom: 1px solid #2e2c92;
      font-weight: bold;
      text-align: center;
      padding: 6px;
    }

    .receipt-table td {
      border-bottom: 1px solid #cbd5e1;
      padding: 6px 8px;
    }

    .text-emerald {
      color: #16a34a;
    }

    .text-rose {
      color: #dc2626;
    }
  </style>
</head>
<body>

  <div class="invoice-container">
    <table>
      <tr>
        <td class="text-center" style="padding: 10px;">
            @php
                $store = \Illuminate\Support\Facades\Auth::user();
            @endphp
            <div class="company-name">{{ $store->name ?? 'OKAY ERP' }}</div>
            <div style="color: #4b5563;">{{ $store->address ?? '' }}</div>
            @if(!empty($store->gstin))
              <div class="bold" style="margin-top: 2px;">GSTIN: {{ $store->gstin }}</div>
            @endif
            @if(!empty($store->phone))
              <div style="color: #4b5563;">Phone: {{ $store->phone }}</div>
            @endif
        </td>
      </tr>
    </table>

    <div class="receipt-title">PAYMENT RECEIPT</div>

    <table>
      <tr>
        <td width="50%" class="border-right border-bottom" style="padding: 8px;">
          <div class="bold" style="color: #2e2c92; margin-bottom: 4px;">Received From :</div>
          <div class="bold" style="font-size: 12px;">{{ $payment->customer_name }}</div>
          @if(!empty($payment->phone))
            <div style="color: #4b5563;">Phone: {{ $payment->phone }}</div>
          @endif
          @if(!empty($payment->email))
            <div style="color: #4b5563;">Email: {{ $payment->email }}</div>
          @endif
        </td>
        <td width="50%" class="border-bottom" style="padding: 8px;">
          <table style="width: 100%; padding:0;">
            <tr>
              <td class="bold" style="padding: 2px; width: 40%; color: #2e2c92;">Receipt No.</td>
              <td style="padding: 2px; width: 5%;">:</td>
              <td style="padding: 2px;" class="bold">{{ $payment->is_return ? 'RET-' : 'PAY-' }}{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
              <td class="bold" style="padding: 2px; color: #2e2c92;">Receipt Date</td>
              <td>:</td>
              <td>{{ date('d-m-Y', strtotime($payment->created_at)) }}</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <table class="receipt-table">
      <thead>
        <tr>
          <th class="border-right" style="width: 40%;">Description</th>
          <th class="border-right" style="width: 20%;">Payment Date</th>
          <th class="border-right" style="width: 20%;">Payment Method</th>
          <th style="width: 20%;" class="text-right">Amount (₹)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($payment->payment_history as $hist)
        <tr style="{{ $hist['id'] == $payment->id ? 'background-color: #f2f5fc; font-weight: bold;' : '' }}">
          <td class="border-right">
              <span class="bold">{{ $hist['reason'] }}</span>
              @if(!empty($hist['note']))
                <div style="font-size: 9px; color: #4b5563; font-weight: normal; margin-top: 2px;">Note: {{ $hist['note'] }}</div>
              @endif
          </td>
          <td class="border-right text-center" style="vertical-align: middle;">
              {{ date('d-m-Y', strtotime($hist['payment_date'])) }}
          </td>
          <td class="border-right text-center" style="vertical-align: middle;">
              {{ $hist['payment_method'] }}
          </td>
          <td class="text-right bold" style="vertical-align: middle;">
              {{ number_format($hist['amount'], 2) }}
          </td>
        </tr>
        @endforeach
        
        <!-- Totals & Balances -->
        <tr>
          <td class="border-right bold text-right" style="border-top: 1px solid #2e2c92; border-bottom: none;" colspan="3">Total Amount Received</td>
          <td class="bold text-right" style="border-top: 1px solid #2e2c92; border-bottom: none; color: #16a34a;">₹{{ number_format($payment->amount, 2) }}</td>
        </tr>
        @if(isset($payment->sale_id) && $payment->sale_id && isset($payment->sale_grand_total))
          <tr>
            <td class="border-right bold text-right" style="border-bottom: none;" colspan="3">Sale Grand Total</td>
            <td class="text-right bold" style="border-bottom: none;">₹{{ number_format($payment->sale_grand_total, 2) }}</td>
          </tr>
          <tr>
            <td class="border-right bold text-right" style="border-bottom: none;" colspan="3">Total Paid</td>
            <td class="text-right bold" style="border-bottom: none; color: #16a34a;">₹{{ number_format($payment->sale_total_paid, 2) }}</td>
          </tr>
          <tr>
            <td class="border-right bold text-right" style="border-bottom: none;" colspan="3">Balance Due</td>
            <td class="text-right bold" style="border-bottom: none; color: {{ $payment->sale_remaining > 0 ? 'red' : 'green' }};">
                ₹{{ number_format($payment->sale_remaining, 2) }}
            </td>
          </tr>
        @endif
      </tbody>
    </table>

    <table>
      <tr class="border-top">
        <td style="padding: 10px; width: 70%; vertical-align: middle;">
          <div>
            <span class="bold" style="color: #2e2c92;">Amount in Words:</span><br>
            @php
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
              echo convertNumberToWords($payment->amount);
            @endphp
          </div>
        </td>
        <td class="text-center" style="padding: 10px; width: 30%;">
          <br><br>
          <div style="border-top: 1px solid #2e2c92; padding-top: 4px; font-weight: bold;">Authorized Signatory</div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
