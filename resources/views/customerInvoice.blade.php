<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $customer->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .container { width: 100%; padding: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .customer-info, .sale-table { width: 100%; margin-bottom: 20px; }
        .sale-table th, .sale-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .sale-table { border-collapse: collapse; width: 100%; }
        .total { text-align: right; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Invoice</h2>
            <p>Date: {{ now()->format('d.m.Y') }}</p>
        </div>

        <div class="customer-info">
            <strong>Customer Info:</strong><br>
            Name: {{ $customer->name }}<br>
            Email: {{ $customer->email ?? 'N/A' }}<br>
            Phone: {{ $customer->phone ?? 'N/A' }}<br>
        </div>

        @foreach ($data as $sale)
            <h4>Sale ID: {{ $sale['sale_id'] }} | Date: {{ $sale['sale_date'] }}</h4>
            <table class="sale-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>SGST</th>
                        <th>CGST</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $saleTotal = 0; @endphp
                    @foreach ($sale['items'] as $item)
                        <tr>
                            <td>{{ $item['product_name'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>{{ number_format($item['price'], 2) }}</td>
                            <td>{{ $item['sgst'] }}</td>
                            <td>{{ $item['cgst'] }}</td>
                            <td>{{ number_format($item['total'], 2) }}</td>
                        </tr>
                        @php $saleTotal += $item['total']; @endphp
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right;"><strong>GST:</strong></td>
                        <td style="text-align: right;">{{ number_format($sale['gstAmount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align: right;"><strong>Discount:</strong></td>
                        <td style="text-align: right;">{{ number_format($sale['discount'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            <p class="total">Sale Total: {{ number_format(($saleTotal + $sale['gstAmount']) - $sale['discount'], 2) }}</p>
            


            <hr>
        @endforeach
    </div>
</body>
</html>
