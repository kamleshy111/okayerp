<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SalePayment;
use App\Models\PurchasePayment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sales_are_correctly_categorized_into_aging_buckets(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '1234567890',
        ]);

        // Sale 1: 45 days ago, grand_total = 1000, paid = 200 (outstanding = 800)
        $sale1 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 1000.00,
            'paid' => 200.00,
            'accepted' => 1,
        ]);
        $sale1->timestamps = false;
        $sale1->created_at = Carbon::now()->subDays(45);
        $sale1->save();

        // Sale 2: 10 days ago, grand_total = 500, paid = 0 (outstanding = 500)
        $sale2 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 500.00,
            'paid' => 0.00,
            'accepted' => 1,
        ]);
        $sale2->timestamps = false;
        $sale2->created_at = Carbon::now()->subDays(10);
        $sale2->save();

        // Sale 3: 95 days ago, grand_total = 400, paid = 100 (outstanding = 300)
        $sale3 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 400.00,
            'paid' => 100.00,
            'accepted' => 1,
        ]);
        $sale3->timestamps = false;
        $sale3->created_at = Carbon::now()->subDays(95);
        $sale3->save();

        $response = $this->actingAs($storeUser)->get('/reports/aging');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Aging')
            ->has('arData', 1)
            ->where('arData.0.total_due', 1600)
            ->where('arData.0.bucket_0_30', 500)
            ->where('arData.0.bucket_31_60', 800)
            ->where('arData.0.bucket_61_90', 0)
            ->where('arData.0.bucket_90_plus', 300)
            ->where('arSummary.total_receivables', 1600)
            ->where('arSummary.total_0_30', 500)
            ->where('arSummary.total_31_60', 800)
            ->where('arSummary.total_90_plus', 300)
        );
    }

    public function test_customer_general_payments_reduce_outstanding_via_fifo(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '1234567890',
        ]);

        // Sale 1: 45 days ago, grand_total = 1000, paid = 200 (outstanding = 800)
        $sale1 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 1000.00,
            'paid' => 200.00,
            'accepted' => 1,
        ]);
        $sale1->timestamps = false;
        $sale1->created_at = Carbon::now()->subDays(45);
        $sale1->save();

        // Sale 2: 10 days ago, grand_total = 500, paid = 0 (outstanding = 500)
        $sale2 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 500.00,
            'paid' => 0.00,
            'accepted' => 1,
        ]);
        $sale2->timestamps = false;
        $sale2->created_at = Carbon::now()->subDays(10);
        $sale2->save();

        // Sale payment: 600 (covers 600 of the 800 outstanding of Sale 1, leaving 200 outstanding on Sale 1)
        SalePayment::create([
            'customer_id' => $customer->id,
            'amount' => 600.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        $response = $this->actingAs($storeUser)->get('/reports/aging');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Aging')
            ->has('arData', 1)
            ->where('arData.0.total_due', 700)
            ->where('arData.0.bucket_0_30', 500)
            ->where('arData.0.bucket_31_60', 200)
            ->where('arSummary.total_receivables', 700)
        );
    }

    public function test_supplier_purchases_and_payments_fifo(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '9876543210',
        ]);

        // Purchase 1: 40 days ago, grand_total = 1200, paid = 200 (outstanding = 1000)
        $purchase1 = new Purchase([
            'supplier_id' => $supplier->id,
            'grand_total' => 1200.00,
            'paid' => 200.00,
            'accepted' => 1,
            'purchase_date' => Carbon::now()->subDays(40)->toDateString(),
        ]);
        $purchase1->save();

        // Purchase 2: 15 days ago, grand_total = 800, paid = 0 (outstanding = 800)
        $purchase2 = new Purchase([
            'supplier_id' => $supplier->id,
            'grand_total' => 800.00,
            'paid' => 0.00,
            'accepted' => 1,
            'purchase_date' => Carbon::now()->subDays(15)->toDateString(),
        ]);
        $purchase2->save();

        // Purchase Payment: 700 (covers 700 of the 1000 outstanding of Purchase 1, leaving 300 outstanding on Purchase 1)
        PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'amount' => 700.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($storeUser)->get('/reports/aging');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Aging')
            ->has('apData', 1)
            ->where('apData.0.total_due', 1100)
            ->where('apData.0.bucket_0_30', 800)
            ->where('apData.0.bucket_31_60', 300)
            ->where('apSummary.total_payables', 1100)
        );
    }

    public function test_aging_report_tenant_isolation(): void
    {
        $userA = User::factory()->create(['role' => 'store']);
        $userB = User::factory()->create(['role' => 'store']);

        $customerA = Customer::create([
            'user_id' => $userA->id,
            'name' => 'Customer A',
            'email' => 'customerA@example.com',
        ]);

        $customerB = Customer::create([
            'user_id' => $userB->id,
            'name' => 'Customer B',
            'email' => 'customerB@example.com',
        ]);

        // Sale for Customer A
        $saleA = new Sale([
            'customer_id' => $customerA->id,
            'grand_total' => 500.00,
            'paid' => 0.00,
            'accepted' => 1,
        ]);
        $saleA->timestamps = false;
        $saleA->created_at = Carbon::now()->subDays(10);
        $saleA->save();

        // Sale for Customer B
        $saleB = new Sale([
            'customer_id' => $customerB->id,
            'grand_total' => 600.00,
            'paid' => 0.00,
            'accepted' => 1,
        ]);
        $saleB->timestamps = false;
        $saleB->created_at = Carbon::now()->subDays(10);
        $saleB->save();

        // Acting as User A, should only see Customer A
        $response = $this->actingAs($userA)->get('/reports/aging');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Aging')
            ->has('arData', 1)
            ->where('arData.0.name', 'Customer A')
        );
    }

    public function test_purchase_edit_page_receives_allocated_general_payments(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '9876543210',
        ]);

        // Purchase 1: grand_total = 1000, paid = 200 (outstanding = 800)
        $purchase1 = new Purchase([
            'supplier_id' => $supplier->id,
            'grand_total' => 1000.00,
            'paid' => 200.00,
            'accepted' => 1,
            'purchase_date' => Carbon::now()->subDays(40)->toDateString(),
        ]);
        $purchase1->save();

        // Purchase 2: grand_total = 800, paid = 0 (outstanding = 800)
        $purchase2 = new Purchase([
            'supplier_id' => $supplier->id,
            'grand_total' => 800.00,
            'paid' => 0.00,
            'accepted' => 1,
            'purchase_date' => Carbon::now()->subDays(15)->toDateString(),
        ]);
        $purchase2->save();

        // General payment: 500
        PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'amount' => 500.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
        ]);

        // Edit Purchase 1: allocated payment should be 500
        $response1 = $this->actingAs($storeUser)->get("/purchase/{$purchase1->id}/edit");
        $response1->assertOk();
        $response1->assertInertia(fn ($page) => $page
            ->component('Purchase/Edit')
            ->where('allocatedPayment', 500)
        );

        // Edit Purchase 2: allocated payment should be 0 (since Purchase 1 absorbed all 500)
        $response2 = $this->actingAs($storeUser)->get("/purchase/{$purchase2->id}/edit");
        $response2->assertOk();
        $response2->assertInertia(fn ($page) => $page
            ->component('Purchase/Edit')
            ->where('allocatedPayment', 0)
        );
    }

    public function test_sale_edit_page_receives_allocated_general_payments(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '1234567890',
        ]);

        // Sale 1: grand_total = 1000, paid = 200 (outstanding = 800)
        $sale1 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 1000.00,
            'paid' => 200.00,
            'accepted' => 1,
        ]);
        $sale1->timestamps = false;
        $sale1->created_at = Carbon::now()->subDays(45);
        $sale1->save();

        // Sale 2: grand_total = 500, paid = 0 (outstanding = 500)
        $sale2 = new Sale([
            'customer_id' => $customer->id,
            'grand_total' => 500.00,
            'paid' => 0.00,
            'accepted' => 1,
        ]);
        $sale2->timestamps = false;
        $sale2->created_at = Carbon::now()->subDays(10);
        $sale2->save();

        // General payment: 600
        SalePayment::create([
            'customer_id' => $customer->id,
            'amount' => 600.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
            'accepted' => 1,
        ]);

        // Edit Sale 1: allocated payment should be 600
        $response1 = $this->actingAs($storeUser)->get("/sale/{$sale1->id}/edit");
        $response1->assertOk();
        $response1->assertInertia(fn ($page) => $page
            ->component('Sale/Edit')
            ->where('allocatedPayment', 600)
        );

        // Edit Sale 2: allocated payment should be 0 (since Sale 1 absorbed all 600)
        $response2 = $this->actingAs($storeUser)->get("/sale/{$sale2->id}/edit");
        $response2->assertOk();
        $response2->assertInertia(fn ($page) => $page
            ->component('Sale/Edit')
            ->where('allocatedPayment', 0)
        );
    }

    public function test_aging_report_excludes_linked_purchase_payments(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Supplier A',
            'email' => 'supplier@example.com',
            'phone' => '9876543210',
        ]);

        // Purchase 1: grand_total = 1000, paid = 300 (outstanding = 700)
        $purchase1 = new Purchase([
            'supplier_id' => $supplier->id,
            'grand_total' => 1000.00,
            'paid' => 300.00,
            'accepted' => 1,
            'purchase_date' => Carbon::now()->subDays(15)->toDateString(),
        ]);
        $purchase1->save();

        // Linked purchase payment: 300
        PurchasePayment::create([
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase1->id,
            'amount' => 300.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($storeUser)->get('/reports/aging');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Aging')
            ->has('apData', 1)
            // If linked payment is excluded from general payments, total_due is 700 (1000 - 300)
            // If it is NOT excluded, it would be counted twice, leaving total_due = 400 (1000 - 300 - 300)
            ->where('apData.0.total_due', 700)
            ->where('apSummary.total_payables', 700)
        );
    }
}
