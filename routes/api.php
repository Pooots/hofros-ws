<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\BookingPortalController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PublicDirectPortalBookingController;
use App\Http\Controllers\Api\V1\PublicDirectPortalCalendarController;
use App\Http\Controllers\Api\V1\PublicDirectPortalController;
use App\Http\Controllers\Api\V1\PublicDirectPortalQuoteController;
use App\Http\Controllers\Api\V1\PromoCodeController;
use App\Http\Controllers\Api\V1\UnitDateBlockController;
use App\Http\Controllers\Api\V1\UnitDiscountController;
use App\Http\Controllers\Api\V1\Configuration\NotificationSettingsController;
use App\Http\Controllers\Api\V1\Configuration\PropertyController;
use App\Http\Controllers\Api\V1\Configuration\TeamMemberController;
use App\Http\Controllers\Api\V1\Configuration\UnitController;
use App\Http\Controllers\Api\V1\Configuration\UnitRateIntervalController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): \Illuminate\Http\JsonResponse {
    return response()->json([
        'status' => 'ok',
        'message' => 'hofros API is running',
    ]);
});

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/public/direct-portals/{slug}', [PublicDirectPortalController::class, 'show'])
        ->where('slug', '[a-z0-9-]+');
    Route::get('/public/direct-portals/{slug}/calendar', [PublicDirectPortalCalendarController::class, 'show'])
        ->where('slug', '[a-z0-9-]+');
    Route::get('/public/direct-portals/{slug}/quote', [PublicDirectPortalQuoteController::class, 'show'])
        ->where('slug', '[a-z0-9-]+');
    Route::post('/public/direct-portals/{slug}/bookings', [PublicDirectPortalBookingController::class, 'store'])
        ->where('slug', '[a-z0-9-]+');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/configuration/properties', [PropertyController::class, 'index']);
        Route::post('/configuration/properties', [PropertyController::class, 'store']);
        Route::put('/configuration/properties/{id}', [PropertyController::class, 'update']);
        Route::delete('/configuration/properties/{id}', [PropertyController::class, 'destroy']);

        Route::get('/configuration/units', [UnitController::class, 'index']);
        Route::post('/configuration/units', [UnitController::class, 'store']);
        Route::post('/configuration/units/{id}/images', [UnitController::class, 'uploadImages']);
        Route::put('/configuration/units/{id}', [UnitController::class, 'update']);
        Route::patch('/configuration/units/{id}/week-schedule', [UnitController::class, 'updateWeekSchedule']);
        Route::delete('/configuration/units/{id}', [UnitController::class, 'destroy']);

        Route::get('/configuration/units/{unitId}/rate-intervals', [UnitRateIntervalController::class, 'index']);
        Route::post('/configuration/units/{unitId}/rate-intervals', [UnitRateIntervalController::class, 'store']);
        Route::put('/configuration/units/{unitId}/rate-intervals/{intervalId}', [UnitRateIntervalController::class, 'update']);
        Route::delete('/configuration/units/{unitId}/rate-intervals/{intervalId}', [UnitRateIntervalController::class, 'destroy']);

        Route::get('/configuration/notifications', [NotificationSettingsController::class, 'show']);
        Route::put('/configuration/notifications', [NotificationSettingsController::class, 'update']);

        Route::get('/configuration/team', [TeamMemberController::class, 'index']);
        Route::patch('/configuration/team/{id}', [TeamMemberController::class, 'update']);
        Route::post('/configuration/team/invite', [TeamMemberController::class, 'invite']);

        Route::get('/booking-portals', [BookingPortalController::class, 'index']);
        Route::get('/booking-portals/direct-website/settings', [BookingPortalController::class, 'directWebsiteSettings']);
        Route::post('/booking-portals/direct-website/hero-image', [BookingPortalController::class, 'uploadDirectWebsiteHero']);
        Route::patch('/booking-portals/direct-website/content', [BookingPortalController::class, 'saveDirectWebsiteContent']);
        Route::post('/booking-portals/direct-website/design', [BookingPortalController::class, 'saveDirectWebsiteDesign']);
        Route::patch('/booking-portals/direct-website/live', [BookingPortalController::class, 'setDirectWebsiteLive']);
        Route::get('/booking-portals/available', [BookingPortalController::class, 'available']);
        Route::patch('/booking-portals/{portalKey}/active', [BookingPortalController::class, 'updateActive'])
            ->where('portalKey', '[a-z_]+');
        Route::post('/booking-portals/{portalKey}/sync', [BookingPortalController::class, 'sync'])
            ->where('portalKey', '[a-z_]+');
        Route::post('/booking-portals/{portalKey}/connect', [BookingPortalController::class, 'connect'])
            ->where('portalKey', '[a-z_]+');

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/analytics', [AnalyticsController::class, 'summary']);
        Route::get('/analytics/export', [AnalyticsController::class, 'export']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{id}/available-units', [BookingController::class, 'availableUnits']);
        Route::get('/bookings/{id}/payments', [BookingPaymentController::class, 'index']);
        Route::post('/bookings/{id}/payments', [BookingPaymentController::class, 'store']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::patch('/bookings/{id}', [BookingController::class, 'update']);
        Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);

        Route::get('/calendar', [CalendarController::class, 'index']);
        Route::get('/unit-date-blocks', [UnitDateBlockController::class, 'index']);
        Route::post('/unit-date-blocks', [UnitDateBlockController::class, 'store']);
        Route::patch('/unit-date-blocks/{id}', [UnitDateBlockController::class, 'update']);
        Route::delete('/unit-date-blocks/{id}', [UnitDateBlockController::class, 'destroy']);

        Route::get('/discounts/promo-codes', [PromoCodeController::class, 'index']);
        Route::post('/discounts/promo-codes', [PromoCodeController::class, 'store']);
        Route::put('/discounts/promo-codes/{id}', [PromoCodeController::class, 'update']);
        Route::delete('/discounts/promo-codes/{id}', [PromoCodeController::class, 'destroy']);

        Route::get('/discounts/unit-discounts', [UnitDiscountController::class, 'index']);
        Route::post('/discounts/unit-discounts', [UnitDiscountController::class, 'store']);
        Route::put('/discounts/unit-discounts/{id}', [UnitDiscountController::class, 'update']);
        Route::delete('/discounts/unit-discounts/{id}', [UnitDiscountController::class, 'destroy']);
    });
});
