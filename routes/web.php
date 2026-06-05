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

use App\Http\Controllers\Admin\StoresController;



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

});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/store', [StoresController::class, 'index'])->name('store');
    Route::get('/add/store', [StoresController::class, 'create'])->name('add.store');
    Route::post('/store/create', [StoresController::class, 'store'])->name('store.create');
    Route::get('/store/edit/{storeId}', [StoresController::class, 'edit'])->name('store.edit');
    Route::post('/store/update/{id}', [StoresController::class, 'update'])->name('store.update');
    Route::delete('/store/destroy/{id}', [StoresController::class, 'destroy'])->name('store.destroy');

    // Route::get('/add/user', [StoresController::class, 'userCreate'])->name('add.user');

    // Route::get('/admin/list', [StoresController::class, 'getAdmin'])->name('admin.list');

    //user switch start
    Route::post('/store/switch/start/{id}', [StoresController::class, 'switch_start'])->name('store.switch.start');

});

Route::middleware(['auth', 'role:store'])->group(function () {

    // Customers
    Route::get('/customer', [CustomersController::class, 'index'])->name('customer');
    Route::get('/customer/create', [CustomersController::class, 'create'])->name('customer.Create');
    Route::post('/customer/store', [CustomersController::class, 'store'])->name('customer.store');
    Route::get('/customer/{id}/edit', [CustomersController::class, 'edit'])->name('customer.edit');
    Route::post('/customer/update/{id}', [CustomersController::class, 'update'])->name('customer.update');
    Route::delete('/customer/destroy/{id}', [CustomersController::class, 'destroy'])->name('customer.destroy');

    Route::get('/customer/{id}/download-pdf', [CustomersController::class, 'downloadInvoice'])->name('customer.invoice.download');

    // Suppliers
    Route::get('/supplier', [SuppliersController::class, 'index'])->name('supplier');
    Route::get('/supplier/create', [SuppliersController::class, 'create'])->name('supplier.Create');
    Route::post('/supplier/store', [SuppliersController::class, 'store'])->name('supplier.store');
    Route::get('/supplier/{id}/edit', [SuppliersController::class, 'edit'])->name('supplier.edit');
    Route::post('/supplier/update/{id}', [SuppliersController::class, 'update'])->name('supplier.update');
    Route::delete('/supplier/destroy/{id}', [SuppliersController::class, 'destroy'])->name('supplier.destroy');

    // Categories
    Route::get('/category', [CategoryController::class, 'index'])->name('category');
    Route::get('/category/create', [CategoryController::class, 'create'])->name('category.Create');
    Route::post('/category/store', [CategoryController::class, 'store'])->name('category.store');
    Route::get('/category/{id}/edit', [CategoryController::class, 'edit'])->name('category.edit');
    Route::post('/category/update/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/category/destroy/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');

    // Products
    Route::get('/product', [ProductController::class, 'index'])->name('product');
    Route::get('/product/create', [ProductController::class, 'create'])->name('product.Create');
    Route::post('/product/store', [ProductController::class, 'store'])->name('product.store');
    Route::get('/product/{id}/edit', [ProductController::class, 'edit'])->name('product.edit');
    Route::post('/product/update/{id}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/product/destroy/{id}', [ProductController::class, 'destroy'])->name('product.destroy');

    // Sales
    Route::get('/sale', [SaleController::class, 'index'])->name('sale');
    Route::get('/sale/create', [SaleController::class, 'create'])->name('sale.create');
    Route::post('/sale/store', [SaleController::class, 'store'])->name('sale.store');
    Route::get('/sale/{id}/edit', [SaleController::class, 'edit'])->name('sale.edit');
    Route::post('/sale/update/{id}', [SaleController::class, 'update'])->name('sale.update');
    Route::delete('/sale/destroy/{id}', [SaleController::class, 'destroy'])->name('sale.destroy');

    //downloadInvoice
    Route::get('/sale/{id}/download-pdf', [SaleController::class, 'downloadInvoice'])->name('sale.invoice.download');


    Route::get('/purchase', [PurchasesController::class, 'index'])->name('purchase');
    Route::get('/purchase/create', [PurchasesController::class, 'create'])->name('purchase.create');
    Route::post('/purchase/store', [PurchasesController::class, 'store'])->name('purchase.store');
    Route::get('/purchase/{id}/edit', [PurchasesController::class, 'edit'])->name('purchase.edit');
    Route::post('/purchase/update/{id}', [PurchasesController::class, 'update'])->name('purchase.update');
    Route::delete('/purchase/destroy/{id}', [PurchasesController::class, 'destroy'])->name('purchase.destroy');

    //payment Information
    Route::get('/purchase/payment/{id}', [PurchasesController::class, 'payment'])->name('suppliers.payment');
    Route::get('/sale/payment/{id}', [SaleController::class, 'payment'])->name('sale.payment');

    //supplier_payment
    Route::get('/paymentSupplier', [SupplierPaymentController::class, 'index'])->name('paymentSupplier');
    Route::get('/paymentSupplier/create', [SupplierPaymentController::class, 'create'])->name('paymentSupplier.create');
    Route::post('/paymentSupplier/store', [SupplierPaymentController::class, 'store'])->name('paymentSupplier.store');

    //customer_payment 
    Route::get('/paymentsCustomer', [CustomerPaymentsController::class, 'index'])->name('paymentsCustomer');
    Route::get('/paymentsCustomer/create', [CustomerPaymentsController::class, 'create'])->name('paymentsCustomer.create');
    Route::post('/paymentsCustomer/store', [CustomerPaymentsController::class, 'store'])->name('paymentsCustomer.store');


});
   
require __DIR__.'/auth.php';