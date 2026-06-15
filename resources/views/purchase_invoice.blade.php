<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Purchase Bill</title>
  <style>
    body {
      margin: 0;
      font-family: DejaVu Sans, sans-serif;
      font-size: 13px;
      color: #1f2937;
    }

    .invoice-box {
      width: 100%;
      padding: 20px;
      background: #fff;
    }

    .invoice-title h1 {
      margin: 0;
      font-size: 24px;
      color: #2e2c92;
    }

    .info-section {
      margin-bottom: 20px;
    }

    .info-left, .info-right {
      width: 48%;
      display: inline-block;
      vertical-align: top;
    }

    .bill-to {
      margin-bottom: 20px;
    }

    table.invoice-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    table.invoice-table th,
    table.invoice-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }

    table.invoice-table th {
      background-color: #f1f5f9;
      color: #1e1b4b;
      font-weight: bold;
    }

    .invoice-table-1 {
      color: #2e2c92;
      font-weight: bold;
      margin-bottom: 15px;    
    }

    .text-right {
      text-align: right;
    }

    .totals-table {
      width: 50%;
      max-width: 50%;
      float: right;
      border-collapse: collapse;
    }

    .totals-table td {
      padding: 6px;
    }

    .totals-table tr:nth-child(4) td {
      font-weight: bold;
      color: #15803d;
    }

    .payment-status-row {
      background-color: #2e2c92;
      color: white;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="invoice-box">
    <div class="invoice-header">
      <table width="100%" style="border-bottom: 2px solid #2e2c92; padding-bottom: 10px; margin-bottom: 20px;">
        <tr>
          <td style="width: 50%;">
            @if($purchase->supplier && $purchase->supplier->user && $purchase->supplier->user->profile_photo && file_exists(storage_path('app/public/' . $purchase->supplier->user->profile_photo)))
              <img src="{{ storage_path('app/public/' . $purchase->supplier->user->profile_photo) }}" style="max-height: 60px; max-width: 180px;">
            @elseif(file_exists(public_path('images/logo.png')))
              <img src="{{ public_path('images/logo.png') }}" style="max-height: 60px; max-width: 180px;">
            @else
              <span style="font-size: 20px; font-weight: bold; color: #2e2c92; text-transform: uppercase; letter-spacing: 1px;">
                {{ $purchase->supplier && $purchase->supplier->user ? $purchase->supplier->user->name : 'OkayERP' }}
              </span>
            @endif
          </td>
          <td style="width: 50%; text-align: right;">
            <h1 style="margin: 0; font-size: 24px; color: #2e2c92;">Purchase Bill</h1>
            <div style="font-size: 12px; color: #6b7280;">{{ $purchase->supplier && $purchase->supplier->user ? $purchase->supplier->user->name : 'Your Company Name' }}</div>
          </td>
        </tr>
      </table>
    </div>

    <div class="info-section">
      <div class="info-left">
        <p><strong>Supplier / Seller:</strong><br>
          {{ $purchase->supplier->name ?? 'N/A' }}<br>
          @if($purchase->supplier && $purchase->supplier->address)
            {!! nl2br(e($purchase->supplier->address)) !!}<br>
          @endif
          @if($purchase->supplier && $purchase->supplier->phone)
            Phone: {{ $purchase->supplier->phone }}<br>
          @endif
          @if($purchase->supplier && $purchase->supplier->email)
            Email: {{ $purchase->supplier->email }}
          @endif
        </p>
      </div>
      <div class="info-right text-right">
        <p>
          <strong>Date:</strong> 
          @if($purchase->purchase_date)
            {{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d.m.Y') }}
          @else
            {{ $purchase->created_at->format('d.m.Y') }}
          @endif
          <br>
          <strong>Purchase ID #:</strong> {{ $purchase->id }}
          @if($purchase->invoice_no)
            <br><strong>Supplier Invoice #:</strong> {{ $purchase->invoice_no }}
          @endif
        </p>
      </div>
    </div>

    <div class="bill-to">
      <strong class="invoice-table-1">Ship To / Buyer:</strong><br>
      @if($purchase->supplier && $purchase->supplier->user)
        {{ $purchase->supplier->user->name }}<br>
        @if($purchase->supplier->user->address)
          {{ $purchase->supplier->user->address }}<br>
        @endif
        @if($purchase->supplier->user->phone)
          Phone: {{ $purchase->supplier->user->phone }}<br>
        @endif
        @if($purchase->supplier->user->email)
          Email: {{ $purchase->supplier->user->email }}
        @endif
      @else
        Your Company Name<br>
        Phone: N/A<br>
        Email: N/A<br>
        Address: N/A
      @endif
    </div>

    <table class="invoice-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Unit</th>
          <th>Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Base Amount</th>
          <th class="text-right">Total</th>
          @if($purchase->accepted)
            <th class="text-right">GST %</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach ($purchase->items as $item)
          <tr>
            <td>{{ optional($item->product)->name ?? 'N/A' }}</td>
            <td>{{ $item->unit_type }}</td>
            <td>{{ $item->quantity }}</td>
            <td class="text-right">{{ number_format($item->price, 2) }}</td>
            <td class="text-right">{{ number_format($item->base_price, 2) }}</td>
            <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }}</td>
            @if($purchase->accepted)
              <td class="text-right">{{ number_format($item->sgst + $item->cgst, 2) }}%</td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>

    <table class="totals-table">
      <tr>
        <td>Total Amount</td>
        <td class="text-right">{{ number_format($purchase->total_amount, 2) }}</td>
      </tr>
      <tr>
        <td>GST Amount</td>
        <td class="text-right">{{ number_format($purchase->gst_amount, 2) }}</td>
      </tr>
      @if($purchase->transport_amount && (float)$purchase->transport_amount > 0)
        <tr>
          <td>Transport Amount</td>
          <td class="text-right">{{ number_format($purchase->transport_amount, 2) }}</td>
        </tr>
      @endif
      <tr>
        <td>Grand Total</td>
        <td class="text-right">{{ number_format($purchase->grand_total, 2) }}</td>
      </tr>
      <tr>
        <td>Paid</td>
        <td class="text-right">{{ number_format($purchase->paid, 2) }}</td>
      </tr>
      @if(isset($allocatedPayment) && $allocatedPayment > 0)
        <tr>
          <td>Advance Applied</td>
          <td class="text-right">{{ number_format($allocatedPayment, 2) }}</td>
        </tr>
      @endif
      <tr>
        <td>Balance Due</td>
        <td class="text-right">{{ number_format(max(0, $purchase->grand_total - $purchase->paid - ($allocatedPayment ?? 0)), 2) }}</td>
      </tr>
      <tr class="payment-status-row">
        <td>Payment Status</td>
        <td class="text-right">{{ $purchase->payment_status }}</td>
      </tr>
    </table>
  </div>
</body>
</html>
