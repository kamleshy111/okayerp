<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePaymentLinkageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_creating_purchase_creates_purchasepayment_record(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Mega Supplier',
            'phone' => '1234567890',
            'email' => 'supplier1@example.com',
        ]);

        $category = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'category_id' => $category->id,
            'name' => 'LED Monitor',
            'sku' => 'MON-123',
            'price' => 1000.00,
        ]);

        $purchaseData = [
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-999',
            'purchase_date' => now()->toDateString(),
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 500.00,
            'payment_method' => 'UPI',
            'payment_status' => 'Partial',
            'accepted' => 1,
            'purchase_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_type' => 'Pcs',
                    'price' => 1000.00,
                    'baseAmount' => 1000.00,
                    'sgst' => 9,
                    'cgst' => 9,
                ]
            ]
        ];

        $response = $this->actingAs($storeUser)->postJson('/purchase/store', $purchaseData);
        $response->assertOk();

        // Verify purchase was created
        $purchase = Purchase::first();
        $this->assertNotNull($purchase);

        // Verify PurchasePayment record was automatically logged
        $this->assertDatabaseHas('purchase_payments', [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'amount' => 500.00,
            'payment_method' => 'UPI',
            'note' => "Payment for Purchase Invoice #{$purchase->id}",
            'accepted' => 1,
        ]);
    }

    public function test_updating_purchase_updates_or_deletes_purchasepayment(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Mega Supplier',
            'phone' => '1234567890',
            'email' => 'supplier2@example.com',
        ]);

        $category = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'category_id' => $category->id,
            'name' => 'LED Monitor',
            'sku' => 'MON-123',
            'price' => 1000.00,
        ]);

        // Create Purchase with paid amount
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-888',
            'purchase_date' => now()->toDateString(),
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        // Create initial linked payment
        $payment = PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'note' => "Payment for Purchase Invoice #{$purchase->id}",
            'accepted' => 1,
        ]);

        // Update Purchase with different paid amount
        $updateData = [
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-888',
            'purchase_date' => now()->toDateString(),
            'transport' => 0.00,
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 1180.00, // Fully Paid
            'payment_method' => 'Card',
            'payment_status' => 'Paid',
            'accepted' => 1,
            'purchase_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_type' => 'Pcs',
                    'price' => 1000.00,
                    'baseAmount' => 1000.00,
                    'sgst' => 9,
                    'cgst' => 9,
                ]
            ]
        ];

        $response = $this->actingAs($storeUser)->postJson("/purchase/update/{$purchase->id}", $updateData);
        $response->assertOk();

        // Verify PurchasePayment record was updated
        $this->assertDatabaseHas('purchase_payments', [
            'id' => $payment->id,
            'amount' => 1180.00,
            'payment_method' => 'Card',
        ]);

        // Update paid amount to 0
        $updateData['paid'] = 0.00;
        $updateData['payment_status'] = 'Unpaid';

        $response = $this->actingAs($storeUser)->postJson("/purchase/update/{$purchase->id}", $updateData);
        $response->assertOk();

        // Verify PurchasePayment record was deleted since paid amount is 0
        $this->assertDatabaseMissing('purchase_payments', [
            'id' => $payment->id,
        ]);
    }

    public function test_deleting_purchase_deletes_purchasepayment(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Mega Supplier',
            'phone' => '1234567890',
            'email' => 'supplier3@example.com',
        ]);

        // Create Purchase
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-777',
            'purchase_date' => now()->toDateString(),
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        // Create linked payment
        $payment = PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'note' => "Payment for Purchase Invoice #{$purchase->id}",
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->deleteJson("/purchase/destroy/{$purchase->id}");
        $response->assertOk();

        // Verify payment is deleted
        $this->assertDatabaseMissing('purchase_payments', [
            'id' => $payment->id,
        ]);
    }
}
