<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Supplier Payment History</title>
  <style>
    body {
      margin: 0;
      font-family: DejaVu Sans, sans-serif;
      font-size: 13px;
      color: #1f2937;
    }

    .statement-box {
      width: 100%;
      padding: 20px;
      background: #fff;
    }

    .statement-title h1 {
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

    .supplier-profile {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 25px;
    }

    .supplier-profile h3 {
      margin: 0 0 10px 0;
      color: #1e1b4b;
      font-size: 16px;
    }

    .supplier-profile p {
      margin: 4px 0;
      color: #4b5563;
    }

    .stats-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }

    .stats-table td {
      border: 1px solid #e2e8f0;
      padding: 12px;
      width: 33.33%;
      vertical-align: top;
    }

    .stats-label {
      font-size: 11px;
      font-weight: bold;
      color: #6b7280;
      text-transform: uppercase;
      margin-bottom: 5px;
      display: block;
    }

    .stats-value {
      font-size: 18px;
      font-weight: bold;
    }

    .text-emerald {
      color: #16a34a;
    }

    .text-rose {
      color: #dc2626;
    }

    table.history-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    table.history-table th,
    table.history-table td {
      border: 1px solid #ddd;
      padding: 10px 8px;
      text-align: left;
    }

    table.history-table th {
      background-color: #2e2c92;
      color: #ffffff;
      font-weight: bold;
    }

    .text-right {
      text-align: right;
    }

    .badge {
      display: inline-block;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: bold;
      border-radius: 9999px;
    }

    .badge-purchase {
      background-color: #d1fae5;
      color: #065f46;
    }

    .badge-payment {
      background-color: #e0e7ff;
      color: #3730a3;
    }

    .badge-return {
      background-color: #ffe4e6;
      color: #9f1239;
    }
  </style>
</head>
<body>
  <div class="statement-box">
    <table width="100%" style="border-bottom: 2px solid #2e2c92; padding-bottom: 10px; margin-bottom: 25px;">
      <tr>
        <td style="width: 50%;">
          @if($supplier->user && $supplier->user->profile_photo && file_exists(storage_path('app/public/' . $supplier->user->profile_photo)))
            <img src="{{ storage_path('app/public/' . $supplier->user->profile_photo) }}" style="max-height: 60px; max-width: 180px;">
          @elseif(file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" style="max-height: 60px; max-width: 180px;">
          @else
            <span style="font-size: 20px; font-weight: bold; color: #2e2c92; text-transform: uppercase; letter-spacing: 1px;">
              {{ $supplier->user ? $supplier->user->name : 'OkayERP' }}
            </span>
          @endif
        </td>
        <td style="width: 50%; text-align: right;">
          <h1 style="margin: 0; font-size: 22px; color: #2e2c92;">Payment Statement</h1>
          <div style="font-size: 11px; color: #6b7280;">Generated on {{ date('d.m.Y') }}</div>
        </td>
      </tr>
    </table>

    <div class="supplier-profile">
      <h3>Supplier Account Profile</h3>
      <p><strong>Name:</strong> {{ $supplier->name }}</p>
      @if($supplier->email)
        <p><strong>Email:</strong> {{ $supplier->email }}</p>
      @endif
      @if($supplier->phone)
        <p><strong>Phone:</strong> {{ $supplier->phone }}</p>
      @endif
      @if($supplier->address)
        <p><strong>Address:</strong> {{ $supplier->address }}</p>
      @endif
    </div>

    <table class="stats-table">
      <tr>
        <td>
          <span class="stats-label">Total Paid</span>
          <span class="stats-value text-emerald">+ ₹{{ number_format($totalPaid, 2) }}</span>
        </td>
        <td>
          <span class="stats-label">Total Refunded</span>
          <span class="stats-value text-rose">- ₹{{ number_format($totalRefunded, 2) }}</span>
        </td>
        <td>
          <span class="stats-label">Net Balance</span>
          <span class="stats-value {{ $netAmount < 0 ? 'text-rose' : 'text-emerald' }}">
            {{ $netAmount < 0 ? '-' : '+' }} ₹{{ number_format(abs($netAmount), 2) }}
          </span>
        </td>
      </tr>
    </table>

    <h2 style="font-size: 16px; color: #1e1b4b; margin: 20px 0 10px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Transaction History Log</h2>

    <table class="history-table">
      <thead>
        <tr>
          <th style="width: 8%;">S No</th>
          <th style="width: 22%;">Source</th>
          <th style="width: 20%;" class="text-right">Amount</th>
          <th style="width: 22%;">Payment Date</th>
          <th style="width: 28%;">Payment Method</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($history as $index => $item)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>
              @if($item['source'] === 'Purchase')
                <span class="badge badge-purchase">Purchase</span>
              @elseif($item['source'] === 'Supplier Payment')
                <span class="badge badge-payment">Direct Payment</span>
              @elseif($item['source'] === 'Return')
                <span class="badge badge-return">Return</span>
              @else
                <span class="badge" style="background-color:#e2e8f0;">{{ $item['source'] }}</span>
              @endif
            </td>
            <td class="text-right" style="font-weight: bold;">
              @if($item['amount'] < 0)
                <span class="text-rose">- ₹{{ number_format(abs($item['amount']), 2) }}</span>
              @else
                <span class="text-emerald">+ ₹{{ number_format($item['amount'], 2) }}</span>
              @endif
            </td>
            <td>{{ \Carbon\Carbon::parse($item['payment_date'])->format('d.m.Y') }}</td>
            <td>{{ $item['payment_method'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</body>
</html>
