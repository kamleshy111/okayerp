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
          <img src="{{ storage_path('app/public/' . $store->profile_photo) }}" style="max-height: 55px; max-width: 150px;">
        @elseif(file_exists(public_path('images/logo.png')))
          <img src="{{ public_path('images/logo.png') }}" style="max-height: 55px; max-width: 150px;">
        @else
          <span style="font-size: 14px; font-weight: bold; color: #2e2c92;">{{ $store ? $store->name : 'OKAY ERP' }}</span>
        @endif
      </td>
      <td class="company-details">
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">PAYMENT STATEMENT</div>
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
        Statement Copy
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

  <!-- Transaction History Log Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th class="border-right" style="width: 7%;">S.N.</th>
        <th class="border-right" style="width: 25%;">Source</th>
        <th class="border-right" style="width: 18%;">Payment Date</th>
        <th class="border-right" style="width: 30%;">Payment Method</th>
        <th style="width: 20%;">Amount(₹)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($history as $index => $item)
      <tr class="item-row">
        <td class="text-center border-right">{{ $index + 1 }}.</td>
        <td class="border-right" style="padding: 4px 6px;">
          @php
            $src = $item['source'];
          @endphp
          @if(str_starts_with($src, 'Purchase'))
            <span class="badge badge-purchase">Purchase</span>
            @if(strlen($src) > 8)
              <span style="font-size: 9px; color: #374151;">{{ str_replace('Purchase ', '', $src) }}</span>
            @endif
          @elseif(str_starts_with($src, 'Return'))
            <span class="badge badge-return">Return</span>
            @if(strlen($src) > 6)
              <span style="font-size: 9px; color: #374151;">{{ str_replace('Return ', '', $src) }}</span>
            @endif
          @elseif(str_starts_with($src, 'Due Clearance'))
            <span class="badge badge-due">Due Clearance</span>
            @if(strlen($src) > 13)
              <span style="font-size: 9px; color: #374151;">{{ str_replace('Due Clearance ', '', $src) }}</span>
            @endif
          @elseif($src === 'Supplier Payment')
            <span class="badge badge-payment">Direct Payment</span>
          @else
            <span class="badge" style="background-color:#e2e8f0; color:#1f2937;">{{ $src }}</span>
          @endif
        </td>
        <td class="text-center border-right">
          {{ \Carbon\Carbon::parse($item['payment_date'])->format('d-m-Y') }}
        </td>
        <td class="border-right" style="padding: 4px 6px;">
          {{ $item['payment_method'] }}
        </td>
        <td class="text-right bold">
          @if($item['amount'] < 0)
            <span class="text-rose">- ₹{{ number_format(abs($item['amount']), 2) }}</span>
          @else
            <span class="text-emerald">+ ₹{{ number_format($item['amount'], 2) }}</span>
          @endif
        </td>
      </tr>
      @endforeach



      <!-- Summary row -->
      <tr class="summary-row">
        <td class="border-right">&nbsp;</td>
        <td class="border-right text-right bold" colspan="3">Net Balance</td>
        <td class="text-right bold" style="font-size: 12px;">
          @if($netAmount < 0)
            <span class="text-rose">- ₹{{ number_format(abs($netAmount), 2) }}</span>
          @else
            <span class="text-emerald">+ ₹{{ number_format(abs($netAmount), 2) }}</span>
          @endif
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
