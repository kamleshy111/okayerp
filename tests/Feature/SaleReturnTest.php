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
            'grand_total' => 100.00,
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
}
