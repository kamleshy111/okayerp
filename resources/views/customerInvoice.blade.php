<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $customer->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; margin: 0; padding: 20px; }
        .container { width: 100%; }
        .header { border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 25px; }
        .header h2 { margin: 0; color: #0284c7; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 11px; }
        .customer-info { margin-bottom: 30px; background-color: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .customer-info strong { color: #0f172a; font-size: 13px; display: block; margin-bottom: 8px; }
        .sale-title { font-size: 14px; color: #0284c7; margin-top: 30px; margin-bottom: 10px; border-left: 3px solid #0284c7; padding-left: 8px; font-weight: bold; }
        .sale-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .sale-table th, .sale-table td { border: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .sale-table th { background-color: #f1f5f9; color: #475569; font-weight: bold; font-size: 11px; text-transform: uppercase; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .total-row td { font-weight: bold; background-color: #f8fafc; }
        .grand-total-row td { font-weight: bold; background-color: #f0fdf4; color: #15803d; font-size: 13px; }
        hr { border: 0; border-top: 1px dashed #cbd5e1; margin: 30px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <h2>Customer Invoice Summary</h2>
                        <p>Generated on: {{ now()->format('d.m.Y') }}</p>
                    </td>
                    <td class="text-right" style="vertical-align: bottom;">
                        <span style="font-size: 12px; color: #64748b; font-weight: bold;">OkayERP System</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="customer-info">
            <strong>Bill To (Customer Information):</strong>
            <table width="100%" cellpadding="0" cellspacing="0" style="color: #475569;">
                <tr>
                    <td width="15%"><strong>Name:</strong></td>
                    <td>{{ $customer->name }}</td>
                </tr>
                @if($customer->email)
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>{{ $customer->email }}</td>
                </tr>
                @endif
                @if($customer->phone)
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td>{{ $customer->phone }}</td>
                </tr>
                @endif
                @if($customer->address)
                <tr>
                    <td><strong>Address:</strong></td>
                    <td>{{ $customer->address }}</td>
                </tr>
                @endif
            </table>
        </div>

        @foreach ($data as $sale)
            <div class="sale-title">
                Sale ID: {{ $sale['sale_id'] }} &nbsp;|&nbsp; Date: {{ \Carbon\Carbon::parse($sale['sale_date'])->format('d.m.Y') }}
            </div>
            <table class="sale-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center" style="width: 8%;">Qty</th>
                        <th class="text-right" style="width: 15%;">Price</th>
                        <th class="text-right" style="width: 10%;">SGST %</th>
                        <th class="text-right" style="width: 10%;">CGST %</th>
                        <th class="text-right" style="width: 20%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $saleTotal = 0; @endphp
                    @foreach ($sale['items'] as $item)
                        <tr>
                            <td>{{ $item['product_name'] }}</td>
                            <td class="text-center">{{ $item['quantity'] }}</td>
                            <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                            <td class="text-right">{{ $item['sgst'] }}%</td>
                            <td class="text-right">{{ $item['cgst'] }}%</td>
                            <td class="text-right">{{ number_format($item['total'], 2) }}</td>
                        </tr>
                        @php $saleTotal += $item['total']; @endphp
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right">GST Amount:</td>
                        <td class="text-right">{{ number_format($sale['gstAmount'], 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="5" class="text-right">Discount:</td>
                        <td class="text-right">{{ number_format($sale['discount'], 2) }}</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td colspan="5" class="text-right">Sale Total:</td>
                        <td class="text-right">{{ number_format($sale['grand_total'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            
            @if(!$loop->last)
                <hr>
            @endif
        @endforeach
    </div>
</body>
</html>
