<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt</title>
  <style>
    @page {
      margin: 8px;
    }
    body {
      margin: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 9px;
      color: #000;
      line-height: 1.25;
      width: 100%;
    }
    .text-center {
      text-align: center !important;
    }
    .text-right {
      text-align: right !important;
    }
    .bold {
      font-weight: bold;
    }
    .divider {
      border-top: 1px dashed #000;
      margin: 6px 0;
      height: 1px;
    }
    .company-title {
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 2px;
    }
    .invoice-title {
      font-size: 10px;
      font-weight: bold;
      letter-spacing: 1px;
      margin: 4px 0;
      text-transform: uppercase;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    td, th {
      padding: 2px 0;
      vertical-align: top;
    }
    .items-table th {
      border-bottom: 1px dashed #000;
      font-weight: bold;
      text-align: left;
    }
    .items-table td {
      padding: 3px 0;
    }
    .totals-table td {
      padding: 2px 0;
    }
  </style>
</head>
<body>

@php
  $customer = $sale->customer;
  $isExport = $customer && !empty($customer->country) && strtolower(trim($customer->country)) !== 'india';
  $currencySymbol = '₹';
  $currencyCode = 'INR';
  $exchangeRate = 1.0000;

  if ($isExport && isset($sale->currency) && !empty($sale->currency) && isset($sale->exchange_rate) && $sale->exchange_rate > 0) {
      $currencyCode = $sale->currency;
      $exchangeRate = (double) $sale->exchange_rate;
      switch ($currencyCode) {
          case 'USD': $currencySymbol = '$'; break;
          case 'GBP': $currencySymbol = '£'; break;
          case 'EUR': $currencySymbol = '€'; break;
          case 'SGD': $currencySymbol = 'S$'; break;
          case 'SAR': $currencySymbol = 'SR'; break;
          case 'CAD': $currencySymbol = 'C$'; break;
          case 'AUD': $currencySymbol = 'A$'; break;
          case 'AED': $currencySymbol = 'AED'; break;
          default: $currencySymbol = $currencyCode; break;
      }
  }

  $store = $sale->customer && $sale->customer->user ? $sale->customer->user : null;
  
  $subtotal = 0;
  foreach ($sale->saleItems as $item) {
      $subtotal += $item->base_price;
  }
  
  $calculatedGrandTotal = $subtotal + $sale->gst_amount - $sale->discount;
  $roundedGrandTotal = $calculatedGrandTotal;
@endphp

<div class="text-center">
  <div class="company-title">{{ $store ? $store->name : 'Your Store Name' }}</div>
  @if($store && $store->address)
    <div>{{ $store->address }}</div>
  @endif
  @if($store && $store->phone)
    <div>Phone: {{ $store->phone }}</div>
  @endif
  @if($store && $store->gstin)
    <div class="bold">GSTIN: {{ $store->gstin }}</div>
  @endif
  
  <div class="divider"></div>
  
  <div class="invoice-title">
    @if ($isExport || ($sale->gst_amount ?? 0) <= 0)
      {{ !empty($store->invoice_title_without_gst) ? $store->invoice_title_without_gst : 'INVOICE' }}
    @else
      {{ !empty($store->invoice_title_with_gst) ? $store->invoice_title_with_gst : 'TAX INVOICE' }}
    @endif
  </div>
</div>

<div class="divider"></div>

<!-- Metadata -->
<table>
  <tr>
    <td class="bold" style="width: 40%;">Invoice No.</td>
    <td style="width: 5%;">:</td>
    <td>{{ $sale->id }}/2026-27</td>
  </tr>
  <tr>
    <td class="bold">Date</td>
    <td>:</td>
    <td>{{ $sale->created_at->format('d-m-Y h:i A') }}</td>
  </tr>
  @if($sale->customer)
    <tr>
      <td class="bold">Customer</td>
      <td>:</td>
      <td class="bold">{{ $sale->customer->name }}</td>
    </tr>
    @if($sale->customer->phone)
      <tr>
        <td class="bold">Phone</td>
        <td>:</td>
        <td>{{ $sale->customer->phone }}</td>
      </tr>
    @endif
    @if($sale->customer->gst_number)
      <tr>
        <td class="bold">Cust. GST</td>
        <td>:</td>
        <td>{{ $sale->customer->gst_number }}</td>
      </tr>
    @endif
  @endif
</table>

<div class="divider"></div>

<!-- Items list -->
<table class="items-table">
  <thead>
    <tr>
      <th style="width: 40%;">Item</th>
      <th class="text-center" style="width: 12%;">Qty</th>
      <th class="text-right" style="width: 28%;">Rate</th>
      <th class="text-right" style="width: 20%;">Amt</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($sale->saleItems as $item)
      <tr>
        <td>
          {{ optional($item->product)->name ?? 'N/A' }}
          @if(!empty($item->width) && !empty($item->height))
            <div style="font-size: 8px; font-style: italic; color: #555;">
              Size: {{ (float)$item->width }} x {{ (float)$item->height }}
            </div>
          @endif
        </td>
        <td class="text-center" style="white-space: nowrap;">{{ (float)$item->quantity }}</td>
        <td class="text-right" style="white-space: nowrap;">
          @if($isExport)
            {{ number_format($item->price / $exchangeRate, 2) }}
          @else
            {{ number_format($item->price, 2) }}
          @endif
          /{{ $item->unit_type ?? 'Pcs.' }}
        </td>
        <td class="text-right" style="white-space: nowrap;">
          @if($isExport)
            {{ number_format($item->base_price / $exchangeRate, 2) }}
          @else
            {{ number_format($item->base_price, 2) }}
          @endif
        </td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="divider"></div>

<!-- Totals -->
<table class="totals-table">
  <tr>
    <td style="width: 60%;">Subtotal:</td>
    <td class="text-right" style="width: 40%;">
      @if($isExport)
        {{ $currencySymbol }}{{ number_format($subtotal / $exchangeRate, 2) }}
      @else
        {{ $currencySymbol }}{{ number_format($subtotal, 2) }}
      @endif
    </td>
  </tr>
  
  @if(!$isExport && $sale->gst_amount > 0)
    <tr>
      <td>Total GST:</td>
      <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->gst_amount, 2) }}</td>
    </tr>
  @endif
  
  @if($sale->discount > 0)
    <tr>
      <td>Discount:</td>
      <td class="text-right">-{{ $currencySymbol }}@if($isExport){{ number_format($sale->discount / $exchangeRate, 2) }}@else{{ number_format($sale->discount, 2) }}@endif</td>
    </tr>
  @endif
  
  <tr class="bold">
    <td>Grand Total:</td>
    <td class="text-right">
      {{ $currencySymbol }}@if($isExport){{ number_format($roundedGrandTotal / $exchangeRate, 2) }}@else{{ number_format($roundedGrandTotal, 2) }}@endif
    </td>
  </tr>
</table>

<div class="divider"></div>

<!-- Bank details -->
@if($store && $store->bank_name && !($store->hide_bank_details ?? false))
  <div style="font-size: 8px;">
    <div class="bold">Bank Transfer Details:</div>
    <div>Bank: {{ $store->bank_name }}</div>
    @if($store->branch_name)
      <div>Branch: {{ $store->branch_name }}</div>
    @endif
    <div>A/C: {{ $store->account_number }}</div>
    <div>IFSC: {{ $store->ifsc_code }}</div>
  </div>
  <div class="divider"></div>
@endif

<div class="text-center" style="font-size: 9px; margin-top: 4px; font-weight: bold;">
  Thank you for your business!
</div>

</body>
</html>
