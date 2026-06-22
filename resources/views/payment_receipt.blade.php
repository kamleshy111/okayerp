<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Receipt</title>
  <style>
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #000; line-height: 1.5; }
    .invoice-container { width: 100%; border: 1px solid #000; padding: 0; box-sizing: border-box; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 8px; vertical-align: top; }
    .border-bottom { border-bottom: 1px solid #000; }
    .border-right { border-right: 1px solid #000; }
    .border-top { border-top: 1px solid #000; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bold { font-weight: bold; }
    .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
    .receipt-title { font-size: 16px; font-weight: bold; text-align: center; background-color: #f0f0f0; padding: 5px; border-bottom: 1px solid #000; border-top: 1px solid #000; }
  </style>
</head>
<body>
  <div class="invoice-container">
    <table>
      <tr>
        <td class="text-center">
            @php
                $store = \Illuminate\Support\Facades\Auth::user();
            @endphp
            <div class="company-name">{{ $store->name ?? 'COMPANY NAME' }}</div>
            <div>{{ $store->address ?? '' }}</div>
            @if(!empty($store->gstin))
              <div>GSTIN: {{ $store->gstin }}</div>
            @endif
            @if(!empty($store->phone))
              <div>Phone: {{ $store->phone }}</div>
            @endif
        </td>
      </tr>
    </table>

    <div class="receipt-title">PAYMENT RECEIPT</div>

    <table>
      <tr>
        <td width="50%" class="border-right border-bottom">
          <div class="bold">Received From:</div>
          <div>{{ $payment->customer_name }}</div>
          @if(!empty($payment->phone))
          <div>Phone: {{ $payment->phone }}</div>
          @endif
          @if(!empty($payment->email))
          <div>Email: {{ $payment->email }}</div>
          @endif
        </td>
        <td width="50%" class="border-bottom">
          <table style="width: 100%; padding:0;">
            <tr>
              <td class="bold" style="padding: 2px;">Receipt No:</td>
              <td style="padding: 2px;">{{ $payment->is_return ? 'RET-' : 'PAY-' }}{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
              <td class="bold" style="padding: 2px;">Receipt Date:</td>
              <td style="padding: 2px;">{{ date('d-M-y', strtotime($payment->created_at)) }}</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <table>
      <tr>
        <th class="border-right border-bottom text-center">Description</th>
        <th class="border-right border-bottom text-center">Payment Date</th>
        <th class="border-right border-bottom text-center">Payment Method</th>
        <th class="border-bottom text-right">Amount (₹)</th>
      </tr>
      @foreach($payment->payment_history as $hist)
      <tr style="{{ $hist['id'] == $payment->id ? 'background-color: #f2f5fc; font-weight: bold;' : '' }}">
        <td class="border-right border-bottom" style="padding: 8px;">
            <span class="bold">{{ $hist['reason'] }}</span>
            @if(!empty($hist['note']))
            <br>
            Note: {{ $hist['note'] }}
            @endif
        </td>
        <td class="border-right border-bottom text-center" style="padding: 8px; vertical-align: middle;">
            {{ date('d-M-y', strtotime($hist['payment_date'])) }}
        </td>
        <td class="border-right border-bottom text-center" style="padding: 8px; vertical-align: middle;">
            {{ $hist['payment_method'] }}
        </td>
        <td class="text-right bold border-bottom" style="padding: 8px; vertical-align: middle;">
            {{ number_format($hist['amount'], 2) }}
        </td>
      </tr>
      @endforeach
      <tr>
        <td class="border-right border-bottom bold text-right" style="border-top: 1px solid #000;" colspan="3">Total Amount Received</td>
        <td class="border-bottom bold text-right" style="border-top: 1px solid #000;">₹{{ number_format($payment->amount, 2) }}</td>
      </tr>
      @if(isset($payment->sale_id) && $payment->sale_id && isset($payment->sale_grand_total))
      <tr>
        <td class="border-right border-bottom bold text-right" colspan="3">Grand Total</td>
        <td class="border-bottom text-right">₹{{ number_format($payment->sale_grand_total, 2) }}</td>
      </tr>
      <tr>
        <td class="border-right border-bottom bold text-right" colspan="3">Paid</td>
        <td class="border-bottom text-right" style="color: green;">₹{{ number_format($payment->sale_total_paid, 2) }}</td>
      </tr>
      <tr>
        <td class="border-right border-bottom bold text-right" colspan="3">Balance Due</td>
        <td class="border-bottom text-right bold" style="color: {{ $payment->sale_remaining > 0 ? 'red' : 'green' }};">
            ₹{{ number_format($payment->sale_remaining, 2) }}
        </td>
      </tr>
      @endif
    </table>

    <table>
      <tr>
        <td style="padding: 20px;">
          <div><span class="bold">Amount in Words:</span> <br>
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
        <td class="text-center" style="padding: 20px; width: 30%;">
          <br><br><br>
          <div class="border-top">Authorized Signatory</div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
