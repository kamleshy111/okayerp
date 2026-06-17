<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_view_customer_payment_history(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('payments customer manage'); // assuming permission name matches indexes

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'email' => 'customer@example.com',
        ]);

        $salePayment = SalePayment::create([
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/paymentsCustomer/{$customer->id}/history");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('CustomerPayment/History')
            ->has('customer')
            ->has('history')
        );
    }

    public function test_can_download_customer_payment_history_pdf(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('payments customer manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'email' => 'customer@example.com',
        ]);

        $salePayment = SalePayment::create([
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/paymentsCustomer/{$customer->id}/history/download-pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
