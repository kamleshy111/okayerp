<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purchase;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_view_purchase_details(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@example.com',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-TEST',
            'purchase_date' => now()->toDateString(),
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/purchase/{$purchase->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Purchase/Show')
            ->has('purchase')
            ->has('allocatedPayment')
        );
    }

    public function test_can_download_purchase_pdf(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('purchase manage');

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@example.com',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-TEST',
            'purchase_date' => now()->toDateString(),
            'grand_total' => 1180.00,
            'total_amount' => 1000.00,
            'gst_amount' => 180.00,
            'paid' => 500.00,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get("/purchase/{$purchase->id}/download-pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
