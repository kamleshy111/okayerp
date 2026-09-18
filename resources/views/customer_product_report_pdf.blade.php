<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Product Sales Report</title>
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
      width: 50%;
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
      padding: 6px 8px;
    }

    .items-table td:last-child {
      border-right: none;
    }

    .items-table tr.item-row td {
      min-height: 24px;
    }

    .sub-table {
      width: 100%;
      margin: 4px 0;
      background-color: #f8fafc;
      border: 1px solid #cbd5e1;
    }

    .sub-table th {
      background-color: #f1f5f9;
      color: #475569;
      font-size: 9px;
      padding: 3px 5px;
      border-bottom: 1px solid #cbd5e1;
    }

    .sub-table td {
      font-size: 9px;
      padding: 3px 5px;
      border-right: 1px solid #e2e8f0;
      border-bottom: 1px solid #f1f5f9;
    }

    .badge {
      display: inline-block;
      padding: 2px 5px;
      font-size: 8px;
      font-weight: bold;
      border-radius: 3px;
    }

    .badge-sale {
      background-color: #d1fae5;
      color: #065f46;
    }

    .badge-return {
      background-color: #ffe4e6;
      color: #9f1239;
    }

    /* Summary row at bottom of table */
    .summary-row td {
      border-top: 2px solid #2e2c92;
      border-bottom: 1px solid #2e2c92;
      padding: 6px 8px;
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
  $store = $customer->user;
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
        <div class="company-name">{{ $store ? $store->name : 'OKAY ERP' }}</div>
        @if($store && $store->address)
          <div style="font-size: 10px; margin-bottom: 2px;">{{ $store->address }}</div>
        @endif
        <div style="font-size: 10px;">
          @if($store && $store->phone) Phone: {{ $store->phone }} @endif
          @if($store && $store->email) | Email: {{ $store->email }} @endif
        </div>
        @if($store && $store->gst_number)
          <div style="font-size: 10px; font-weight: bold; margin-top: 2px;">GSTIN: {{ $store->gst_number }}</div>
        @endif
      </td>
      <td class="invoice-type">
        <div style="font-size: 12px; font-weight: bold; color: #2e2c92;">PRODUCT REPORT</div>
        <div style="color: #6b7280; font-size: 9px; margin-top: 3px;">Date: {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
      </td>
    </tr>
  </table>

  <!-- Meta Table (Customer Info & Period Details) -->
  <table class="border-bottom meta-table">
    <tr>
      <td class="border-right">
        <table class="meta-sub-table">
          <tr>
            <td style="width: 35%; color: #6b7280;">Customer Name</td>
            <td class="bold">{{ $customer->name }}</td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Phone Number</td>
            <td>{{ $customer->phone ?? 'N/A' }}</td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Email Address</td>
            <td>{{ $customer->email ?? 'N/A' }}</td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Billing Address</td>
            <td>{{ $customer->address ?? 'N/A' }}</td>
          </tr>
        </table>
      </td>
      <td>
        <table class="meta-sub-table">
          <tr>
            <td style="width: 40%; color: #6b7280;">Report Type</td>
            <td class="bold">Product-wise Sales Summary</td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Generated Date</td>
            <td>{{ \Carbon\Carbon::now()->format('d-m-Y h:i A') }}</td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Filter Period</td>
            <td>
              @if($fromDate || $toDate)
                {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d/m/Y') : 'Start' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d/m/Y') : 'End' }}
              @else
                All Time Transactions
              @endif
            </td>
          </tr>
          <tr>
            <td style="color: #6b7280;">Account Type</td>
            <td>Registered Customer</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Summary Stats Box -->
  <table class="border-bottom stats-table">
    <tr class="text-center">
      <td style="width: 33.33%;">
        <span class="stats-label">Unique Products Purchased</span>
        <span class="stats-value" style="color:#2e2c92;">{{ $totalProductsCount }}</span>
      </td>
      <td style="width: 33.33%;">
        <span class="stats-label">Total Quantity</span>
        <span class="stats-value" style="color:#16a34a;">{{ number_format($grandTotalQuantity, 2) }}</span>
      </td>
      <td style="width: 33.33%;">
        <span class="stats-label">Total Amount</span>
        <span class="stats-value" style="color:#2e2c92;">₹{{ number_format($grandTotalAmount, 2) }}</span>
      </td>
    </tr>
  </table>

  <!-- Product-wise Report Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th class="border-right" style="width: 7%;">S No</th>
        <th class="border-right" style="width: 43%; text-align: left; padding-left: 8px;">Product Name & SKU</th>
        <th class="border-right" style="width: 16%;">Total Quantity</th>
        <th class="border-right" style="width: 16%; text-align: right; padding-right: 8px;">Unit Rate</th>
        <th style="width: 18%; text-align: right; padding-right: 8px;">Total Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($aggregated as $index => $prod)
      <tr class="item-row">
        <td class="text-center border-right">
          {{ $index + 1 }}
        </td>
        <td class="border-right">
          <div class="bold" style="font-size: 12px; color: #111827;">{{ $prod['product_name'] }}</div>
          @if($prod['product_code'])
            <div style="font-size: 9px; color: #6b7280;">SKU: {{ $prod['product_code'] }}</div>
          @endif
          @if(count($prod['transactions']) > 1)
            <div style="font-size: 8px; color: #2e2c92; font-style: italic; margin-top: 2px;">
              Includes {{ count($prod['transactions']) }} transaction entries
            </div>
          @endif
        </td>
        <td class="text-center border-right bold" style="font-size: 12px; {{ $prod['total_quantity'] < 0 ? 'color: #dc2626;' : 'color: #16a34a;' }}">
          {{ number_format($prod['total_quantity'], 2) }} {{ $prod['unit_type'] }}
        </td>
        <td class="text-right border-right bold" style="color: #1f2937;">
          {{ $prod['rate_display'] }}
        </td>
        <td class="text-right bold" style="color: #2e2c92; font-size: 12px;">
          ₹{{ number_format($prod['total_amount'], 2) }}
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" class="text-center" style="padding: 20px; color: #9ca3af;">
          No product sales found matching the criteria.
        </td>
      </tr>
      @endforelse

      @if($aggregated->count() > 0)
      <tr class="summary-row">
        <td class="border-right">&nbsp;</td>
        <td class="border-right text-right bold" style="font-size: 11px;">Grand Total:</td>
        <td class="border-right text-center bold" style="color: #16a34a; font-size: 11px;">
          {{ number_format($grandTotalQuantity, 2) }}
        </td>
        <td class="border-right text-right bold">--</td>
        <td class="text-right bold" style="color: #2e2c92; font-size: 12px;">
          ₹{{ number_format($grandTotalAmount, 2) }}
        </td>
      </tr>
      @endif
    </tbody>
  </table>

  <!-- Footer Information & Sign-off -->
  <table class="border-bottom footer-table">
    <tr>
      <td class="border-right" style="vertical-align: top;">
        <span class="bold" style="font-size: 10px;">Terms & Conditions:</span>
        <ul class="terms-list" style="font-size: 9px; color: #4b5563;">
          <li>This is a system-generated product sales statement.</li>
          <li>All product quantities reflect recorded sales invoices and returns.</li>
          <li>For questions regarding this report, please contact customer support.</li>
        </ul>
      </td>
      <td style="text-align: right; vertical-align: bottom; padding-bottom: 12px; padding-right: 15px;">
        <div style="font-size: 10px; color: #4b5563; margin-bottom: 40px;">
          For <strong>{{ $store ? $store->name : 'OKAY ERP' }}</strong>
        </div>
        <div style="font-size: 10px; border-top: 1px solid #9ca3af; display: inline-block; padding-top: 4px; min-width: 140px; text-align: center;">
          Authorized Signatory
        </div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>
