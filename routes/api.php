docker exec -i skybroker-app bash -lc 'cat > routes/api.php << "PHP"
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ShipmentsController;
use App\Http\Controllers\Api\V1\PaymentsController;
use App\Http\Controllers\Api\V1\WebhooksController;

Route::get("/health", HealthController::class);

Route::prefix("v1")->group(function () {
    Route::post("/shipments", [ShipmentsController::class, "store"]);
    Route::get("/shipments/{id}/label", [ShipmentsController::class, "label"]);
    Route::get("/shipments/{id}/tracking", [ShipmentsController::class, "tracking"]);

    Route::post("/payments/{shipmentId}/start", [PaymentsController::class, "start"]);
    Route::post("/payments/simulate", [PaymentsController::class, "simulate"]);

    Route::post("/webhooks/incoming/payments", [WebhooksController::class, "payments"]);
});
