<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\JournalEntry;
use App\Models\SalePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sale_return_restores_product_stock_and_logs_stock_movement(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Returnable Item',
            'sku' => 'RET-ITEM',
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'grand_total' => 100.00,
            'accepted' => 0, // Private/Non-GST
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50.00,
            'unit_type' => 'Pcs',
        ]);

        // Stock goes down during sales creation (simulating that manually)
        $product->stock_quantity -= 2;
        $product->save();

        $this->actingAs($storeUser);

        $response = $this->postJson('/sale-return/store', [
            'sale_id' => $sale->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Cash',
            'reason' => 'Defective',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $response->assertOk();

        // Check product stock is restored
        $product->refresh();
        $this->assertEquals(10, $product->stock_quantity);

        // Check stock movement is logged
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 2,
            'type' => 'Addition',
            'reference_type' => 'SaleReturn',
        ]);
    }

    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Returnable Item',
            'sku' => 'RET-ITEM',
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 50.00,
            'grand_total' => 50.00,
            'accepted' => 0,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50.00,
            'unit_type' => 'Pcs',
        ]);

        $this->actingAs($storeUser);

        // Attempting to return 2 when only 1 was sold
        $response = $this->postJson('/sale-return/store', [
            'sale_id' => $sale->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $response->assertStatus(500); // Throws Exception which resolves to 500
        $response->assertJsonPath('message', 'Failed to record return: Returned quantity for product ' . $product->id . ' exceeds allowed maximum of 1.');
    }

    public function test_sale_return_generates_correct_double_entry_postings(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Returnable Item',
            'sku' => 'RET-ITEM',
            'price' => 100.00,
            'stock_quantity' => 10,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'grand_total' => 118.00,
            'accepted' => 1, // GST invoice
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'unit_type' => 'Pcs',
            'sgst' => 9,
            'cgst' => 9,
        ]);

        $this->actingAs($storeUser);

        $response = $this->postJson('/sale-return/store', [
            'sale_id' => $sale->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Store Credit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response->assertOk();

        // Total base = 100, GST (18%) = 18. Grand Total = 118.
        // Debit: Sales = 100
        // Debit: GST Output (Liability) = 18
        // Credit: Accounts Receivable (AR) = 118
        
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'debit',
            'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'debit',
            'amount' => 18.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'credit',
            'amount' => 118.00,
        ]);
    }

    public function test_can_download_return_pdf_invoice(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'grand_total' => 100.00,
            'accepted' => 0,
        ]);

        $return = SaleReturn::create([
            'user_id' => $storeUser->id,
            'sale_id' => $sale->id,
            'return_no' => 'RET-00001',
            'return_date' => '2026-06-11',
            'refund_amount' => 100.00,
            'refund_method' => 'Cash',
        ]);

        $this->actingAs($storeUser);

        $response = $this->get("/sale-return/{$return->id}/download-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sale_return_with_custom_due_deduction(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Returnable Item',
            'sku' => 'RET-ITEM',
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'gst_amount' => 18.00,
            'grand_total' => 118.00,
            'paid' => 80.00,
            'payment_status' => 'Partial',
            'accepted' => 1, // GST invoice
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50.00,
            'unit_type' => 'Pcs',
            'sgst' => 9,
            'cgst' => 9,
        ]);

        $this->actingAs($storeUser);

        $response = $this->postJson('/sale-return/store', [
            'sale_id' => $sale->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Cash',
            'due_deduction' => 20.00,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response->assertOk();

        // Verify sale record updates
        $sale->refresh();
        $this->assertEquals(100.00, $sale->total_amount);
        $this->assertEquals(18.00, $sale->gst_amount);
        $this->assertEquals(118.00, $sale->grand_total);
        $this->assertEquals(80.00, $sale->paid);
        $this->assertEquals('Partial', $sale->payment_status);

        // Verify database returns record
        $this->assertDatabaseHas('sale_returns', [
            'sale_id' => $sale->id,
            'due_deduction' => 20.00,
            'refund_amount' => 50.00,
            'gst_refund_amount' => 9.00,
        ]);

        // Verify Journal Entries
        // Debit: Sales = 50.00
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'debit',
            'amount' => 50.00,
        ]);

        // Debit: GST Output = 9.00
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'debit',
            'amount' => 9.00,
        ]);

        $saleReturn = SaleReturn::latest('id')->first();
        
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'credit',
            'amount' => 20.00,
            'description' => 'Sale Return #' . $saleReturn->return_no . ' for Invoice #' . $sale->id . ' (Applied to Invoice Due)',
        ]);

        // Credit: Cash/Bank = 39.00 (Net Refund)
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SaleReturn',
            'type' => 'credit',
            'amount' => 39.00,
            'description' => 'Sale Return #' . $saleReturn->return_no . ' for Invoice #' . $sale->id . ' (Cash)',
        ]);
    }

    public function test_sale_returns_show_up_in_customer_payments_list(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'grand_total' => 100.00,
            'accepted' => 1,
        ]);

        // Create a sale payment on 2026-06-14 (earlier)
        $payment1 = SalePayment::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => 50.00,
            'payment_date' => '2026-06-14',
            'payment_method' => 'UPI',
            'accepted' => 1,
        ]);

        // Create a return on 2026-06-15 (middle)
        $return = SaleReturn::create([
            'user_id' => $storeUser->id,
            'sale_id' => $sale->id,
            'return_no' => 'RET-99999',
            'return_date' => '2026-06-15',
            'refund_amount' => 60.00,
            'gst_refund_amount' => 10.80,
            'refund_method' => 'Cash',
        ]);

        // Create a sale payment on 2026-06-16 (later)
        $payment2 = SalePayment::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => 30.00,
            'payment_date' => '2026-06-16',
            'payment_method' => 'Card',
            'accepted' => 1,
        ]);

        $this->actingAs($storeUser);

        $response = $this->get('/paymentsCustomer');
        $response->assertOk();
        
        $response->assertInertia(fn ($page) => $page
            ->component('CustomerPayment/Index')
            ->has('customers', 3)
            ->has('customers.0', fn ($page) => $page
                ->where('amount', 30)
                ->where('payment_date', '2026-06-16')
                ->where('source', 'Due Clearance')
                ->etc()
            )
            ->has('customers.1', fn ($page) => $page
                ->where('amount', -70.8)
                ->where('payment_date', '2026-06-15')
                ->where('source', 'Return')
                ->etc()
            )
            ->has('customers.2', fn ($page) => $page
                ->where('amount', 50)
                ->where('payment_date', '2026-06-14')
                ->where('source', 'Sale')
                ->etc()
            )
        );

        $responseHistory = $this->get('/paymentsCustomer/' . $customer->id . '/history');
        $responseHistory->assertOk();
        $responseHistory->assertInertia(fn ($page) => $page
            ->component('CustomerPayment/History')
            ->has('customer')
            ->where('customer.id', $customer->id)
            ->has('history', 3)
            ->has('history.0', fn ($page) => $page
                ->where('amount', 30)
                ->where('payment_date', '2026-06-16')
                ->where('source', 'Due Clearance (Invoice #' . $sale->id . ')')
                ->etc()
            )
            ->has('history.1', fn ($page) => $page
                ->where('amount', -70.8)
                ->where('payment_date', '2026-06-15')
                ->where('source', 'Return (Invoice #' . $sale->id . ')')
                ->etc()
            )
            ->has('history.2', fn ($page) => $page
                ->where('amount', 50)
                ->where('payment_date', '2026-06-14')
                ->where('source', 'Sale (Invoice #' . $sale->id . ')')
                ->etc()
            )
        );
    }

    public function test_get_customer_purchased_items_returns_only_available_quantities(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Client',
            'email' => 'jane@client.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Item X',
            'sku' => 'ITM-X',
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'grand_total' => 100.00,
            'accepted' => 1,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 50.00,
            'unit_type' => 'Pcs',
        ]);

        // Create a return of 2 items
        $return = SaleReturn::create([
            'user_id' => $storeUser->id,
            'sale_id' => $sale->id,
            'return_no' => 'RET-00002',
            'return_date' => '2026-06-11',
            'refund_amount' => 100.00,
            'refund_method' => 'Cash',
        ]);

        SaleReturnItem::create([
            'sale_return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $this->actingAs($storeUser);

        $response = $this->get("/sale-return/customer/{$customer->id}/purchased-items");
        $response->assertOk();

        // Should return 1 item with available_qty = 3 (5 sold - 2 returned)
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.product_id', $product->id);
        $response->assertJsonPath('0.available_qty', 3);
    }
}
