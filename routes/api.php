<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

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

/* Authenticated Routes */
Route::group(['middleware' => ['auth:sanctum']], static function () {
    // Route::get('get-package', [ApiController::class, 'getPackage']);
    
    Route::post('update-profile', [ApiController::class, 'updateProfile']);
    Route::delete('delete-user', [ApiController::class, 'deleteUser']);

    Route::get('get-bidcoin-package', [ApiController::class, 'getBidcoinPackages']);
    Route::get('bidcoin-balances', [ApiController::class, 'getBidcoinBalances']);
    Route::post('purchase-bidcoin', [ApiController::class, 'purchaseBidcoin']);

    Route::get('my-items', [ApiController::class, 'getItem']);
    Route::post('add-item', [ApiController::class, 'addItem']);
    Route::post('update-item', [ApiController::class, 'updateItem']);
    Route::post('delete-item', [ApiController::class, 'deleteItem']);
    Route::post('update-item-status', [ApiController::class, 'updateItemStatus']);
    Route::post('bid-item', [ApiController::class, 'bidItem']);

    Route::post('buy-now', [ApiController::class, 'buyNow']);

    Route::post('assign-free-package', [ApiController::class, 'assignFreePackage']);
    Route::post('make-item-featured', [ApiController::class, 'makeFeaturedItem']);
    Route::post('manage-favourite', [ApiController::class, 'manageFavourite']);
    Route::post('add-reports', [ApiController::class, 'addReports']);
    Route::get('get-notification-list', [ApiController::class, 'getNotificationList']);
    Route::get('get-limits', [ApiController::class, 'getLimits']);
    Route::get('get-favourite-item', [ApiController::class, 'getFavouriteItem']);//ini

    Route::get('get-payment-settings', [ApiController::class, 'getPaymentSettings']);
    Route::post('payment-intent', [ApiController::class, 'getPaymentIntent']);
    Route::get('payment-transactions', [ApiController::class, 'getPaymentTransactions']);//ini

    Route::get('my-auction', [ApiController::class, 'getMyAuction']);//ini histori bid
    Route::get('bid-history', [ApiController::class, 'getBidHistory']);//ini histori bid
    Route::get('sell-history', [ApiController::class, 'getSellHistory']);//ini histori bid
    Route::get('waiting-payment', [ApiController::class, 'getWaitingPayment']);//ini waiting payment winner



    /*Chat Module*/
    // Route::post('item-offer', [ApiController::class, 'createItemOffer']);
    // Route::get('chat-list', [ApiController::class, 'getChatList']);
    // Route::post('send-message', [ApiController::class, 'sendMessage']);
    // Route::get('chat-messages', [ApiController::class, 'getChatMessages']);

    // Route::post('in-app-purchase', [ApiController::class, 'inAppPurchase']);

    Route::post('block-user', [ApiController::class, 'blockUser']);
    Route::post('unblock-user', [ApiController::class, 'unblockUser']);
    Route::get('blocked-users', [ApiController::class, 'getBlockedUsers']);

    Route::get('get-item-detail', [ApiController::class, 'getItemDetail']);//item detail dengan bid winner dan payment
    Route::post('pay-item',[ApiController::class,'payItem']);
    Route::post('send-item',[ApiController::class,'sendItem']);
    Route::post('receive-item',[ApiController::class,'receiveItem']);

});

Route::get('get-pgs', [ApiController::class, 'getPgs']);//ini histori bid
/* Non Authenticated Routes */
// Route::get('get-package', [ApiController::class, 'getPackage']);
Route::post('write-bid', [ApiController::class, 'writeBid']);
Route::get('get-bidcoin-package', [ApiController::class, 'getBidcoinPackages']);
Route::get('get-languages', [ApiController::class, 'getLanguages']);
Route::post('user-signup', [ApiController::class, 'userSignup']);
Route::post('set-item-total-click', [ApiController::class, 'setItemTotalClick']);
Route::get('get-system-settings', [ApiController::class, 'getSystemSettings']);
Route::get('app-payment-status', [ApiController::class, 'appPaymentStatus']);
Route::get('get-customfields', [ApiController::class, 'getCustomFields']);
Route::get('get-item', [ApiController::class, 'getItem']);
Route::get('get-slider', [ApiController::class, 'getSlider']);
Route::get('get-report-reasons', [ApiController::class, 'getReportReasons']);
Route::get('get-categories', [ApiController::class, 'getCategories']);
Route::get('get-parent-categories', [ApiController::class, 'getParentCategoryTree']);
Route::get('get-featured-section', [ApiController::class, 'getFeaturedSection']);
Route::get('blogs', [ApiController::class, 'getBlog']);
Route::get('blog-tags', [ApiController::class, 'getAllBlogTags']);
Route::get('faq', [ApiController::class, 'getFaqs']);
Route::get('tips', [ApiController::class, 'getTips']);
Route::get('countries', [ApiController::class, 'getCountries']);
Route::get('states', [ApiController::class, 'getStates']);
Route::get('cities', [ApiController::class, 'getCities']);
Route::get('areas', [ApiController::class, 'getAreas']);
Route::post('contact-us', [ApiController::class, 'storeContactUs']);

Route::get('close-items', [ApiController::class, 'cronCloseItems']);
Route::post('set-warning-time-item', [ApiController::class, 'setWarningTimeItem']);



