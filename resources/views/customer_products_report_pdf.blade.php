<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $isAll ? 'All Customers Pricing Report' : 'Customer Pricing - ' . ($customer->name ?? 'Customer') }}</title>
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
      padding: 6px 8px;
      vertical-align: middle;
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
      padding: 10px;
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
      vertical-align: top;
    }

    .meta-sub-table td {
      padding: 5px 8px;
      border-bottom: 1px solid #e5e7eb;
    }

    .meta-sub-table tr:last-child td {
      border-bottom: none;
    }

    /* Stats Box Table */
    .stats-table td {
      border-right: 1px solid #2e2c92;
      padding: 8px 12px;
      text-align: center;
    }

    .stats-table td:last-child {
      border-right: none;
    }

    .stats-label {
      font-size: 9px;
      text-transform: uppercase;
      color: #6b7280;
      display: block;
      font-weight: bold;
      margin-bottom: 2px;
    }

    .stats-value {
      font-size: 14px;
      font-weight: bold;
    }

    /* Products Table */
    .items-table th {
      background-color: #e0e7ff;
      color: #2e2c92;
      border-bottom: 1px solid #2e2c92;
      font-size: 10px;
      text-transform: uppercase;
      font-weight: bold;
      padding: 6px 8px;
    }

    .items-table td {
      border-right: 1px solid #2e2c92;
      border-bottom: 1px solid #e5e7eb;
      padding: 5px 6px;
      font-size: 10px;
    }

    .items-table td:last-child {
      border-right: none;
    }

    .item-row:nth-child(even) {
      background-color: #f9fafb;
    }

    .item-row:last-child td {
      border-bottom: 1px solid #2e2c92;
    }

    .text-indigo {
      color: #2e2c92;
    }

    .text-emerald {
      color: #059669;
    }

    /* Footer Table */
    .footer-table td {
      padding: 10px 12px;
      font-size: 9px;
      color: #6b7280;
    }
  </style>
</head>
<body>

<div class="invoice-container">
  
  <!-- Header Table -->
  <table class="border-bottom header-table">
    <tr>
      <td class="logo-container">
        @if($store && $store->profile_photo && file_exists(storage_path('app/public/' . $store->profile_photo)))
          <img src="{{ storage_path('app/public/' . $store->profile_photo) }}" style="height: 55px; width: auto;">
        @elseif(file_exists(public_path('images/logo.png')))
          <img src="{{ public_path('images/logo.png') }}" style="height: 55px; width: auto;">
        @endif
      </td>
      <td class="company-details">
        <div class="company-name">{{ $store ? $store->name : 'OkayERP Store' }}</div>
        @if($store && $store->address)
          <div>{{ $store->address }}</div>
        @endif
        @if($store && $store->allow_gst_invoice && $store->gstin)
          <div class="bold">GSTIN: {{ $store->gstin }}</div>
        @endif
        @if($store && $store->phone)
          <div>Tel: {{ $store->phone }}</div>
        @endif
      </td>
      <td class="invoice-type text-right bold">
      </td>
    </tr>
  </table>

  <!-- Meta Info Table -->
  <table class="border-bottom meta-table">
    <tr>
      @if(!$isAll && $customer)
        <td class="border-right" style="width: 50%; padding: 6px 10px;">
          <span class="bold">Customer Name : </span>
          <span class="bold text-indigo">{{ $customer->name }}</span>
          @if($store && $store->allow_gst_invoice && !empty($customer->gst_number))
            <div style="margin-top: 3px;">
              <span class="bold">GSTIN : </span>
              <span>{{ $customer->gst_number }}</span>
            </div>
          @endif
        </td>
        <td style="width: 50%; padding: 6px 10px;">
          <span class="bold">Phone : </span>
          <span>{{ $customer->phone ?: 'N/A' }}</span>
        </td>
      @else
        <td colspan="2" style="padding: 6px 10px;">
          <span class="bold">Report Type : </span>
          <span class="bold text-indigo">All Customer-Specific Pricing Master List</span>
        </td>
      @endif
    </tr>
  </table>


  <!-- Products List Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th class="border-right text-center" style="width: 5%;">#</th>
        @if($isAll)
          <th class="border-right" style="width: 22%;">Customer</th>
        @endif
        <th class="border-right" style="{{ $isAll ? 'width: 28%;' : 'width: 38%;' }}">Product Name</th>
        <th class="border-right" style="width: 15%;">Category</th>
        <th class="border-right text-center" style="width: 8%;">Unit</th>
        <th class="border-right text-right" style="width: 13%;">Standard Price</th>
        <th class="text-right" style="width: 14%;">Customer Price</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($customerProducts as $index => $item)
        <tr class="item-row">
          <td class="text-center border-right bold" style="color: #6b7280;">{{ $index + 1 }}</td>
          @if($isAll)
            <td class="border-right bold text-indigo">
              {{ $item->customer_name }}
              @if(!empty($item->customer_phone))
                <div style="font-size: 8px; color: #6b7280; font-weight: normal;">{{ $item->customer_phone }}</div>
              @endif
            </td>
          @endif
          <td class="border-right bold">
            {{ $item->product_name }}
            @if(!empty($item->sku))
              <span style="font-size: 8px; color: #6b7280; font-weight: normal;">({{ $item->sku }})</span>
            @endif
          </td>
          <td class="border-right" style="color: #4b5563;">
            {{ $item->category_name ?: '----' }}
          </td>
          <td class="border-right text-center" style="color: #4b5563;">
            {{ $item->unit_type ?: 'Unit' }}
          </td>
          <td class="border-right text-right" style="color: #4b5563;">
            ₹{{ number_format((float)($item->master_price ?? 0), 2) }}
          </td>
          <td class="text-right bold text-indigo" style="font-size: 11px;">
            ₹{{ number_format((float)$item->sale_price, 2) }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="{{ $isAll ? 7 : 6 }}" class="text-center" style="padding: 25px; color: #9ca3af;">
            No customer specific products or pricing assigned.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Footer Table -->
  <table class="footer-table">
    <tr>
      <td style="width: 60%;">
        <div>* Customer sale prices will automatically apply when billing for this customer in Sales & Invoicing.</div>
      </td>
      <td style="width: 40%; text-align: right;" class="bold text-indigo">
        Authorized Signatory / Store Stamp
      </td>
    </tr>
  </table>

</div>

</body>
</html>
