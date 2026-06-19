<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Debit Note - Purchase Return</title>
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
      color: #d97706; /* Amber/orange color scheme for purchase returns */
    }

    .company-name {
      font-size: 12px;
      color: #6b7280;
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
      background-color: #fef3c7; /* Amber theme */
      color: #92400e;
      font-weight: bold;
    }
    
    .invoice-table-1 {
      background-color: #fef3c7;
      color: #92400e;
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

    .totals-table tr:nth-child(3) td {
      font-weight: bold;
      color: #d97706;
    }
  </style>
</head>
<body>
  <div class="invoice-box">
    <div class="invoice-header">
      <table width="100%" style="border-bottom: 2px solid #d97706; padding-bottom: 10px; margin-bottom: 20px;">
        <tr>
          <td style="width: 50%;">
            @if($return->purchase->supplier && $return->purchase->supplier->user && $return->purchase->supplier->user->profile_photo && file_exists(storage_path('app/public/' . $return->purchase->supplier->user->profile_photo)))
              <img src="{{ storage_path('app/public/' . $return->purchase->supplier->user->profile_photo) }}" style="max-height: 60px; max-width: 180px;">
            @elseif(file_exists(public_path('images/logo.png')))
              <img src="{{ public_path('images/logo.png') }}" style="max-height: 60px; max-width: 180px;">
            @else
              <span style="font-size: 20px; font-weight: bold; color: #d97706; text-transform: uppercase; letter-spacing: 1px;">
                {{ $return->purchase->supplier && $return->purchase->supplier->user ? $return->purchase->supplier->user->name : 'OkayERP' }}
              </span>
            @endif
          </td>
          <td style="width: 50%; text-align: right;">
            <h1 style="margin: 0; font-size: 24px; color: #d97706;">Debit Note</h1>
            <div style="font-size: 12px; color: #6b7280;">{{ $return->purchase->supplier && $return->purchase->supplier->user ? $return->purchase->supplier->user->name : 'Your Company Name' }}</div>
          </td>
        </tr>
      </table>
    </div>

    <div class="info-section">
      <div class="info-left">
        <p><strong>From (Supplier):</strong><br>
          {{ $return->purchase->supplier->name ?? 'N/A' }}<br>
          @if($return->purchase->supplier->address)
            {!! nl2br(e($return->purchase->supplier->address)) !!}<br>
          @endif
          @if($return->purchase->supplier->phone)
            Phone: {{ $return->purchase->supplier->phone }}<br>
          @endif
          @if($return->purchase->supplier->email)
            Email: {{ $return->purchase->supplier->email }}
          @endif
        </p>
      </div>
      <div class="info-right text-right">
        <p>
          <strong>Return Date:</strong> {{ \Carbon\Carbon::parse($return->return_date)->format('d.m.Y') }}<br>
          <strong>Return No:</strong> {{ $return->return_no }}<br>
          <strong>Original Bill ID #:</strong> {{ $return->purchase_id }}<br>
          <strong>Refund Method:</strong> {{ $return->refund_method }}
        </p>
      </div>
    </div>

    <div class="bill-to">
      <strong class="invoice-table-1">Bill To (Store):</strong><br>
      {{ $return->purchase->supplier->user->name ?? 'N/A' }}<br>
      {{ $return->purchase->supplier->user->phone ?? 'N/A' }}<br>
      {{ $return->purchase->supplier->user->email ?? 'N/A' }}<br>
      {{ $return->purchase->supplier->user->address ?? 'N/A' }}
      @if(!empty($return->purchase->supplier->user->gstin))
        <br>GSTIN: {{ $return->purchase->supplier->user->gstin }}
      @endif
    </div>

    <table class="invoice-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Returned Qty</th>
          <th class="text-right">Unit Cost</th>
          <th class="text-right">Total Return</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($return->items as $item)
        <tr>
          <td>{{ optional($item->product)->name ?? 'N/A' }}</td>
          <td>{{ $item->quantity }}</td>
          <td class="text-right">{{ number_format($item->price, 2) }}</td>
          <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <table class="totals-table">
      <tr>
        <td>Subtotal Return</td>
        <td class="text-right">{{ number_format($return->refund_amount, 2) }}</td>
      </tr>
      @if($return->gst_refund_amount > 0)
      <tr>
        <td>GST Refunded</td>
        <td class="text-right">{{ number_format($return->gst_refund_amount, 2) }}</td>
      </tr>
      @endif
      <tr>
        <td>Total Refunded</td>
        <td class="text-right">₹ {{ number_format($return->refund_amount + $return->gst_refund_amount, 2) }}</td>
      </tr>
    </table>
    
    @if($return->reason)
    <div style="margin-top: 50px; border-top: 1px solid #ddd; padding-top: 10px;">
      <strong>Reason for Return:</strong><br>
      {{ $return->reason }}
    </div>
    @endif
  </div>
</body>
</html>
