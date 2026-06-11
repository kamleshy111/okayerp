<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\AuditLog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_creating_product_generates_create_audit_log(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $this->actingAs($storeUser);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'price' => 99.99,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'model_type' => Product::class,
            'model_id' => $product->id,
            'user_id' => $storeUser->id,
        ]);

        $log = AuditLog::first();
        $this->assertNotNull($log->new_values);
        $this->assertEquals('Test Product', $log->new_values['name']);
        $this->assertEquals('TEST-SKU', $log->new_values['sku']);
    }

    public function test_updating_product_generates_update_audit_log_with_diff(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Original Name',
            'sku' => 'SKU-1',
            'price' => 10.00,
            'stock_quantity' => 5,
        ]);

        // Clear initial CREATE log to isolate UPDATE log
        AuditLog::query()->delete();

        $this->actingAs($storeUser);

        $product->update([
            'name' => 'Updated Name',
            'price' => 15.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'model_type' => Product::class,
            'model_id' => $product->id,
        ]);

        $log = AuditLog::first();
        $this->assertNotNull($log->old_values);
        $this->assertNotNull($log->new_values);
        $this->assertEquals('Original Name', $log->old_values['name']);
        $this->assertEquals('Updated Name', $log->new_values['name']);
        $this->assertEquals(10.00, $log->old_values['price']);
        $this->assertEquals(15.00, $log->new_values['price']);
        // stock_quantity did not change, so it should not be in the diff
        $this->assertArrayNotHasKey('stock_quantity', $log->old_values);
    }

    public function test_deleting_product_generates_delete_audit_log(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'To Delete',
            'sku' => 'SKU-2',
            'price' => 5.00,
            'stock_quantity' => 1,
        ]);

        AuditLog::query()->delete();

        $this->actingAs($storeUser);

        $product->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE',
            'model_type' => Product::class,
            'model_id' => $product->id,
        ]);

        $log = AuditLog::first();
        $this->assertNotNull($log->old_values);
        $this->assertEquals('To Delete', $log->old_values['name']);
    }

    public function test_store_user_can_only_see_their_own_audit_logs(): void
    {
        $storeUser1 = User::factory()->create(['role' => 'store']);
        $storeUser2 = User::factory()->create(['role' => 'store']);

        // Generate logs for user 1
        AuditLog::create([
            'user_id' => $storeUser1->id,
            'action' => 'CREATE',
            'model_type' => Product::class,
            'model_id' => 1,
            'new_values' => ['name' => 'Product 1'],
        ]);

        // Generate logs for user 2
        AuditLog::create([
            'user_id' => $storeUser2->id,
            'action' => 'CREATE',
            'model_type' => Product::class,
            'model_id' => 2,
            'new_values' => ['name' => 'Product 2'],
        ]);

        // Access as user 1
        $response = $this->actingAs($storeUser1)->get('/audit-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AuditLog/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.model_id', 1)
        );
    }

    public function test_admin_user_can_see_all_audit_logs(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $storeUser = User::factory()->create(['role' => 'store']);

        // Assign admin role to user
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin');
        $adminUser->assignRole($adminRole);

        AuditLog::create([
            'user_id' => $storeUser->id,
            'action' => 'CREATE',
            'model_type' => Product::class,
            'model_id' => 1,
        ]);

        AuditLog::create([
            'user_id' => $adminUser->id,
            'action' => 'DELETE',
            'model_type' => Product::class,
            'model_id' => 2,
        ]);

        $response = $this->actingAs($adminUser)->get('/audit-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AuditLog/Index')
            ->has('logs.data', 2)
        );
    }

    public function test_guest_cannot_access_audit_logs(): void
    {
        $response = $this->get('/audit-logs');
        $response->assertRedirect('/login');
    }

    public function test_attempting_to_access_private_ledger_payments_without_correct_session_passcode_returns_403(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Client',
            'email' => 'john@client.com',
            'phone' => '1234567890',
        ]);

        // Attempting to post payment without setting private_ledger_unlocked = true in session
        $response = $this->actingAs($storeUser)->postJson('/private-ledger/payment', [
            'customer_id' => $customer->id,
            'amount' => 500,
            'payment_date' => '2026-06-11',
            'payment_method' => 'Cash',
        ]);

        $response->assertStatus(403);
    }
}
