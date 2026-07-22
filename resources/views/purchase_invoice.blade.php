<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Purchase Bill #{{ $purchase->id }}</title>
  <style>
    body {
      margin: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 11px;
      color: #111;
      line-height: 1.4;
    }
    .invoice-container {
      width: 100%;
      border: 1px solid #2e2c92;
      padding: 0;
      box-sizing: border-box;
    }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 5px; vertical-align: top; }
    .border-bottom { border-bottom: 1px solid #2e2c92; }
    .border-right  { border-right:  1px solid #2e2c92; }
    .text-center   { text-align: center; }
    .text-right    { text-align: right; }
    .bold          { font-weight: bold; }

    /* Header */
    .header-table td { padding: 8px; }
    .company-name {
      font-size: 16px; font-weight: bold;
      margin-bottom: 2px; text-transform: uppercase; color: #2e2c92;
    }
    .company-details { width: 70%; text-align: center; }
    .invoice-type    { width: 30%; text-align: right; font-size: 10px; }

    /* Party boxes */
    .party-table td { width: 50%; padding: 6px 8px; }

    /* Items table */
    .items-table th {
      background-color: #e0e7ff; color: #2e2c92;
      border-bottom: 1px solid #2e2c92; font-weight: bold;
      text-align: center; padding: 6px 4px;
    }
    .items-table td  { border-right: 1px solid #2e2c92; padding: 4px 6px; }
    .items-table td:last-child { border-right: none; }

    /* Totals */
    .total-row td {
      border-top: 1px solid #2e2c92; border-bottom: 1px solid #2e2c92; padding: 6px;
    }

    /* Footer */
    .footer-table td { width: 50%; padding: 8px; }
  </style>
</head>
<body>

@php
  $store = $purchase->supplier->user ?? null;
  $supplier = $purchase->supplier;
  $subtotal = $purchase->items->sum(fn($i) => $i->quantity * $i->unit_price);
  $taxTotal  = $purchase->items->sum(fn($i) => $i->quantity * $i->unit_price * ($i->tax_rate ?? 0) / 100);
  $grandTotal = $purchase->grand_total;
  $paid       = $purchase->paid ?? 0;
  $balance    = $grandTotal - $paid;
@endphp

<div class="invoice-container">

  {{-- ===== HEADER ===== --}}
  <table class="header-table border-bottom">
    <tr>
      <td class="company-details">
        <div class="company-name">{{ $store?->name ?? 'OkayERP' }}</div>
        @if($store?->address)<div>{{ $store->address }}</div>@endif
        @if($store?->phone)<div>Phone: {{ $store->phone }}</div>@endif
        @if($store?->email)<div>Email: {{ $store->email }}</div>@endif
        @if($store?->gst_number)<div><strong>GSTIN:</strong> {{ $store->gst_number }}</div>@endif
      </td>
      <td class="invoice-type" style="text-align:right;">
        <div style="font-size:18px;font-weight:bold;color:#2e2c92;">PURCHASE BILL</div>
        <div style="margin-top:6px;">
          <div><strong>Bill No:</strong> #{{ $purchase->id }}</div>
          <div><strong>Date:</strong> {{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') : \Carbon\Carbon::parse($purchase->created_at)->format('d/m/Y') }}</div>
          @if($purchase->invoice_no)
          <div><strong>Supplier Inv#:</strong> {{ $purchase->invoice_no }}</div>
          @endif
        </div>
      </td>
    </tr>
  </table>

  {{-- ===== PARTY INFO ===== --}}
  <table class="party-table border-bottom">
    <tr>
      <td class="border-right">
        <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;margin-bottom:4px;">Purchased By (Our Company)</div>
        <div class="bold">{{ $store?->name ?? 'Your Business' }}</div>
        @if($store?->address)<div>{{ $store->address }}</div>@endif
        @if($store?->phone)<div>Phone: {{ $store->phone }}</div>@endif
        @if($store?->gst_number)<div>GSTIN: {{ $store->gst_number }}</div>@endif
      </td>
      <td>
        <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;margin-bottom:4px;">Supplier / Seller</div>
        <div class="bold">{{ $supplier?->name ?? 'N/A' }}</div>
        @if($supplier?->address)<div>{{ $supplier->address }}</div>@endif
        @if($supplier?->phone)<div>Phone: {{ $supplier->phone }}</div>@endif
        @if($supplier?->email)<div>Email: {{ $supplier->email }}</div>@endif
        @if($supplier?->gst_number)<div>GSTIN: {{ $supplier->gst_number }}</div>@endif
      </td>
    </tr>
  </table>

  {{-- ===== ITEMS TABLE ===== --}}
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:5%;">#</th>
        <th style="width:35%;text-align:left;">Product / Description</th>
        <th style="width:10%;">SKU</th>
        <th style="width:8%;">Qty</th>
        <th style="width:12%;">Unit Price</th>
        <th style="width:8%;">Tax%</th>
        <th style="width:10%;">Tax Amt</th>
        <th style="width:12%;text-align:right;">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchase->items as $index => $item)
      @php
        $lineSubtotal = $item->quantity * $item->unit_price;
        $lineTax = $lineSubtotal * ($item->tax_rate ?? 0) / 100;
        $lineTotal = $lineSubtotal + $lineTax;
      @endphp
      <tr class="item-row" style="background:{{ $index % 2 === 0 ? '#fff' : '#f9fafb' }}">
        <td class="text-center">{{ $index + 1 }}</td>
        <td>
          {{ $item->product?->name ?? $item->name ?? 'Item' }}
          @if(!empty($item->description))
            <div style="font-size: 8px; color: #444; margin-top: 1px; font-weight: normal; word-wrap: break-word; white-space: normal;">
              {{ $item->description }}
            </div>
          @endif
        </td>
        <td>{{ $item->product?->sku ?? '-' }}</td>
        <td class="text-center">{{ $item->quantity }}</td>
        <td class="text-right">₹ {{ number_format($item->unit_price, 2) }}</td>
        <td class="text-center">{{ $item->tax_rate ?? 0 }}%</td>
        <td class="text-right">₹ {{ number_format($lineTax, 2) }}</td>
        <td class="text-right">₹ {{ number_format($lineTotal, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- ===== TOTALS ===== --}}
  <table style="margin-top:0;">
    <tr class="total-row">
      <td style="width:60%;"></td>
      <td style="width:40%;">
        <table style="width:100%;">
          <tr>
            <td>Subtotal:</td>
            <td class="text-right">₹ {{ number_format($subtotal, 2) }}</td>
          </tr>
          @if($purchase->discount_amount > 0)
          <tr>
            <td>Discount:</td>
            <td class="text-right" style="color:#dc2626;">- ₹ {{ number_format($purchase->discount_amount, 2) }}</td>
          </tr>
          @endif
          <tr>
            <td>Total Tax:</td>
            <td class="text-right">₹ {{ number_format($taxTotal, 2) }}</td>
          </tr>
          @if($purchase->extra_charges > 0)
          <tr>
            <td>Extra Charges:</td>
            <td class="text-right">₹ {{ number_format($purchase->extra_charges, 2) }}</td>
          </tr>
          @endif
          <tr style="font-weight:bold;font-size:13px;border-top:1px solid #2e2c92;">
            <td>Grand Total:</td>
            <td class="text-right">₹ {{ number_format($grandTotal, 2) }}</td>
          </tr>
          <tr>
            <td>Amount Paid:</td>
            <td class="text-right" style="color:#16a34a;">₹ {{ number_format($paid, 2) }}</td>
          </tr>
          <tr style="font-weight:bold;color:{{ $balance > 0 ? '#dc2626' : '#16a34a' }}">
            <td>Balance Due:</td>
            <td class="text-right">₹ {{ number_format($balance, 2) }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- ===== FOOTER ===== --}}
  <table class="footer-table border-bottom" style="margin-top:12px;">
    <tr>
      <td class="border-right">
        <div class="bold" style="margin-bottom:4px;">Payment Status:</div>
        <div style="font-size:13px;font-weight:bold;color:{{ $purchase->payment_status === 'paid' ? '#16a34a' : ($purchase->payment_status === 'partial' ? '#d97706' : '#dc2626') }}">
          {{ strtoupper($purchase->payment_status ?? 'PENDING') }}
        </div>
        @if($purchase->note)
        <div style="margin-top:8px;"><span class="bold">Note:</span> {{ $purchase->note }}</div>
        @endif
      </td>
      <td class="text-right">
        <div style="margin-bottom:40px;font-size:9px;color:#6b7280;">Authorized Signatory</div>
        <div class="bold">{{ $store?->name ?? 'OkayERP' }}</div>
      </td>
    </tr>
  </table>

  <div class="text-center" style="padding:6px;font-size:9px;color:#6b7280;">
    This is a computer generated document. No signature required. &mdash; Generated by OkayERP
  </div>

</div>
</body>
</html>
