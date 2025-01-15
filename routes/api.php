<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GaleriController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SuratController;
use App\Http\Controllers\Api\KategoriSuratController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\LayananController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Storage route - tambahkan di atas route lainnya
Route::get('storage/{path}', function($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Content-Type' => mime_content_type($filePath)
    ]);
})->where('path', '.*');

// Public Content routes
Route::get('galeri', [GaleriController::class, 'index']);
Route::get('galeri/{id}', [GaleriController::class, 'show']);
Route::get('berita', [PostController::class, 'index']);
Route::get('berita/{id}', [PostController::class, 'show']);
Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');
Route::get('layanan/jenis', [LayananController::class, 'getJenisLayanan']);
// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    // resend email verification
    Route::post('email/verification-notification', [AuthController::class, 'resendEmailVerificationNotification']);

    // Verification routes
    Route::post('upload-selfie', [AuthController::class, 'uploadSelfie']);
    Route::get('verification-status', [AuthController::class, 'checkVerificationStatus']);
    
    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::post('verify-user/{userId}', [AuthController::class, 'verifyUser']);
    });
    
    // Profile routes
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile/update', [ProfileController::class, 'update']);
    Route::post('profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('profile/photo', [ProfileController::class, 'updatePhotoProfile']);
    
    // Layanan routes
    Route::post('layanan', [LayananController::class, 'index']);
    
    Route::get('layanan/{id}', [LayananController::class, 'show']);
    Route::post('layanan/store', [LayananController::class, 'store']);
    Route::post('layanan/upload-identitas', [LayananController::class, 'uploadIdentitas']);
    Route::post('layanan/upload-dokumen', [LayananController::class, 'uploadDokumen']);
    Route::post('layanan/finalisasi/{id}', [LayananController::class, 'finalisasi']);
    Route::post('layanan/approve/{id}', [LayananController::class, 'approve']);
    Route::delete('layanan/{id}', [LayananController::class, 'destroy']);
    
    // Notifikasi routes
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'markAsRead']);
    
    // Surat routes
    Route::apiResource('surat', SuratController::class);
    Route::apiResource('kategori-surat', KategoriSuratController::class);
});