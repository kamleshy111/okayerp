<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles and permissions
        $this->seed(DatabaseSeeder::class);
    }

    public function test_estimate_endpoints_require_authentication_and_permissions(): void
    {
        // Guests cannot access index
        $response = $this->get('/estimate');
        $response->assertRedirect('/login');

        // User without store role is redirected (302)
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/estimate');
        $response->assertStatus(302);

        // User with store role but without sale manage permission is forbidden (403)
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->syncPermissions([]); // Revoke all auto-assigned permissions
        $response = $this->actingAs($storeUser)->get('/estimate');
        $response->assertStatus(403);
    }

    public function test_creating_an_estimate_does_not_deduct_product_stock(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Widget',
            'sku' => '12345',
            'price' => 50.00,
            'stock_quantity' => 100,
        ]);

        // Post request to store estimate
        $response = $this->actingAs($storeUser)->postJson('/estimate/store', [
            'customer_id' => $customer->id,
            'estimate_date' => '2026-06-10',
            'expiry_date' => '2026-07-10',
            'discount' => 10.00,
            'accepted' => 1,
            'notes' => 'Testing estimate creation',
            'total_amount' => 50.00,
            'GstAmount' => 0.00,
            'grand_total' => 40.00,
            'estimate_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_type' => 'Pcs',
                    'price' => 5.00,
                    'baseAmount' => 50.00,
                    'sgst' => 0,
                    'cgst' => 0,
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Quotation added successfully.');

        // Assert estimate and estimate item exist in DB
        $this->assertDatabaseHas('estimates', [
            'customer_id' => $customer->id,
            'grand_total' => 40.00,
            'status' => 'Draft',
        ]);

        $estimate = Estimate::first();
        $this->assertDatabaseHas('estimate_items', [
            'estimate_id' => $estimate->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Assert stock has NOT been deducted
        $product->refresh();
        $this->assertEquals(100, $product->stock_quantity);
    }

    public function test_estimate_list_is_scoped_per_store(): void
    {
        $storeUserA = User::factory()->create(['role' => 'store']);
        $storeUserA->givePermissionTo('sale manage');
        $customerA = Customer::create(['user_id' => $storeUserA->id, 'name' => 'Cust A', 'phone' => '1', 'email' => 'a@a.com']);
        $estimateA = Estimate::create([
            'customer_id' => $customerA->id,
            'estimate_no' => 'EST-2026-0001',
            'estimate_date' => '2026-06-10',
            'grand_total' => 100.00,
        ]);

        $storeUserB = User::factory()->create(['role' => 'store']);
        $storeUserB->givePermissionTo('sale manage');
        $customerB = Customer::create(['user_id' => $storeUserB->id, 'name' => 'Cust B', 'phone' => '2', 'email' => 'b@b.com']);
        $estimateB = Estimate::create([
            'customer_id' => $customerB->id,
            'estimate_no' => 'EST-2026-0002',
            'estimate_date' => '2026-06-10',
            'grand_total' => 200.00,
        ]);

        // A views estimates list
        $response = $this->actingAs($storeUserA)->get('/estimate');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Estimate/Index')
            ->has('estimates', 1)
            ->where('estimates.0.id', $estimateA->id)
        );
    }

    public function test_converting_estimate_to_sale_marks_estimate_as_invoiced_and_deducts_stock(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Widget',
            'sku' => '12345',
            'price' => 50.00,
            'stock_quantity' => 100,
        ]);

        $estimate = Estimate::create([
            'customer_id' => $customer->id,
            'estimate_no' => 'EST-2026-0001',
            'estimate_date' => '2026-06-10',
            'grand_total' => 500.00,
            'status' => 'Draft',
        ]);

        $estimateItem = EstimateItem::create([
            'estimate_id' => $estimate->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 50.00,
            'base_price' => 500.00,
        ]);

        // Now convert by submitting a sale with estimate_id reference
        $response = $this->actingAs($storeUser)->postJson('/sale/store', [
            'customer_id' => $customer->id,
            'estimate_id' => $estimate->id,
            'total_amount' => 500.00,
            'GstAmount' => 0.00,
            'grand_total' => 500.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
            'accepted' => 1,
            'sale_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_type' => 'Pcs',
                    'price' => 50.00,
                    'baseAmount' => 500.00,
                    'sgst' => 0,
                    'cgst' => 0,
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'sale added successfully.');

        // Assert sale was created with correct estimate_id
        $this->assertDatabaseHas('sales', [
            'estimate_id' => $estimate->id,
            'grand_total' => 500.00,
        ]);

        // Assert estimate status was changed to Invoiced
        $estimate->refresh();
        $this->assertEquals('Invoiced', $estimate->status);

        // Assert stock was decremented
        $product->refresh();
        $this->assertEquals(90, $product->stock_quantity);
    }
}
