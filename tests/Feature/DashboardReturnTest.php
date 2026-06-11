<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

class DashboardReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_profitLossData_adjusts_for_sales_and_purchase_returns(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Customer',
            'email' => 'john@example.com',
            'phone' => '1234567891',
        ]);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Inventory Item',
            'sku' => 'INV-ITEM',
            'price' => 100.00,
            'stock_quantity' => 10,
        ]);

        $now = Carbon::now();

        // 1. Create a Sale of 500 (accepted = 1 for GST)
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 500.00,
            'grand_total' => 500.00,
            'accepted' => 1,
            'created_at' => $now,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 100.00,
        ]);

        // 2. Create a Purchase of 300 (accepted = 1)
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 300.00,
            'grand_total' => 300.00,
            'accepted' => 1,
            'created_at' => $now,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 100.00,
        ]);

        // 3. Create an Expense Category and an Expense of 50
        $category = ExpenseCategory::create([
            'user_id' => $storeUser->id,
            'name' => 'Office Rent',
        ]);

        Expense::create([
            'user_id' => $storeUser->id,
            'expense_category_id' => $category->id,
            'amount' => 50.00,
            'date' => $now->toDateString(),
            'created_at' => $now,
        ]);

        // 4. Create a Sale Return of 150 (base = 150, no gst return for simplicity here)
        SaleReturn::create([
            'user_id' => $storeUser->id,
            'sale_id' => $sale->id,
            'return_no' => 'RET-00001',
            'return_date' => $now->toDateString(),
            'refund_amount' => 150.00,
            'gst_refund_amount' => 0.00,
            'refund_method' => 'Cash',
            'created_at' => $now,
        ]);

        // 5. Create a Purchase Return of 100 (base = 100)
        PurchaseReturn::create([
            'user_id' => $storeUser->id,
            'purchase_id' => $purchase->id,
            'return_no' => 'PRET-00001',
            'return_date' => $now->toDateString(),
            'refund_amount' => 100.00,
            'gst_refund_amount' => 0.00,
            'refund_method' => 'Cash',
            'created_at' => $now,
        ]);

        // Authenticate
        $this->actingAs($storeUser);

        $response = $this->get('/dashboard');
        $response->assertOk();

        // Calculate expected values:
        // Net sales: 500 - 150 = 350
        // Net purchases: 300 - 100 = 200
        // Expenses: 50
        // Net profit: 350 - 200 - 50 = 100

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('profitLossData', 6)
            ->where('profitLossData.5.sales', 350)
            ->where('profitLossData.5.purchases', 200)
            ->where('profitLossData.5.expenses', 50)
            ->where('profitLossData.5.profit', 100)
            ->where('totalCustomerDue', 500)
            ->where('totalSupplierDue', 300)
        );
    }
}
