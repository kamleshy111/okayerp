<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SalePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_non_gst_sales_are_hidden_from_default_sales_index(): void
    {
        // Create store owner
        $storeUser = User::factory()->create([
            'role' => 'store',
            'ledger_pin' => '1234',
        ]);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        // Create accepted (GST) sale
        $gstSale = Sale::create([
            'customer_id' => $customer->id,
            'grand_total' => 100.00,
            'accepted' => 1,
        ]);

        // Create non-accepted (private/Non-GST) sale
        $privateSale = Sale::create([
            'customer_id' => $customer->id,
            'grand_total' => 200.00,
            'accepted' => 0,
        ]);

        // Access the standard sales index page
        $response = $this->actingAs($storeUser)->get('/sale');

        $response->assertOk();
        
        // Assert standard sales list contains the GST sale, but NOT the private sale
        $response->assertInertia(fn ($page) => $page
            ->component('Sale/Index')
            ->has('sales', 1)
            ->where('sales.0.id', $gstSale->id)
        );
    }

    public function test_non_gst_purchases_are_hidden_from_default_purchases_index(): void
    {
        $storeUser = User::factory()->create([
            'role' => 'store',
            'ledger_pin' => '1234',
        ]);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Jane Supplier',
            'email' => 'jane@example.com',
            'phone' => '0987654321',
        ]);

        // Create accepted (GST) purchase
        $gstPurchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'grand_total' => 500.00,
            'accepted' => 1,
        ]);

        // Create private purchase
        $privatePurchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'grand_total' => 600.00,
            'accepted' => 0,
        ]);

        $response = $this->actingAs($storeUser)->get('/purchase');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Purchase/Index')
            ->has('purchases', 1)
            ->where('purchases.0.id', $gstPurchase->id)
        );
    }

    public function test_cannot_access_private_ledger_without_unlocking(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);

        // Request private ledger index without unlocking
        $response = $this->actingAs($storeUser)->get('/private-ledger');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Private/Index')
            ->where('unlocked', false)
            ->where('hasPin', true)
        );
    }

    public function test_can_unlock_private_ledger_with_correct_pin(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);

        $response = $this->actingAs($storeUser)->postJson('/private-ledger/unlock', [
            'pin' => '1234',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Private ledger unlocked successfully.');
        $this->assertTrue(session('private_ledger_unlocked'));
    }

    public function test_cannot_unlock_private_ledger_with_incorrect_pin(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);

        $response = $this->actingAs($storeUser)->postJson('/private-ledger/unlock', [
            'pin' => '9999',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('pin');
        $this->assertNull(session('private_ledger_unlocked'));
    }

    public function test_can_lock_private_ledger(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);
        session(['private_ledger_unlocked' => true]);

        $response = $this->actingAs($storeUser)->post('/private-ledger/lock');

        $response->assertRedirect('/private-ledger');
        $this->assertNull(session('private_ledger_unlocked'));
    }

    public function test_private_ledger_returns_data_when_unlocked(): void
    {
        $storeUser = User::factory()->create(['role' => 'store', 'ledger_pin' => '1234']);
        session(['private_ledger_unlocked' => true]);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $privateSale = Sale::create([
            'customer_id' => $customer->id,
            'grand_total' => 250.00,
            'accepted' => 0,
        ]);

        $privatePayment = SalePayment::create([
            'customer_id' => $customer->id,
            'amount' => 150.00,
            'accepted' => 0,
            'payment_date' => '2026-06-09',
            'payment_method' => 'Cash',
        ]);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'phone' => '1234509876',
            'email' => 'supplier@example.com',
        ]);

        $privatePurchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'grand_total' => 300.00,
            'accepted' => 0,
        ]);

        $response = $this->actingAs($storeUser)->get('/private-ledger');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Private/Index')
            ->where('unlocked', true)
            ->where('hasPin', true)
            ->has('sales', 1)
            ->where('sales.0.id', $privateSale->id)
            ->has('payments', 1)
            ->where('payments.0.amount', 150)
            ->has('purchases', 1)
            ->where('purchases.0.id', $privatePurchase->id)
        );
    }
}
