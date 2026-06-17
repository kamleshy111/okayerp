<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchasePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_view_supplier_payment_history(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('payment supplier manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@example.com',
        ]);

        $purchasePayment = PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/paymentSupplier/{$supplier->id}/history");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SupplierPayment/History')
            ->has('supplier')
            ->has('history')
        );
    }

    public function test_can_download_supplier_payment_history_pdf(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('payment supplier manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@example.com',
        ]);

        $purchasePayment = PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/paymentSupplier/{$supplier->id}/history/download-pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
