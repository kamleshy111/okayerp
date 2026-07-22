<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Invoices Statement - {{ $customer->name }}</title>
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

    /* Invoice List Section */
    .invoice-section-title {
      font-size: 11px;
      font-weight: bold;
      color: #2e2c92;
      background-color: #f3f4f6;
      padding: 6px 8px;
      border-bottom: 1px solid #2e2c92;
      margin: 0;
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

    .sale-total-para {
        text-align: right;
        font-weight: bold;
        margin: 0px;
        font-size: 11px;
        color: #2e2c92;
        padding-top: 4px;
        border-bottom: 1px solid #2e2c92;
        background-color: #f3f4f6;
        padding: 6px;
    }

    /* Footer styling */
    .footer-table td {
      width: 50%;
      padding: 8px;
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
        <div class="bold text-center" style="font-size: 10px; margin-bottom: 2px; letter-spacing: 1px;">CUSTOMER INVOICES STATEMENT</div>
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
            <td class="bold" style="width: 35%;">Billed To</td>
            <td style="width: 5%;">:</td>
            <td class="bold">{{ $customer->name }}</td>
          </tr>
          <tr>
            <td class="bold">Phone</td>
            <td>:</td>
            <td>{{ $customer->phone ?? 'N/A' }}</td>
          </tr>
        </table>
      </td>
      <td>
        <table class="meta-sub-table">
          <tr>
            <td class="bold" style="width: 35%;">Statement Date</td>
            <td style="width: 5%;">:</td>
            <td>{{ date('d-m-Y') }}</td>
          </tr>
          <tr>
            <td class="bold">Email</td>
            <td>:</td>
            <td>{{ $customer->email ?? 'N/A' }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Invoice listing loop -->
  @foreach ($data as $saleIndex => $sale)
    <div class="invoice-section-title">
      Invoice #{{ $sale['sale_id'] }} | Date: {{ $sale['sale_date'] }}
    </div>

    <table class="items-table border-bottom">
      <thead>
        <tr>
          <th class="border-right" style="width: 5%;">S.N.</th>
          <th class="border-right" style="width: 45%;">Product Description</th>
          <th class="border-right" style="width: 10%;">Qty.</th>
          <th class="border-right" style="width: 10%;">Price</th>
          <th class="border-right" style="width: 10%;">SGST</th>
          <th class="border-right" style="width: 10%;">CGST</th>
          <th style="width: 10%;">Total(₹)</th>
        </tr>
      </thead>
      <tbody>
        @php $saleTotal = 0; @endphp
        @foreach ($sale['items'] as $itemIndex => $item)
          <tr class="item-row">
            <td class="text-center border-right">{{ $itemIndex + 1 }}.</td>
            <td class="border-right">
              {{ $item['product_name'] }}
              @if((!empty($item['width']) && !empty($item['height'])) || !empty($item['alternate_quantity']))
                <div style="font-size: 8px; color: #555; margin-top: 1px; font-weight: normal; font-style: italic;">
                  @if(!empty($item['width']) && !empty($item['height']))
                    Size: {{ (float)$item['width'] }} x {{ (float)$item['height'] }}
                  @endif
                  @if(!empty($item['alternate_quantity']))
                    {{ (!empty($item['width']) && !empty($item['height'])) ? ' | ' : '' }}Alt Qty: {{ (float)$item['alternate_quantity'] }} {{ $item['alternate_unit_type'] ?: 'pcs' }}
                  @endif
                </div>
              @endif
            </td>
            <td class="text-right border-right">{{ number_format($item['quantity'], 2) }}</td>
            <td class="text-right border-right">{{ number_format($item['price'], 2) }}</td>
            <td class="text-right border-right">{{ $item['sgst'] }}%</td>
            <td class="text-right border-right">{{ $item['cgst'] }}%</td>
            <td class="text-right">{{ number_format($item['total'], 2) }}</td>
          </tr>
          @php $saleTotal += $item['total']; @endphp
        @endforeach

        <!-- GST / Discount footer values inside table -->
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="bold border-right text-right" colspan="5">GST Amount</td>
          <td class="text-right bold">{{ number_format($sale['gstAmount'], 2) }}</td>
        </tr>
        <tr class="total-row">
          <td class="border-right">&nbsp;</td>
          <td class="bold border-right text-right" colspan="5">Discount Amount</td>
          <td class="text-right bold">{{ number_format($sale['discount'], 2) }}</td>
        </tr>
      </tbody>
    </table>

    <div class="sale-total-para">
      Invoice Total: ₹ {{ number_format(($saleTotal + $sale['gstAmount']) - $sale['discount'], 2) }}
    </div>
  @endforeach

  <!-- Footer Section -->
  <table class="footer-table">
    <tr>
      <td class="border-right" style="width: 50%;">
        <div class="bold border-bottom" style="padding-bottom: 2px; margin-bottom: 4px;">Terms & Conditions :</div>
        <div style="font-size: 9px; color: #333;">
          E. & O.E.
          <ol class="terms-list">
            <li>This is a consolidated statement of customer invoices generated for record verification.</li>
            <li>Subject to terms of individual invoices.</li>
          </ol>
        </div>
      </td>
      <td style="width: 50%; vertical-align: top; padding: 8px;">
        <div class="text-center bold border-bottom" style="padding-bottom: 2px; margin-bottom: 4px;">Receiver's Verification</div>
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
