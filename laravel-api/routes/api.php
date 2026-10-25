<?php

use App\Http\Controllers\Api\AdminAuditLogController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RoyaltyController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\WriterRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/health', fn () => ['ok' => true, 'app' => 'Zaimedia Laravel API']);
Route::get('/storefront-config', [StorefrontController::class, 'config']);

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/reviews/book/{bookId}', [ReviewController::class, 'book']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);

Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::patch('/users/me', [UserController::class, 'update']);
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']);
    Route::post('/users/me/avatar', [UserController::class, 'uploadAvatar']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/writer-request/me', [WriterRequestController::class, 'mine']);
    Route::post('/writer-request', [WriterRequestController::class, 'store']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{book}', [WishlistController::class, 'toggle']);

    Route::middleware('role:customer,writer,admin')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/checkout-operations/{operationKey}', [OrderController::class, 'receipt']);
        Route::get('/orders/me', [OrderController::class, 'mine']);
        Route::get('/orders/by-number/{orderNumber}', [OrderController::class, 'showByNumber']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show']);
        Route::get('/library', [LibraryController::class, 'index']);
        Route::get('/library/{book}', [LibraryController::class, 'show']);
        Route::get('/library/{book}/download', [LibraryController::class, 'download']);
    });

    Route::middleware('role:writer,admin')->group(function () {
        Route::get('/writer/books', [BookController::class, 'writerBooks']);
        Route::post('/writer/books', [BookController::class, 'store']);
        Route::put('/writer/books/{book}', [BookController::class, 'update']);
        Route::delete('/writer/books/{book}', [BookController::class, 'destroy']);
        Route::patch('/writer/books/{book}/restore', [BookController::class, 'restore']);
        Route::post('/uploads/{type}', [BookController::class, 'upload']);

        Route::get('/royalty/balance', [RoyaltyController::class, 'balance']);
        Route::get('/royalty/sales', [RoyaltyController::class, 'sales']);
        Route::get('/royalty/withdrawals', [RoyaltyController::class, 'withdrawals']);
        Route::post('/royalty/withdrawals', [RoyaltyController::class, 'requestWithdrawal']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}/role', [UserController::class, 'setRole']);
        Route::patch('/users/{user}/status', [UserController::class, 'setStatus']);

        Route::get('/books', [BookController::class, 'adminBooks']);
        Route::patch('/books/{book}/review', [BookController::class, 'review']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        Route::patch('/books/{book}/restore', [BookController::class, 'restore']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'adminIndex']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

        Route::get('/withdrawals', [RoyaltyController::class, 'adminWithdrawals']);
        Route::patch('/withdrawals/{withdrawal}', [RoyaltyController::class, 'process']);
        Route::get('/sales', [RoyaltyController::class, 'adminSales']);
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('/articles', [ArticleController::class, 'adminIndex']);
        Route::post('/articles', [ArticleController::class, 'store']);
        Route::put('/articles/{article}', [ArticleController::class, 'update']);
        Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);
        Route::post('/articles/upload-cover', [ArticleController::class, 'uploadCover']);
        Route::get('/writer-requests', [WriterRequestController::class, 'index']);
        Route::patch('/writer-requests/{writerRequest}', [WriterRequestController::class, 'process']);
        Route::get('/reviews', [ReviewController::class, 'adminIndex']);
        Route::patch('/reviews/{review}', [ReviewController::class, 'moderate']);
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::patch('/settings', [SystemSettingController::class, 'update']);

        Route::get('/exports/sales', [ReportExportController::class, 'sales']);
        Route::get('/exports/orders', [ReportExportController::class, 'orders']);
        Route::get('/exports/withdrawals', [ReportExportController::class, 'withdrawals']);
        Route::get('/exports/audit-logs', [ReportExportController::class, 'auditLogs']);
    });
});
