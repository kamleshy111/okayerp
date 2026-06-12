<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SalePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePaymentLinkageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_creating_sale_creates_salepayment_record(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Client',
            'phone' => '1234567890',
            'email' => 'client1@example.com',
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

        $saleData = [
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 500.00,
            'payment_method' => 'UPI',
            'payment_status' => 'Partial',
            'accepted' => 1,
            'sale_items' => [
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

        $response = $this->actingAs($storeUser)->postJson('/sale/store', $saleData);
        $response->assertOk();

        // Verify sale was created
        $sale = Sale::first();
        $this->assertNotNull($sale);

        // Verify SalePayment record was automatically logged
        $this->assertDatabaseHas('sale_payments', [
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => 500.00,
            'payment_method' => 'UPI',
            'note' => "Payment for Sale Invoice #{$sale->id}",
            'accepted' => 1,
        ]);
    }

    public function test_updating_sale_updates_or_deletes_salepayment(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Client',
            'phone' => '1234567890',
            'email' => 'client2@example.com',
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

        // Create Sale with paid amount
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        // Create initial linked payment
        $payment = SalePayment::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'note' => "Payment for Sale Invoice #{$sale->id}",
            'accepted' => 1,
        ]);

        // Update Sale with different paid amount
        $updateData = [
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 1180.00, // Fully Paid
            'payment_method' => 'Card',
            'payment_status' => 'Paid',
            'accepted' => 1,
            'sale_items' => [
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

        $response = $this->actingAs($storeUser)->postJson("/sale/update/{$sale->id}", $updateData);
        $response->assertOk();

        // Verify SalePayment record was updated
        $this->assertDatabaseHas('sale_payments', [
            'id' => $payment->id,
            'amount' => 1180.00,
            'payment_method' => 'Card',
        ]);

        // Update paid amount to 0
        $updateData['paid'] = 0.00;
        $updateData['payment_status'] = 'Unpaid';

        $response = $this->actingAs($storeUser)->postJson("/sale/update/{$sale->id}", $updateData);
        $response->assertOk();

        // Verify SalePayment record was deleted since paid amount is 0
        $this->assertDatabaseMissing('sale_payments', [
            'id' => $payment->id,
        ]);
    }

    public function test_deleting_sale_deletes_salepayment(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Client',
            'phone' => '1234567890',
            'email' => 'client3@example.com',
        ]);

        // Create Sale
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        // Create linked payment
        $payment = SalePayment::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'note' => "Payment for Sale Invoice #{$sale->id}",
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->deleteJson("/sale/destroy/{$sale->id}");
        $response->assertOk();

        // Verify payment is deleted
        $this->assertDatabaseMissing('sale_payments', [
            'id' => $payment->id,
        ]);
    }
}
