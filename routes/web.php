<?php

use App\Http\Controllers\DonationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\DonationController as AdminDonationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentNotificationController;
use App\Http\Controllers\ProofController;

Route::get('/', [DonationController::class, 'index'])->name('donation.index');
Route::post('/donations', [DonationController::class, 'store'])->name('donations.store');
Route::get('/donations/status/{external_reference}', [PaymentNotificationController::class, 'checkPaymentStatus'])->name('donations.status');

Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('projects', ProjectController::class)->names([
        'index' => 'admin.projects.index',
        'create' => 'admin.projects.create',
        'store' => 'admin.projects.store',
        'show' => 'admin.projects.show',
        'edit' => 'admin.projects.edit',
        'update' => 'admin.projects.update',
        'destroy' => 'admin.projects.destroy',
    ]);
    Route::get('/donations', [AdminDonationController::class, 'index'])->name('admin.donations.index');
    Route::post('/donations', [AdminDonationController::class, 'store'])->name('admin.donations.store');
    Route::get('/donations/{donation}', [AdminDonationController::class, 'show'])->name('admin.donations.show');
    Route::get('/donations/{donation}/edit', [AdminDonationController::class, 'edit'])->name('admin.donations.edit');
    Route::put('/donations/{donation}', [AdminDonationController::class, 'update'])->name('admin.donations.update');
    Route::delete('/donations/{donation}', [AdminDonationController::class, 'destroy'])->name('admin.donations.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('register', function () {
    return redirect('/login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('proofs/{filename}', [ProofController::class, 'show'])
        ->name('proofs.show')
        ->where('filename', '.*');
});

require __DIR__.'/auth.php';
