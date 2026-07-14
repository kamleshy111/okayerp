<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkDeleteController extends Controller
{
    public function destroy(Request $request)
    {
        $request->validate([
            'resource' => 'required|string',
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $resource = $request->input('resource');
        $ids = $request->input('ids');

        $controller = match($resource) {
            'product' => app(\App\Http\Controllers\ProductController::class),
            'category' => app(\App\Http\Controllers\CategoryController::class),
            'customer' => app(\App\Http\Controllers\CustomersController::class),
            'supplier' => app(\App\Http\Controllers\SuppliersController::class),
            'sale' => app(\App\Http\Controllers\SaleController::class),
            'purchase' => app(\App\Http\Controllers\PurchasesController::class),
            'expense' => app(\App\Http\Controllers\ExpenseController::class),
            'income' => app(\App\Http\Controllers\IncomeController::class),
            'estimate' => app(\App\Http\Controllers\EstimateController::class),
            'offer' => app(\App\Http\Controllers\OfferController::class),
            'expense-category' => app(\App\Http\Controllers\ExpenseCategoryController::class),
            'income-category' => app(\App\Http\Controllers\IncomeCategoryController::class),
            'referral-user' => app(\App\Http\Controllers\ReferralUserController::class),
            'sale-return' => app(\App\Http\Controllers\SaleReturnController::class),
            'purchase-return' => app(\App\Http\Controllers\PurchaseReturnController::class),
            'stock-adjustment' => app(\App\Http\Controllers\StockMovementController::class),
            'customer-payment' => app(\App\Http\Controllers\CustomerPaymentsController::class),
            'supplier-payment' => app(\App\Http\Controllers\SupplierPaymentController::class),
            default => null
        };

        if (!$controller) {
            return response()->json(['message' => 'Invalid resource type.'], 422);
        }

        $succeeded = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                // Call the controller's destroy method
                $response = $controller->destroy($id);
                
                // Inspect response status code
                $statusCode = 200;
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $statusCode = $response->getStatusCode();
                } elseif ($response instanceof \Symfony\Component\HttpFoundation\Response) {
                    $statusCode = $response->getStatusCode();
                }

                if ($statusCode >= 400) {
                    $content = json_decode($response->getContent(), true);
                    $failed[$id] = $content['message'] ?? 'Failed to delete.';
                } else {
                    $succeeded[] = $id;
                }
            } catch (\Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Bulk deletion complete.',
            'succeeded' => $succeeded,
            'failed' => $failed
        ]);
    }
}
