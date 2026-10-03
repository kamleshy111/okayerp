<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Product Wise Sales Report</title>
  <style>
    body {
      margin: 0;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 10px;
      color: #1e293b;
      line-height: 1.25;
    }

    .container {
      width: 100%;
      border: 1px solid #1e3a8a;
      padding: 0;
      box-sizing: border-box;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    td, th {
      padding: 5px 8px;
      vertical-align: middle;
    }

    .border-bottom {
      border-bottom: 1px solid #1e3a8a;
    }

    .border-right {
      border-right: 1px solid #1e3a8a;
    }

    .text-center {
      text-align: center;
    }

    .text-right {
      text-align: right;
    }

    .text-left {
      text-align: left;
    }

    .bold {
      font-weight: bold;
    }

    /* Header styling */
    .header-table td {
      padding: 8px 12px;
    }

    .company-name {
      font-size: 16px;
      font-weight: bold;
      color: #1e3a8a;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .report-title {
      font-size: 14px;
      font-weight: bold;
      color: #1e3a8a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Stats Box Table */
    .stats-table {
      background-color: #f8fafc;
    }

    .stats-table td {
      border-right: 1px solid #cbd5e1;
      padding: 8px 10px;
      text-align: center;
      width: 20%;
    }

    .stats-table td:last-child {
      border-right: none;
    }

    .stats-label {
      font-size: 8.5px;
      font-weight: bold;
      color: #64748b;
      text-transform: uppercase;
      display: block;
      margin-bottom: 2px;
    }

    .stats-value {
      font-size: 12px;
      font-weight: bold;
      color: #0f172a;
    }

    /* Items Table */
    .items-table th {
      background-color: #1e3a8a;
      color: #ffffff;
      font-weight: bold;
      font-size: 9.5px;
      text-align: center;
      padding: 6px 8px;
      border-right: 1px solid #3b82f6;
    }

    .items-table th:last-child {
      border-right: none;
    }

    .items-table td {
      border-right: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
      padding: 6px 8px;
      font-size: 10px;
    }

    .items-table td:last-child {
      border-right: none;
    }

    .items-table tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    .total-row td {
      background-color: #e2e8f0 !important;
      font-weight: bold;
      color: #0f172a;
      border-top: 1px solid #1e3a8a;
      border-bottom: none;
      padding: 8px;
    }

    .sku-badge {
      font-size: 8.5px;
      color: #64748b;
      display: block;
    }
  </style>
</head>
<body>

  <div class="container">
    <!-- Header Table -->
    <table class="header-table border-bottom">
      <tr>
        <td style="width: 55%;" class="text-left">
          <div class="company-name">{{ $store['name'] }}</div>
          @if(!empty($store['address']))
            <div style="font-size: 9.5px; color: #475569;">{{ $store['address'] }}</div>
          @endif
          <div style="font-size: 9.5px; color: #475569;">
            @if(!empty($store['phone'])) Phone: {{ $store['phone'] }} | @endif
            @if(!empty($store['gstin'])) <strong>GSTIN:</strong> {{ $store['gstin'] }} @endif
          </div>
        </td>
        <td style="width: 45%;" class="text-right">
          <div class="report-title">Product-Wise Sales Report</div>
          <div style="font-size: 9px; color: #64748b; margin-top: 3px;">Tally Item-wise Sales Register</div>
          <div style="font-size: 9.5px; margin-top: 2px;">
            <strong>Period:</strong> {{ !empty($filters['from_date']) ? \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') : 'Start' }} to {{ !empty($filters['to_date']) ? \Carbon\Carbon::parse($filters['to_date'])->format('d M Y') : 'Today' }}
          </div>
          <div style="font-size: 8.5px; color: #94a3b8;">
            Generated: {{ now()->format('d M Y, h:i A') }}
          </div>
        </td>
      </tr>
    </table>

    <!-- KPI Summary Bar (Cleaned) -->
    <table class="stats-table border-bottom">
      <tr>
        <td>
          <span class="stats-label">Items Sold</span>
          <span class="stats-value">{{ $summary['total_products'] }}</span>
        </td>
        <td>
          <span class="stats-label">Gross Qty</span>
          <span class="stats-value">{{ number_format($summary['total_gross_qty'], 2) }}</span>
        </td>
        <td>
          <span class="stats-label">Return Qty</span>
          <span class="stats-value" style="color: #dc2626;">{{ number_format($summary['total_return_qty'], 2) }}</span>
        </td>
        <td>
          <span class="stats-label">Net Qty Sold</span>
          <span class="stats-value" style="color: #16a34a;">{{ number_format($summary['total_net_qty'], 2) }}</span>
        </td>
        <td style="background-color: #dbeafe;">
          <span class="stats-label" style="color: #1e3a8a;">Net Sales Value</span>
          <span class="stats-value" style="color: #1e3a8a; font-size: 13px;">₹{{ number_format($summary['total_net_revenue'], 2) }}</span>
        </td>
      </tr>
    </table>

    <!-- Product Summary Table (Cleaned) -->
    <table class="items-table">
      <thead>
        <tr>
          <th style="width: 25px;">#</th>
          <th style="text-align: left; width: 200px;">Product Name & SKU</th>
          <th style="width: 45px;">Unit</th>
          <th style="width: 60px;">Gross Qty</th>
          <th style="width: 55px;">Return</th>
          <th style="width: 60px;">Net Qty</th>
          <th style="width: 75px;">Purchase Rate</th>
          <th style="width: 80px;">Purchase Amt</th>
          <th style="width: 75px;">Sale Rate</th>
          <th style="width: 80px;">Sale Amt</th>
          <th style="width: 80px;">Profit (₹)</th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $idx => $p)
          <tr>
            <td class="text-center">{{ $idx + 1 }}</td>
            <td class="text-left">
              <strong>{{ $p['product_name'] }}</strong>
              @if(!empty($p['product_sku']))
                <span class="sku-badge">SKU: {{ $p['product_sku'] }}</span>
              @endif
            </td>
            <td class="text-center">{{ $p['unit_type'] }}</td>
            <td class="text-right">{{ number_format($p['gross_quantity'], 2) }}</td>
            <td class="text-right" style="color: {{ $p['return_quantity'] > 0 ? '#dc2626' : '#94a3b8' }};">
              {{ $p['return_quantity'] > 0 ? ('-' . number_format($p['return_quantity'], 2)) : '—' }}
            </td>
            <td class="text-right bold" style="color: #16a34a;">{{ number_format($p['net_quantity'], 2) }}</td>
            <td class="text-right">
              {{ $p['purchase_rate'] > 0 ? ('₹' . number_format($p['purchase_rate'], 2)) : '—' }}
            </td>
            <td class="text-right bold" style="color: #78350f;">
              {{ $p['total_cost'] > 0 ? ('₹' . number_format($p['total_cost'], 2)) : '—' }}
            </td>
            <td class="text-right">₹{{ number_format($p['avg_rate'], 2) }}</td>
            <td class="text-right bold" style="color: #1e3a8a;">
              ₹{{ number_format($p['net_amount'], 2) }}
            </td>
            <td class="text-right bold" style="color: {{ ($p['total_profit'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
              {{ ($p['total_profit'] ?? 0) >= 0 ? '+' : '' }}₹{{ number_format($p['total_profit'] ?? 0, 2) }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-center" style="padding: 25px; color: #94a3b8;">
              No product sales found for the selected period.
            </td>
          </tr>
        @endforelse

        <!-- Totals Row -->
        @if(count($products) > 0)
          <tr class="total-row">
            <td colspan="3" class="text-left">GRAND TOTAL ({{ $summary['total_products'] }} Products)</td>
            <td class="text-right">{{ number_format($summary['total_gross_qty'], 2) }}</td>
            <td class="text-right">{{ number_format($summary['total_return_qty'], 2) }}</td>
            <td class="text-right" style="color: #16a34a;">{{ number_format($summary['total_net_qty'], 2) }}</td>
            <td class="text-right">—</td>
            <td class="text-right bold" style="color: #78350f;">₹{{ number_format($summary['total_cost'] ?? 0, 2) }}</td>
            <td class="text-right">—</td>
            <td class="text-right bold" style="color: #1e3a8a;">₹{{ number_format($summary['total_net_revenue'], 2) }}</td>
            <td class="text-right bold" style="color: {{ ($summary['total_profit'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
              {{ ($summary['total_profit'] ?? 0) >= 0 ? '+' : '' }}₹{{ number_format($summary['total_profit'] ?? 0, 2) }}
            </td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>

  <div style="margin-top: 10px; font-size: 8px; color: #94a3b8; text-align: right;">
    Generated by OkayERP &bull; Page 1 of 1
  </div>

</body>
</html>
