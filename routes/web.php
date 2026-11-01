<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BatchGeneratorController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrameController as AdminFrameController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageSettingController;
use App\Http\Controllers\Admin\PhotoshootBookingController;
use App\Http\Controllers\Admin\PhotoshootPlanController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\FrameStudioController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PhotoshootController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Customer Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

// Interactive Frame Customizer & Generation with Razorpay
Route::middleware('auth')->group(function () {
    Route::get('/studio/{frame?}', [FrameStudioController::class, 'index'])->name('studio.index');
    Route::post('/studio/photos', [FrameStudioController::class, 'uploadPhotos'])->name('studio.photos');
    Route::post('/studio/preview', [FrameStudioController::class, 'preview'])->name('studio.preview');
    Route::post('/studio/order', [FrameStudioController::class, 'createOrder'])->name('studio.order');
    Route::post('/studio/verify-payment', [FrameStudioController::class, 'verifyPayment'])->name('studio.verify');
});
Route::get('/studio/download/{token}', [FrameStudioController::class, 'download'])->name('studio.download');
Route::get('/my-orders', [FrameStudioController::class, 'myOrders'])->name('customer.orders');

// Photoshoot Packages & Appointment Booking (Page 2)
Route::get('/photoshoot', [PhotoshootController::class, 'index'])->name('photoshoot.index');
Route::post('/photoshoot/book', [PhotoshootController::class, 'book'])->name('photoshoot.book');

// Information & Policy Pages (Neelax Tech)
Route::get('/about', [CmsController::class, 'about'])->name('about');
Route::get('/contact', [CmsController::class, 'contact'])->name('contact');
Route::post('/contact', [CmsController::class, 'submitContact'])->name('contact.submit');
Route::get('/terms', [CmsController::class, 'terms'])->name('terms');
Route::get('/privacy', [CmsController::class, 'privacy'])->name('privacy');
Route::get('/refund-policy', [CmsController::class, 'refundPolicy'])->name('refund-policy');

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
    Route::get('/quick-login', [AdminAuthController::class, 'quickLogin'])->name('admin.quick-login');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

/*
|--------------------------------------------------------------------------
| Admin Panel Protected Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['admin'])->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Template Frames Management
    Route::post('/frames/detect-slots', [AdminFrameController::class, 'detectSlots'])->name('frames.detectSlots');
    Route::post('/frames/{frame}/toggle', [AdminFrameController::class, 'toggleActive'])->name('frames.toggle');
    Route::resource('frames', AdminFrameController::class);

    // Admin Batch Generator (Upload up to 300 photos, zero payment)
    Route::get('/batch-generate', [BatchGeneratorController::class, 'index'])->name('batch.index');
    Route::post('/batch-generate/chunk', [BatchGeneratorController::class, 'processChunk'])->name('batch.chunk');
    Route::get('/batch-generate/zip/{batchId}', [BatchGeneratorController::class, 'downloadZip'])->name('batch.zip');

    // Photoshoot Plans Management
    Route::post('/plans/{plan}/toggle', [PhotoshootPlanController::class, 'toggleActive'])->name('plans.toggle');
    Route::resource('plans', PhotoshootPlanController::class);

    // Photoshoot Bookings & Appointments
    Route::get('/bookings', [PhotoshootBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [PhotoshootBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [PhotoshootBookingController::class, 'updateStatus'])->name('bookings.status');
    Route::delete('/bookings/{booking}', [PhotoshootBookingController::class, 'destroy'])->name('bookings.destroy');

    // Frame Orders & Payments
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Pricing & Studio Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // CMS Pages & Policies Management
    Route::get('/pages', [PageSettingController::class, 'index'])->name('pages.index');
    Route::post('/pages', [PageSettingController::class, 'update'])->name('pages.update');

    // Customer Inquiries
    Route::get('/inquiries', [PageSettingController::class, 'inquiries'])->name('inquiries.index');
    Route::post('/inquiries/{inquiry}/toggle', [PageSettingController::class, 'toggleInquiry'])->name('inquiries.toggle');
    Route::delete('/inquiries/{inquiry}', [PageSettingController::class, 'deleteInquiry'])->name('inquiries.destroy');
});

/*
|--------------------------------------------------------------------------
| User Authentication (Google OAuth) Routes
|--------------------------------------------------------------------------
*/

// Register / Login form pages
Route::get('/userauth/register', function () {
    return view('userauth.register');
})->name('userauth.register');

Route::get('/userauth/login', function () {
    return view('userauth.login');
})->name('userauth.login');

// Google OAuth entry point
Route::get('userauth/google', [UserAuthController::class, 'redirectToGoogle'])
    ->name('userauth.google');

// Google OAuth callback — canonical path
Route::get('userauth/google/callback', [UserAuthController::class, 'handleGoogleCallback'])
    ->name('userauth.google.callback');

// Legacy safety net — keeps working if Google Console still calls /auth/google/callback
// (no name — avoids the duplicate-name bug)
Route::get('auth/google/callback', [UserAuthController::class, 'handleGoogleCallback']);

// Phone collection (auth required)
Route::middleware('auth')->group(function () {
    Route::get('userauth/phone', [UserAuthController::class, 'showPhoneForm'])
        ->name('userauth.phone');
    Route::post('userauth/phone', [UserAuthController::class, 'savePhone'])
        ->name('userauth.phone.save');
});

// Logout
Route::post('userauth/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('userauth.logout');