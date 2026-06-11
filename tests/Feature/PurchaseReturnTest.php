<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\JournalEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_purchase_return_deducts_product_stock_and_logs_stock_movement(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Purchased Item',
            'sku' => 'PUR-ITEM',
            'price' => 40.00,
            'stock_quantity' => 10, // already incremented on purchase
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 80.00,
            'grand_total' => 80.00,
            'accepted' => 0,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 40.00,
            'unit_type' => 'Pcs',
        ]);

        $this->actingAs($storeUser);

        $response = $this->postJson('/purchase-return/store', [
            'purchase_id' => $purchase->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Cash',
            'reason' => 'Damaged on receipt',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $response->assertOk();

        // Product stock should go from 10 to 8
        $product->refresh();
        $this->assertEquals(8, $product->stock_quantity);

        // Check stock movement is logged as Deduction
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 2,
            'type' => 'Deduction',
            'reference_type' => 'PurchaseReturn',
        ]);
    }

    public function test_cannot_return_more_than_purchased_quantity(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Purchased Item',
            'sku' => 'PUR-ITEM',
            'price' => 40.00,
            'stock_quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 40.00,
            'grand_total' => 40.00,
            'accepted' => 0,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 40.00,
            'unit_type' => 'Pcs',
        ]);

        $this->actingAs($storeUser);

        // Attempting to return 2 when only 1 was purchased
        $response = $this->postJson('/purchase-return/store', [
            'purchase_id' => $purchase->id,
            'return_date' => '2026-06-11',
            'refund_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to record return: Returned quantity for product ' . $product->id . ' exceeds allowed maximum of 1.');
    }

    public function test_purchase_return_generates_correct_double_entry_postings(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Purchased Item',
            'sku' => 'PUR-ITEM',
            'price' => 100.00,
            'stock_quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100.00,
            'grand_total' => 118.00,
            'accepted' => 1, // GST invoice
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'unit_type' => 'Pcs',
            'sgst' => 9,
            'cgst' => 9,
        ]);

        $this->actingAs($storeUser);

        $response = $this->postJson('/purchase-return/store', [
            'purchase_id' => $purchase->id,
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
        // Debit: Accounts Payable (AP) = 118 (reducing liability)
        // Credit: Purchases = 100
        // Credit: GST Input (Asset) = 18 (reducing receivable)
        
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'debit',
            'amount' => 118.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'credit',
            'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'credit',
            'amount' => 18.00,
        ]);
    }

    public function test_can_download_purchase_return_pdf(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100.00,
            'grand_total' => 100.00,
            'accepted' => 0,
        ]);

        $return = PurchaseReturn::create([
            'user_id' => $storeUser->id,
            'purchase_id' => $purchase->id,
            'return_no' => 'PRET-00001',
            'return_date' => '2026-06-11',
            'refund_amount' => 100.00,
            'refund_method' => 'Cash',
        ]);

        $this->actingAs($storeUser);

        $response = $this->get("/purchase-return/{$return->id}/download-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_purchase_return_with_custom_due_deduction(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Purchased Item',
            'sku' => 'PUR-ITEM',
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100.00,
            'gst_amount' => 18.00,
            'grand_total' => 118.00,
            'paid' => 80.00,
            'payment_status' => 'Partial',
            'accepted' => 1, // GST invoice
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50.00,
            'unit_type' => 'Pcs',
            'sgst' => 9,
            'cgst' => 9,
        ]);

        $this->actingAs($storeUser);

        $response = $this->postJson('/purchase-return/store', [
            'purchase_id' => $purchase->id,
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

        // Verify purchase record updates
        $purchase->refresh();
        $this->assertEquals(50.00, $purchase->total_amount);
        $this->assertEquals(9.00, $purchase->gst_amount);
        $this->assertEquals(59.00, $purchase->grand_total);
        $this->assertEquals(41.00, $purchase->paid);
        $this->assertEquals('Partial', $purchase->payment_status);

        // Verify database returns record
        $this->assertDatabaseHas('purchase_returns', [
            'purchase_id' => $purchase->id,
            'due_deduction' => 20.00,
            'refund_amount' => 50.00,
            'gst_refund_amount' => 9.00,
        ]);

        // Verify Journal Entries
        // Debit: Accounts Payable (AP) = 20.00 (Due Deduction)
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'debit',
            'amount' => 20.00,
            'description' => 'Purchase Return #PRET-00001 for Bill #' . $purchase->id . ' (Applied to Bill Due)',
        ]);

        // Debit: Cash = 39.00 (Remaining Cash Refund)
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'debit',
            'amount' => 39.00,
            'description' => 'Purchase Return #PRET-00001 for Bill #' . $purchase->id . ' (Cash)',
        ]);

        // Credit: Purchases = 50.00
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'credit',
            'amount' => 50.00,
        ]);

        // Credit: GST Input = 9.00
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'PurchaseReturn',
            'type' => 'credit',
            'amount' => 9.00,
        ]);
    }
}
