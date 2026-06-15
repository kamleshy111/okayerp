<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use App\Models\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_view_sale_details(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'email' => 'customer@example.com',
        ]);

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

        $response = $this->actingAs($storeUser)->get("/sale/{$sale->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Sale/Show')
            ->has('sale')
            ->has('allocatedPayment')
        );
    }

    public function test_can_download_sale_pdf(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('sale manage');

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'email' => 'customer@example.com',
        ]);

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

        $response = $this->actingAs($storeUser)->get("/sale/{$sale->id}/download-pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
