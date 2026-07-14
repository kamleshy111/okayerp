<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Supplier Payment Statement</title>
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

    /* Stats Box Table */
    .stats-table td {
      border-right: 1px solid #2e2c92;
      padding: 8px;
      width: 33.33%;
    }
    .stats-table td:last-child {
      border-right: none;
    }
    .stats-label {
      font-size: 9px;
      font-weight: bold;
      color: #6b7280;
      text-transform: uppercase;
      display: block;
      margin-bottom: 2px;
    }
    .stats-value {
      font-size: 14px;
      font-weight: bold;
    }

    /* Items / History Table */
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
      padding: 5px 6px;
    }

    .items-table td:last-child {
      border-right: none;
    }

    .items-table tr.item-row td {
      min-height: 22px;
    }



    .text-emerald {
      color: #16a34a;
    }

    .text-rose {
      color: #dc2626;
    }

    .badge {
      display: inline-block;
      padding: 2px 6px;
      font-size: 9px;
      font-weight: bold;
      border-radius: 4px;
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

    .badge-due {
      background-color: #fef3c7;
      color: #92400e;
    }

    /* Summary row at bottom of table */
    .summary-row td {
      border-top: 2px solid #2e2c92;
      border-bottom: 1px solid #2e2c92;
      padding: 6px;
      background-color: #f8fafc;
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
  $store = $supplier->user;
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
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">ACCOUNT STATEMENT</div>
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
        Ledger Copy
      </td>
    </tr>
  </table>

  <!-- Meta Info Table -->
  <table class="border-bottom meta-table">
    <tr>
      <td class="border-right">
        <table class="meta-sub-table">
          <tr>
            <td class="bold" style="width: 35%;">Statement For</td>
            <td style="width: 5%;">:</td>
            <td class="bold">{{ $supplier->name }}</td>
          </tr>
          <tr>
            <td class="bold">Phone</td>
            <td>:</td>
            <td>{{ $supplier->phone ?? 'N/A' }}</td>
          </tr>
          <tr>
            <td class="bold">Email</td>
            <td>:</td>
            <td>{{ $supplier->email ?? 'N/A' }}</td>
          </tr>
        </table>
      </td>
      <td>
        <table class="meta-sub-table">
          <tr>
            <td class="bold" style="width: 35%;">Generated On</td>
            <td style="width: 5%;">:</td>
            <td>{{ date('d-m-Y') }}</td>
          </tr>
          <tr>
            <td class="bold">Account Status</td>
            <td>:</td>
            <td class="bold text-emerald">ACTIVE</td>
          </tr>
          <tr>
            <td class="bold">GSTIN / UIN</td>
            <td>:</td>
            <td>{{ $supplier->gst_number ?? 'N/A' }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Stats Table -->
  <table class="border-bottom stats-table">
    <tr>
      <td>
        <span class="stats-label">Total Debit (Pay/Ret)</span>
        <span class="stats-value" style="color:#4b5563;">₹{{ number_format($totalDebits, 2) }}</span>
      </td>
      <td>
        <span class="stats-label">Total Credit (Purchases)</span>
        <span class="stats-value" style="color:#4b5563;">₹{{ number_format($totalCredits, 2) }}</span>
      </td>
      <td>
        <span class="stats-label">Outstanding Balance</span>
        <span class="stats-value {{ $currentBalance >= 0 ? 'text-rose' : 'text-emerald' }}">
          ₹{{ number_format(abs($currentBalance), 2) }} {{ $currentBalance >= 0 ? 'Cr' : 'Dr' }}
        </span>
      </td>
    </tr>
  </table>

  <!-- Transaction History Log Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th class="border-right" style="width: 12%;">Date</th>
        <th class="border-right" style="width: 12%;">Vch Type</th>
        <th class="border-right" style="width: 36%;">Particulars</th>
        <th class="border-right" style="width: 13%;">Debit (Dr)</th>
        <th class="border-right" style="width: 13%;">Credit (Cr)</th>
        <th style="width: 14%;">Balance</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($history as $item)
      <tr class="item-row">
        <td class="text-center border-right">
          {{ \Carbon\Carbon::parse($item['date'])->format('d-m-Y') }}
        </td>
        <td class="border-right text-center" style="padding: 4px 6px;">
          @if($item['type'] === 'Purchase')
            <span class="badge badge-sale" style="background-color: #d1fae5; color: #065f46;">Bill</span>
          @elseif($item['type'] === 'Payment')
            <span class="badge badge-payment">Payment</span>
          @elseif($item['type'] === 'Return')
            <span class="badge badge-return">Return</span>
          @else
            <span class="badge" style="background-color:#e2e8f0; color:#1f2937;">{{ $item['type'] }}</span>
          @endif
        </td>
        <td class="border-right" style="padding: 4px 6px;">
          {{ $item['particulars'] }}
          @if($item['payment_method'] && $item['payment_method'] !== 'Bill')
            <span style="font-size: 8px; color: #6b7280;">({{ $item['payment_method'] }})</span>
          @endif
        </td>
        <td class="text-right border-right text-rose bold">
          {{ $item['debit'] > 0 ? '₹' + number_format($item['debit'], 2) : '--' }}
        </td>
        <td class="text-right border-right text-emerald bold">
          {{ $item['credit'] > 0 ? '₹' + number_format($item['credit'], 2) : '--' }}
        </td>
        <td class="text-right bold">
          ₹{{ number_format(abs($item['running_balance']), 2) }} {{ $item['running_balance'] >= 0 ? 'Cr' : 'Dr' }}
        </td>
      </tr>
      @endforeach

      <!-- Summary row -->
      <tr class="summary-row">
        <td class="border-right">&nbsp;</td>
        <td class="border-right text-right bold" colspan="2">Outstanding Balance</td>
        <td class="border-right text-right bold text-rose">
          ₹{{ number_format($totalDebits, 2) }}
        </td>
        <td class="border-right text-right bold text-emerald">
          ₹{{ number_format($totalCredits, 2) }}
        </td>
        <td class="text-right bold" style="font-size: 11px;">
          ₹{{ number_format(abs($currentBalance), 2) }} {{ $currentBalance >= 0 ? 'Cr' : 'Dr' }}
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Footer Section -->
  <table class="footer-table">
    <tr>
      <td class="border-right" style="width: 50%;">
        <div class="bold border-bottom" style="padding-bottom: 2px; margin-bottom: 4px;">Terms & Conditions :</div>
        <div style="font-size: 9px; color: #333;">
          E. & O.E.
          <ol class="terms-list">
            <li>This is a system-generated account statement detailing supplier payment history.</li>
            <li>Please report any discrepancies in balance within 7 business days.</li>
          </ol>
        </div>
      </td>
      <td style="width: 50%; vertical-align: top; padding: 8px;">
        <div class="text-center" style="font-size: 10px; margin-bottom: 30px;">Supplier Representative</div>
        <div style="text-align: right; width: 100%;">
          <div style="font-size: 9px; margin-bottom: 2px;">for <span class="bold">{{ $store ? $store->name : 'Your Company' }}</span></div>
          <div class="bold" style="font-size: 10px; margin-top: 15px; padding-right: 5px;">Authorized Signatory</div>
        </div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>
