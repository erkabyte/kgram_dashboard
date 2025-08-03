<?php

use App\Http\Controllers\Auth\API\AuthController;
use App\Http\Controllers\Backend\API\DashboardController;
use App\Http\Controllers\Backend\API\NotificationsController;
use App\Http\Controllers\Backend\API\SettingController;
use Modules\Frontend\Http\Controllers\QueryOptimizeController;

use Modules\User\Http\Controllers\API\UserController;
use Modules\Entertainment\Http\Controllers\API\EntertainmentsController;
use Modules\LiveTV\Http\Controllers\API\LiveTVsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('user-detail', [AuthController::class, 'userDetails']);

Route::get('/optimize', [QueryOptimizeController::class, 'optimize'])->name('optimize');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('social-login', 'socialLogin');
    Route::post('forgot-password', 'forgotPassword');
    Route::get('logout', 'logout');
});
Route::post('/store-access-token', [SettingController::class, 'storeToken']);
Route::post('/token-revoke', [SettingController::class, 'revokeToken']);

Route::get('dashboard-detail', [DashboardController::class, 'DashboardDetail']);
Route::get('dashboard-detail-data', [DashboardController::class, 'DashboardDetailData']);
Route::get('get-tranding-data', [DashboardController::class, 'getTrandingData']);
// Route::get('/movies', [DashboardController::class, 'getMovieData']);
// Route::get('/tvshows', [DashboardController::class, 'getTvShowData']);
Route::get('/banner-data', [DashboardController::class, 'getEntertainmentData']);
Route::get('v2/dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV2']);
Route::get('v2/dashboard-detail', [DashboardController::class, 'DashboardDetailV2']);
Route::get('v2/episode-details', [EntertainmentsController::class, 'episodeDetailsV2']);
Route::get('v2/livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV2']);
Route::get('v2/tvshow-details', [EntertainmentsController::class, 'tvshowDetailsV2']);
Route::get('v2/movie-details', [EntertainmentsController::class, 'movieDetailsV2']);


Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::apiResource('setting', SettingController::class);
    Route::apiResource('notification', NotificationsController::class);
    Route::get('notification-list', [NotificationsController::class, 'notificationList']);
    Route::get('gallery-list', [DashboardController::class, 'globalGallery']);
    Route::get('search-list', [DashboardController::class, 'searchList']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);

    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('delete-account', [AuthController::class, 'deleteAccount']);

    Route::get('vendor-dashboard-list', [DashboardController::class, 'VendorDashboardDetail']);

    ### v2 api`s

    Route::get('v2/profile-details', [UserController::class, 'profileDetailsV2']);
    // Route::get('v2/dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV2']);
    // Route::get('v2/dashboard-detail', [DashboardController::class, 'DashboardDetailV2']);
    // Route::get('v2/episode-details', [EntertainmentsController::class, 'episodeDetailsV2']);
    // Route::get('v2/livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV2']);
    // Route::get('v2/tvshow-details', [EntertainmentsController::class, 'tvshowDetailsV2']);

    // Route::get('v2/movie-details', [EntertainmentsController::class, 'movieDetailsV2']);

});
Route::get('app-configuration', [SettingController::class, 'appConfiguraton']);
