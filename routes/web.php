<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\CustomerPaymentsController;
use App\Http\Controllers\PrivateSaleController;

use App\Http\Controllers\Admin\StoresController;
use App\Http\Controllers\Admin\RolesController;

use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeCategoryController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\AgingReportController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\PurchaseReturnController;




Route::get('/', function () {
    return Inertia::render('Auth/Login');
});
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});



// Both admin and store
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //user switch stop
    Route::get('/switch/stop', [StoresController::class, 'user_switch_stop'])->name('switch.stop');

    // Audit Logs
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');

});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/store', [StoresController::class, 'index'])->name('store');
    Route::get('/store/add', [StoresController::class, 'create'])->name('store.add');
    Route::post('/store/create', [StoresController::class, 'store'])->name('store.create');
    Route::get('/store/edit/{storeId}', [StoresController::class, 'edit'])->name('store.edit');
    Route::post('/store/update/{id}', [StoresController::class, 'update'])->name('store.update');
    Route::delete('/store/destroy/{id}', [StoresController::class, 'destroy'])->name('store.destroy');
    Route::get('/store/permissions/{id}', [StoresController::class, 'editPermissions'])->name('store.permissions.edit');
    Route::post('/store/permissions/{id}', [StoresController::class, 'updatePermissions'])->name('store.permissions.update');

    // Roles CRUD
    Route::get('/role', [RolesController::class, 'index'])->name('role');
    Route::get('/role/create', [RolesController::class, 'create'])->name('role.create');
    Route::post('/role/store', [RolesController::class, 'store'])->name('role.store');
    Route::get('/role/edit/{id}', [RolesController::class, 'edit'])->name('role.edit');
    Route::post('/role/update/{id}', [RolesController::class, 'update'])->name('role.update');
    Route::delete('/role/destroy/{id}', [RolesController::class, 'destroy'])->name('role.destroy');

    // Permissions CRUD
    Route::get('/permission', [RolesController::class, 'permissionIndex'])->name('permission');
    Route::post('/permission/store', [RolesController::class, 'permissionStore'])->name('permission.store');
    Route::post('/permission/update/{id}', [RolesController::class, 'permissionUpdate'])->name('permission.update');
    Route::delete('/permission/destroy/{id}', [RolesController::class, 'permissionDestroy'])->name('permission.destroy');

    //user switch start
    Route::post('/store/switch/start/{id}', [StoresController::class, 'switch_start'])->name('store.switch.start');

});

Route::middleware(['auth', 'role:store'])->group(function () {

    // Customers
    Route::middleware('permission:customer manage')->group(function () {
        Route::get('/customer', [CustomersController::class, 'index'])->name('customer');
        Route::get('/customer/create', [CustomersController::class, 'create'])->name('customer.Create');
        Route::post('/customer/store', [CustomersController::class, 'store'])->name('customer.store');
        Route::get('/customer/{id}/edit', [CustomersController::class, 'edit'])->name('customer.edit');
        Route::post('/customer/update/{id}', [CustomersController::class, 'update'])->name('customer.update');
        Route::delete('/customer/destroy/{id}', [CustomersController::class, 'destroy'])->name('customer.destroy');
        Route::get('/customer/{id}/download-pdf', [CustomersController::class, 'downloadInvoice'])->name('customer.invoice.download');
    });

    // Suppliers
    Route::middleware('permission:supplier manage')->group(function () {
        Route::get('/supplier', [SuppliersController::class, 'index'])->name('supplier');
        Route::get('/supplier/create', [SuppliersController::class, 'create'])->name('supplier.Create');
        Route::post('/supplier/store', [SuppliersController::class, 'store'])->name('supplier.store');
        Route::get('/supplier/{id}/edit', [SuppliersController::class, 'edit'])->name('supplier.edit');
        Route::post('/supplier/update/{id}', [SuppliersController::class, 'update'])->name('supplier.update');
        Route::delete('/supplier/destroy/{id}', [SuppliersController::class, 'destroy'])->name('supplier.destroy');
    });

    // Categories
    Route::middleware('permission:category manage')->group(function () {
        Route::get('/category', [CategoryController::class, 'index'])->name('category');
        Route::get('/category/create', [CategoryController::class, 'create'])->name('category.Create');
        Route::post('/category/store', [CategoryController::class, 'store'])->name('category.store');
        Route::get('/category/{id}/edit', [CategoryController::class, 'edit'])->name('category.edit');
        Route::post('/category/update/{id}', [CategoryController::class, 'update'])->name('category.update');
        Route::delete('/category/destroy/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');
    });

    // Expense Categories
    Route::middleware('permission:expense category manage')->group(function () {
        Route::get('/expense-category', [ExpenseCategoryController::class, 'index'])->name('expense-category');
        Route::post('/expense-category/store', [ExpenseCategoryController::class, 'store'])->name('expense-category.store');
        Route::post('/expense-category/update/{id}', [ExpenseCategoryController::class, 'update'])->name('expense-category.update');
        Route::delete('/expense-category/destroy/{id}', [ExpenseCategoryController::class, 'destroy'])->name('expense-category.destroy');
    });

    // Expenses
    Route::middleware('permission:expense manage')->group(function () {
        Route::get('/expense', [ExpenseController::class, 'index'])->name('expense');
        Route::post('/expense/store', [ExpenseController::class, 'store'])->name('expense.store');
        Route::post('/expense/update/{id}', [ExpenseController::class, 'update'])->name('expense.update');
        Route::delete('/expense/destroy/{id}', [ExpenseController::class, 'destroy'])->name('expense.destroy');
    });

    // Income Categories
    Route::middleware('permission:income category manage')->group(function () {
        Route::get('/income-category', [IncomeCategoryController::class, 'index'])->name('income-category');
        Route::post('/income-category/store', [IncomeCategoryController::class, 'store'])->name('income-category.store');
        Route::post('/income-category/update/{id}', [IncomeCategoryController::class, 'update'])->name('income-category.update');
        Route::delete('/income-category/destroy/{id}', [IncomeCategoryController::class, 'destroy'])->name('income-category.destroy');
    });

    // Incomes
    Route::middleware('permission:income manage')->group(function () {
        Route::get('/income', [IncomeController::class, 'index'])->name('income');
        Route::post('/income/store', [IncomeController::class, 'store'])->name('income.store');
        Route::post('/income/update/{id}', [IncomeController::class, 'update'])->name('income.update');
        Route::delete('/income/destroy/{id}', [IncomeController::class, 'destroy'])->name('income.destroy');
    });

    // Products
    Route::middleware('permission:product manage')->group(function () {
        Route::get('/product', [ProductController::class, 'index'])->name('product');
        Route::get('/product/create', [ProductController::class, 'create'])->name('product.Create');
        Route::post('/product/store', [ProductController::class, 'store'])->name('product.store');
        Route::get('/product/{id}/edit', [ProductController::class, 'edit'])->name('product.edit');
        Route::post('/product/update/{id}', [ProductController::class, 'update'])->name('product.update');
        Route::delete('/product/destroy/{id}', [ProductController::class, 'destroy'])->name('product.destroy');
        Route::post('/product/import', [ProductController::class, 'import'])->name('product.import');

        // Stock Adjustments
        Route::get('/stock-adjustment', [StockMovementController::class, 'index'])->name('stock-adjustment.index');
        Route::get('/stock-adjustment/create', [StockMovementController::class, 'create'])->name('stock-adjustment.create');
        Route::post('/stock-adjustment/store', [StockMovementController::class, 'store'])->name('stock-adjustment.store');
    });

    // Sales
    Route::middleware('permission:sale manage')->group(function () {
        Route::get('/sale', [SaleController::class, 'index'])->name('sale');
        Route::get('/sale/create', [SaleController::class, 'create'])->name('sale.create');
        Route::post('/sale/store', [SaleController::class, 'store'])->name('sale.store');
        Route::get('/sale/{id}/edit', [SaleController::class, 'edit'])->name('sale.edit');
        Route::get('/sale/{id}', [SaleController::class, 'show'])->name('sale.show');
        Route::post('/sale/update/{id}', [SaleController::class, 'update'])->name('sale.update');
        Route::delete('/sale/destroy/{id}', [SaleController::class, 'destroy'])->name('sale.destroy');

        //downloadInvoice
        Route::get('/sale/{id}/download-pdf', [SaleController::class, 'downloadInvoice'])->name('sale.invoice.download');

        // Estimates (Quotations)
        Route::get('/estimate', [EstimateController::class, 'index'])->name('estimate.index');
        Route::get('/estimate/create', [EstimateController::class, 'create'])->name('estimate.create');
        Route::post('/estimate/store', [EstimateController::class, 'store'])->name('estimate.store');
        Route::get('/estimate/{id}/edit', [EstimateController::class, 'edit'])->name('estimate.edit');
        Route::post('/estimate/update/{id}', [EstimateController::class, 'update'])->name('estimate.update');
        Route::delete('/estimate/destroy/{id}', [EstimateController::class, 'destroy'])->name('estimate.destroy');
        Route::get('/estimate/{id}/download-pdf', [EstimateController::class, 'downloadPdf'])->name('estimate.pdf');
        Route::get('/estimate/{id}/get-json', [EstimateController::class, 'getJson'])->name('estimate.json');

        // Sales Returns
        Route::get('/sale-return', [SaleReturnController::class, 'index'])->name('sale-return.index');
        Route::get('/sale-return/create', [SaleReturnController::class, 'create'])->name('sale-return.create');
        Route::get('/sale-return/customer/{customerId}/sales', [SaleReturnController::class, 'getCustomerSales'])->name('sale-return.customer-sales');
        Route::get('/sale-return/customer/{customerId}/purchased-items', [SaleReturnController::class, 'getCustomerPurchasedItems'])->name('sale-return.customer-purchased-items');
        Route::get('/sale-return/sale/{id}/details', [SaleReturnController::class, 'getSaleDetails'])->name('sale-return.sale-details');
        Route::post('/sale-return/store', [SaleReturnController::class, 'store'])->name('sale-return.store');
        Route::get('/sale-return/{id}/download-pdf', [SaleReturnController::class, 'downloadReturnPdf'])->name('sale-return.pdf');
    });


    Route::middleware('permission:purchase manage')->group(function () {
        Route::get('/purchase', [PurchasesController::class, 'index'])->name('purchase');
        Route::get('/purchase/create', [PurchasesController::class, 'create'])->name('purchase.create');
        Route::post('/purchase/store', [PurchasesController::class, 'store'])->name('purchase.store');
        Route::get('/purchase/{id}/edit', [PurchasesController::class, 'edit'])->name('purchase.edit');
        Route::get('/purchase/{id}', [PurchasesController::class, 'show'])->name('purchase.show');
        Route::get('/purchase/{id}/download-pdf', [PurchasesController::class, 'downloadInvoice'])->name('purchase.invoice.download');
        Route::post('/purchase/update/{id}', [PurchasesController::class, 'update'])->name('purchase.update');
        Route::delete('/purchase/destroy/{id}', [PurchasesController::class, 'destroy'])->name('purchase.destroy');

        // Purchase Returns
        Route::get('/purchase-return', [PurchaseReturnController::class, 'index'])->name('purchase-return.index');
        Route::get('/purchase-return/create', [PurchaseReturnController::class, 'create'])->name('purchase-return.create');
        Route::get('/purchase-return/purchase/{id}/details', [PurchaseReturnController::class, 'getPurchaseDetails'])->name('purchase-return.purchase-details');
        Route::post('/purchase-return/store', [PurchaseReturnController::class, 'store'])->name('purchase-return.store');
        Route::get('/purchase-return/{id}/download-pdf', [PurchaseReturnController::class, 'downloadReturnPdf'])->name('purchase-return.pdf');
    });

    //payment Information
    Route::get('/purchase/payment/{id}', [PurchasesController::class, 'payment'])->name('suppliers.payment');
    Route::get('/sale/payment/{id}', [SaleController::class, 'payment'])->name('sale.payment');

    //supplier_payment
    Route::middleware('permission:payment supplier manage')->group(function () {
        Route::get('/paymentSupplier', [SupplierPaymentController::class, 'index'])->name('paymentSupplier');
        Route::get('/paymentSupplier/create', [SupplierPaymentController::class, 'create'])->name('paymentSupplier.create');
        Route::post('/paymentSupplier/store', [SupplierPaymentController::class, 'store'])->name('paymentSupplier.store');
        Route::get('/paymentSupplier/{id}/history', [SupplierPaymentController::class, 'history'])->name('paymentSupplier.history');
        Route::get('/paymentSupplier/{id}/history/download-pdf', [SupplierPaymentController::class, 'downloadHistoryPdf'])->name('paymentSupplier.history.pdf');
    });

    //customer_payment 
    Route::middleware('permission:payments customer manage')->group(function () {
        Route::get('/paymentsCustomer', [CustomerPaymentsController::class, 'index'])->name('paymentsCustomer');
        Route::get('/paymentsCustomer/create', [CustomerPaymentsController::class, 'create'])->name('paymentsCustomer.create');
        Route::post('/paymentsCustomer/store', [CustomerPaymentsController::class, 'store'])->name('paymentsCustomer.store');
        Route::get('/paymentsCustomer/{id}/history', [CustomerPaymentsController::class, 'history'])->name('paymentsCustomer.history');
        Route::get('/paymentsCustomer/{id}/history/download-pdf', [CustomerPaymentsController::class, 'downloadHistoryPdf'])->name('paymentsCustomer.history.pdf');
        Route::get('/paymentsCustomer/receipt/{source}/{id}', [CustomerPaymentsController::class, 'showReceipt'])->name('paymentsCustomer.receipt.show');
        Route::get('/paymentsCustomer/receipt/{source}/{id}/pdf', [CustomerPaymentsController::class, 'downloadReceiptPdf'])->name('paymentsCustomer.receipt.pdf');
        
        Route::get('/customer/{id}/payment-info', [CustomerPaymentsController::class, 'paymentInfo'])->name('customer.payment.info');
    });

    // AR/AP Aging Report
    Route::get('/reports/aging', [AgingReportController::class, 'index'])->name('reports.aging');
    Route::get('/reports/ledger', [LedgerController::class, 'index'])->name('reports.ledger');
    Route::post('/reports/ledger/repost', [LedgerController::class, 'repost'])->name('reports.ledger.repost');

    // Private Ledger
    Route::get('/private-ledger', [PrivateSaleController::class, 'index'])->name('private.index');
    Route::post('/private-ledger/unlock', [PrivateSaleController::class, 'unlock'])->name('private.unlock');
    Route::post('/private-ledger/lock', [PrivateSaleController::class, 'lock'])->name('private.lock');
    Route::post('/private-ledger/payment', [PrivateSaleController::class, 'storePayment'])->name('private.payment.store');

});
   
require __DIR__.'/auth.php';