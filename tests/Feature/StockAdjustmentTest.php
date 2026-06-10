<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Purchase;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles and permissions
        $this->seed(DatabaseSeeder::class);
    }

    public function test_stock_adjustment_endpoints_require_authentication_and_permissions(): void
    {
        // Guest redirect
        $response = $this->get('/stock-adjustment');
        $response->assertRedirect('/login');

        // User without store role redirect
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/stock-adjustment');
        $response->assertStatus(302);

        // Store user without product manage permission gets 403
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->syncPermissions([]); // Revoke all permissions
        $response = $this->actingAs($storeUser)->get('/stock-adjustment');
        $response->assertStatus(403);
    }

    public function test_creating_manual_addition_adjustment_increases_stock_and_logs_movement(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('product manage');

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Wrench',
            'sku' => 'SKU-54321',
            'price' => 10.00,
            'stock_quantity' => 50,
        ]);

        $response = $this->actingAs($storeUser)->postJson('/stock-adjustment/store', [
            'product_id' => $product->id,
            'quantity' => 20,
            'type' => 'Addition',
            'reason' => 'Physical Count Correction',
            'remarks' => 'Found 20 extra wrenches during annual audit',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Stock adjusted successfully!');

        // Assert product stock increased
        $product->refresh();
        $this->assertEquals(70, $product->stock_quantity);

        // Assert log entry exists
        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $storeUser->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'type' => 'Addition',
            'reference_type' => 'Manual',
            'reason' => 'Physical Count Correction',
            'remarks' => 'Found 20 extra wrenches during annual audit',
        ]);
    }

    public function test_creating_manual_deduction_adjustment_decreases_stock_and_logs_movement(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('product manage');

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Wrench',
            'sku' => 'SKU-54321',
            'price' => 10.00,
            'stock_quantity' => 50,
        ]);

        $response = $this->actingAs($storeUser)->postJson('/stock-adjustment/store', [
            'product_id' => $product->id,
            'quantity' => 5,
            'type' => 'Deduction',
            'reason' => 'Damaged Goods',
            'remarks' => '5 rusted items discarded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Stock adjusted successfully!');

        // Assert product stock decreased
        $product->refresh();
        $this->assertEquals(45, $product->stock_quantity);

        // Assert log entry exists
        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $storeUser->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'type' => 'Deduction',
            'reference_type' => 'Manual',
            'reason' => 'Damaged Goods',
            'remarks' => '5 rusted items discarded',
        ]);
    }

    public function test_cannot_manually_deduct_stock_below_zero(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('product manage');

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Wrench',
            'sku' => 'SKU-54321',
            'price' => 10.00,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($storeUser)->postJson('/stock-adjustment/store', [
            'product_id' => $product->id,
            'quantity' => 10, // Exceeds 5
            'type' => 'Deduction',
            'reason' => 'Theft/Loss',
        ]);

        $response->assertStatus(422);
        
        // Assert stock level remained unchanged
        $product->refresh();
        $this->assertEquals(5, $product->stock_quantity);
    }

    public function test_sale_creation_automatically_logs_stock_deduction(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Client',
            'email' => 'john@client.com',
            'phone' => '1122334455',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Wrench',
            'sku' => 'SKU-54321',
            'price' => 10.00,
            'stock_quantity' => 100,
        ]);

        $response = $this->actingAs($storeUser)->postJson('/sale/store', [
            'customer_id' => $customer->id,
            'total_amount' => 50.00,
            'GstAmount' => 0.00,
            'grand_total' => 50.00,
            'paid' => 50.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
            'accepted' => 1,
            'sale_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_type' => 'Pcs',
                    'price' => 10.00,
                    'baseAmount' => 50.00,
                    'sgst' => 0,
                    'cgst' => 0,
                ]
            ]
        ]);

        $response->assertOk();

        $sale = Sale::first();

        // Assert database has correct stock movement logged
        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $storeUser->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'type' => 'Deduction',
            'reference_type' => 'Sale',
            'reference_id' => $sale->id,
            'reason' => "Sale Invoice #{$sale->id}",
        ]);
    }

    public function test_purchase_creation_automatically_logs_stock_addition(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier XYZ',
            'email' => 'xyz@supplier.com',
            'phone' => '5544332211',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Wrench',
            'sku' => 'SKU-54321',
            'price' => 10.00,
            'stock_quantity' => 10,
        ]);

        $response = $this->actingAs($storeUser)->postJson('/purchase/store', [
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-9988',
            'purchase_date' => '2026-06-10',
            'total_amount' => 200.00,
            'GstAmount' => 0.00,
            'grand_total' => 200.00,
            'paid' => 200.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
            'accepted' => 1,
            'purchase_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                    'unit_type' => 'Pcs',
                    'price' => 10.00,
                    'baseAmount' => 200.00,
                    'sgst' => 0,
                    'cgst' => 0,
                ]
            ]
        ]);

        $response->assertOk();

        $purchase = Purchase::first();

        // Assert database has correct stock movement logged
        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $storeUser->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'type' => 'Addition',
            'reference_type' => 'Purchase',
            'reference_id' => $purchase->id,
            'reason' => "Purchase Bill #{$purchase->id}",
        ]);
    }

    public function test_stock_logs_are_strictly_scoped_per_tenant(): void
    {
        $storeUserA = User::factory()->create(['role' => 'store']);
        $storeUserA->givePermissionTo('product manage');
        $productA = Product::create(['user_id' => $storeUserA->id, 'name' => 'Prod A', 'sku' => '1', 'price' => 1]);
        $movementA = StockMovement::create([
            'user_id' => $storeUserA->id,
            'product_id' => $productA->id,
            'quantity' => 10,
            'type' => 'Addition',
            'reference_type' => 'Manual',
            'reason' => 'Auditing A',
        ]);

        $storeUserB = User::factory()->create(['role' => 'store']);
        $storeUserB->givePermissionTo('product manage');
        $productB = Product::create(['user_id' => $storeUserB->id, 'name' => 'Prod B', 'sku' => '2', 'price' => 1]);
        $movementB = StockMovement::create([
            'user_id' => $storeUserB->id,
            'product_id' => $productB->id,
            'quantity' => 20,
            'type' => 'Addition',
            'reference_type' => 'Manual',
            'reason' => 'Auditing B',
        ]);

        // A visits index
        $response = $this->actingAs($storeUserA)->get('/stock-adjustment');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('StockAdjustment/Index')
            ->has('movements', 1)
            ->where('movements.0.id', $movementA->id)
        );
    }
}
