<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SalePayment;
use App\Models\PurchasePayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Category;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed permissions/roles
        $this->seed(DatabaseSeeder::class);
    }

    public function test_journal_entries_are_automatically_created_on_sales(): void
    {
        $this->withoutExceptionHandling();
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe Customer',
            'email' => 'john@example.com',
            'phone' => '9876543210',
        ]);

        $category = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Awesome Product',
            'sku' => 'AW-PROD-001',
            'hsn_code' => '8517',
            'price' => 100,
            'category_id' => $category->id,
            'unit_type' => 'Pcs',
            'stock_quantity' => 100,
            'description' => 'Test product description',
        ]);

        // Post a Sale
        $saleData = [
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 500.00,
            'due' => 680.00,
            'payment_status' => 'Partial',
            'accepted' => 1,
            'sale_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_type' => 'Pcs',
                    'price' => 100,
                    'baseAmount' => 1000,
                    'cgst' => 9,
                    'sgst' => 9
                ]
            ]
        ];

        $response = $this->actingAs($storeUser)->post('/sale/store', $saleData);
        $response->assertSessionHasNoErrors();
        $response->assertOk();

        // Check that journal entries were created
        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Sale',
            'type' => 'credit',
            'amount' => 1000.00, // Sales Revenue
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Sale',
            'type' => 'credit',
            'amount' => 180.00, // GST Output
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Sale',
            'type' => 'debit',
            'amount' => 500.00, // Cash
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Sale',
            'type' => 'debit',
            'amount' => 680.00, // Accounts Receivable
            'accepted' => 1,
        ]);
    }

    public function test_journal_entries_are_automatically_created_on_purchases(): void
    {
        $this->withoutExceptionHandling();
        $storeUser = User::factory()->create(['role' => 'store']);

        $supplier = Supplier::create([
            'user_id' => $storeUser->id,
            'name' => 'Acme Supplier',
            'email' => 'acme@example.com',
            'phone' => '1122334455',
        ]);

        $category = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Raw Material A',
            'sku' => 'RM-A-001',
            'price' => 100,
            'category_id' => $category->id,
            'unit_type' => 'Pcs',
            'stock_quantity' => 10,
        ]);

        // Post a Purchase
        $purchaseData = [
            'supplier_id' => $supplier->id,
            'purchase_date' => Carbon::now()->toDateString(),
            'total_amount' => 2000.00,
            'GstAmount' => 360.00,
            'grand_total' => 2360.00,
            'paid' => 1000.00,
            'due' => 1360.00,
            'payment_status' => 'Partial',
            'accepted' => 1,
            'purchase_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                    'unit_type' => 'Pcs',
                    'price' => 100,
                    'baseAmount' => 2000,
                    'cgst' => 9,
                    'sgst' => 9
                ]
              ]
        ];

        $response = $this->actingAs($storeUser)->post('/purchase/store', $purchaseData);
        $response->assertSessionHasNoErrors();
        $response->assertOk();

        // Check that journal entries were created
        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Purchase',
            'type' => 'debit',
            'amount' => 2000.00, // Purchase Expenses
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Purchase',
            'type' => 'debit',
            'amount' => 360.00, // GST Input
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Purchase',
            'type' => 'credit',
            'amount' => 1000.00, // Cash
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Purchase',
            'type' => 'credit',
            'amount' => 1360.00, // Accounts Payable
            'accepted' => 1,
        ]);
    }

    public function test_journal_entries_are_created_for_payments_and_expenses(): void
    {
        $this->withoutExceptionHandling();
        $storeUser = User::factory()->create(['role' => 'store']);

        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ]);

        // Post customer payment
        $paymentData = [
            'customer_id' => $customer->id,
            'amount' => 300.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'Cash',
        ];

        $response = $this->actingAs($storeUser)->post('/paymentsCustomer/store', $paymentData);
        $response->assertOk();

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'SalePayment',
            'type' => 'debit',
            'amount' => 300.00, // Cash
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'SalePayment',
            'type' => 'credit',
            'amount' => 300.00, // Accounts Receivable
            'accepted' => 1,
        ]);

        // Post expense
        $category = ExpenseCategory::create([
            'user_id' => $storeUser->id,
            'name' => 'Office Rent',
        ]);

        $expenseData = [
            'expense_category_id' => $category->id,
            'amount' => 800.00,
            'date' => Carbon::now()->toDateString(),
            'note' => 'Monthly Rent',
        ];

        $response = $this->actingAs($storeUser)->post('/expense/store', $expenseData);
        $response->assertOk();

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Expense',
            'type' => 'debit',
            'amount' => 800.00, // Office Rent Expense
            'accepted' => 1,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $storeUser->id,
            'reference_type' => 'Expense',
            'type' => 'credit',
            'amount' => 800.00, // Cash
            'accepted' => 1,
        ]);
    }

    public function test_reports_ledger_returns_matching_and_balanced_trial_balance(): void
    {
        $this->withoutExceptionHandling();
        $storeUser = User::factory()->create(['role' => 'store']);

        // Generate journal entries by posting sale & purchase
        $customer = Customer::create([
            'user_id' => $storeUser->id,
            'name' => 'Customer A',
            'email' => 'a@example.com',
            'phone' => '123'
        ]);

        $category = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'user_id' => $storeUser->id,
            'name' => 'Product X',
            'sku' => 'PX-001',
            'price' => 100,
            'category_id' => $category->id,
            'unit_type' => 'Pcs',
            'stock_quantity' => 100,
        ]);

        $saleData = [
            'customer_id' => $customer->id,
            'total_amount' => 1000.00,
            'GstAmount' => 180.00,
            'grand_total' => 1180.00,
            'paid' => 1180.00,
            'due' => 0.00,
            'payment_status' => 'Paid',
            'accepted' => 1,
            'sale_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_type' => 'Pcs',
                    'price' => 100,
                    'baseAmount' => 1000,
                    'cgst' => 9,
                    'sgst' => 9
                ]
            ]
        ];
        $this->actingAs($storeUser)->post('/sale/store', $saleData);

        $response = $this->actingAs($storeUser)->get('/reports/ledger');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Accounting')
            ->where('unlocked', true)
            ->where('ledgerType', 'standard')
            ->where('trialBalance.total_debit', 1180)
            ->where('trialBalance.total_credit', 1180)
            ->where('profitAndLoss.total_revenue', 1000)
            ->where('profitAndLoss.net_profit', 1000)
            ->where('balanceSheet.total_assets', 1180)
            ->where('balanceSheet.total_equity', 1000)
            ->where('balanceSheet.total_liabilities', 180)
            ->where('balanceSheet.total_liabilities_and_equity', 1180)
        );
    }

    public function test_private_ledger_is_initially_locked_and_can_be_unlocked(): void
    {
        $storeUser = User::factory()->create([
            'role' => 'store',
            'ledger_pin' => Hash::make('1234')
        ]);

        // Access private ledger - should return unlocked: false
        $response = $this->actingAs($storeUser)->get('/reports/ledger?ledger_type=private');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Accounting')
            ->where('unlocked', false)
            ->where('hasPin', true)
            ->where('ledgerType', 'private')
        );

        // Unlock ledger
        $unlockResponse = $this->actingAs($storeUser)->post('/private-ledger/unlock', [
            'pin' => '1234'
        ]);
        $unlockResponse->assertOk();

        // Access private ledger again - should be unlocked
        $response = $this->actingAs($storeUser)->get('/reports/ledger?ledger_type=private');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Accounting')
            ->where('unlocked', true)
            ->where('ledgerType', 'private')
        );
    }
}
