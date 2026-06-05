<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice</title>
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

    .logo {
      height: 50px;
    }

    .invoice-title h1 {
      margin: 0;
      font-size: 24px;
      color: #0369a1;
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
      background-color: #e0f2fe;
      color: #0369a1;
      font-weight: bold;
    }
    .invoice-table-1{
        background-color: #e0f2fe;
      color: #0369a1;
      font-weight: bold;
      margin-bottom: 15px;    
    }

    .text-right {
      text-align: right;
    }

    .totals-table {
      width: 50%;
      max-width:50%;
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
      background-color: #0284c7;
      color: white;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="invoice-box">
   <div class="invoice-header">
    <table width="100%" style="border-bottom: 2px solid #0369a1; padding-bottom: 10px; margin-bottom: 20px;">
        <tr>
        <td style="width: 50%;">
            <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo" style="height: 50px;">
        </td>
        <td style="width: 50%; text-align: right;">
            <h1 style="margin: 0; font-size: 24px; color: #0369a1;">Invoice</h1>
            <div style="font-size: 12px; color: #6b7280;">Your Company Name</div>
        </td>
        </tr>
    </table>
    </div>


    <div class="info-section">
      <div class="info-left">
        <p><strong>From:</strong><br>
          Your Company<br>
          123 Business Street<br>
          City, State ZIP<br>
          Phone: (555) 555-5555<br>
          Email: email@company.com
        </p>
      </div>
      <div class="info-right text-right">
        <p><strong>Date:</strong> {{ $sale->created_at->format('d.m.Y') }}<br>
           <strong>Invoice #:</strong> {{ $sale->id }}</p>
      </div>
    </div>

    <div class="bill-to">
      <strong class="invoice-table-1">Bill To:</strong><br>
      {{ $sale->customer->name ?? 'N/A' }}<br>
      {{ $sale->customer->phone ?? 'N/A' }}<br>
      {{ $sale->customer->email ?? 'N/A' }}<br>
      {{ $sale->customer->address ?? 'N/A' }}
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
          <th class="text-right">GST %</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($sale->saleItems as $item)
        <tr>
          <td>{{ optional($item->product)->name ?? 'N/A' }}</td>
          <td>{{ $item->unit_type }}</td>
          <td>{{ $item->quantity }}</td>
          <td class="text-right">{{ number_format($item->price, 2) }}</td>
          <td class="text-right">{{ number_format($item->base_price, 2) }}</td>
          <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }}</td>
          <td class="text-right">{{ number_format($item->sgst + $item->cgst, 2) }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <table class="totals-table">
      <tr>
        <td>Total Amount</td>
        <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
      </tr>
      <tr>
        <td>GST Amount</td>
        <td class="text-right">{{ number_format($sale->gst_amount, 2) }}</td>
      </tr>
      <tr>
        <td>Discount</td>
        <td class="text-right">{{ number_format($sale->discount, 2) }}</td>
      </tr>
      <tr>
        <td>Grand Total</td>
        <td class="text-right">{{ number_format($sale->grand_total, 2) }}</td>
      </tr>
      <tr>
        <td>Paid</td>
        <td class="text-right">{{ number_format($sale->paid, 2) }}</td>
      </tr>
      <tr class="payment-status-row">
        <td>Payment Status</td>
        <td class="text-right">{{ $sale->payment_status }}</td>
      </tr>
    </table>
  </div>
</body>
</html>
